<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

use Magento\Framework\App\ResourceConnection;
use WB\AiChatbot\Model\Kb\Retriever;
use WB\AiChatbot\Model\Kb\Sync\Provider\Product as ProductSync;

/**
 * Product search over the knowledge base's product documents (per store view, so prices/currency are right),
 * with simple constraint parsing so "gold earrings under 15000 ready to ship" works even without AI.
 */
class ProductFinder
{
    private const CANDIDATES = 40;

    /**
     * @var Retriever
     */
    private $retriever;

    /**
     * @var Cards
     */
    private $cards;

    /**
     * @var ResourceConnection
     */
    private $resource;

    public function __construct(Retriever $retriever, Cards $cards, ResourceConnection $resource)
    {
        $this->retriever = $retriever;
        $this->cards = $cards;
        $this->resource = $resource;
    }

    /**
     * @param array $filters ['price_max' => float, 'price_min' => float, 'availability' => in_stock|pre_order, 'category' => string]
     * @return array{products: array, filters: array, query: string}
     */
    public function find(string $query, int $storeId, int $limit = 4, array $filters = [], bool $useEmbeddings = true): array
    {
        $parsed = $this->parse($query);
        $filters = array_filter($filters, function ($v) {
            return $v !== null && $v !== '';
        }) + $parsed['filters'];
        $searchText = trim($parsed['query']) !== '' ? $parsed['query'] : $query;
        if (!empty($filters['category'])) {
            $searchText .= ' ' . $filters['category'];
        }

        $results = $this->retriever->search($searchText, $storeId, self::CANDIDATES, ['product'], $useEmbeddings);
        $products = [];
        foreach ($results as $row) {
            $attributes = $row['attributes'];
            if (!$this->passes($attributes, $filters)) {
                continue;
            }
            $card = $this->cards->product($attributes, $row['title'], $row['url']);
            $card['score'] = $row['score'];
            $card['summary'] = Text::truncate($this->stripSheet($row['text']), 220);
            $products[] = $card;
            if (count($products) >= $limit) {
                break;
            }
        }
        return ['products' => $products, 'filters' => $filters, 'query' => $searchText];
    }

    /**
     * Products by exact SKU or name, for comparisons.
     *
     * @param string[] $identifiers
     * @return array<int, array{card: array, attributes: array, text: string}>
     */
    public function findByIdentifiers(array $identifiers, int $storeId): array
    {
        $found = [];
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('wb_aichatbot_kb_document');
        foreach ($identifiers as $identifier) {
            $identifier = trim($identifier);
            if ($identifier === '') {
                continue;
            }
            $row = $connection->fetchRow(
                $connection->select()->from($table, ['title', 'url', 'attributes', 'body'])
                    ->where('source_type = ?', 'product')
                    ->where('store_id = ?', $storeId)
                    ->where('is_enabled = 1')
                    ->where(
                        'attributes LIKE ' . $connection->quote('%"sku": "' . strtoupper($identifier) . '"%')
                        . ' OR title = ' . $connection->quote($identifier)
                    )
                    ->limit(1)
            );
            if (!$row) {
                $hits = $this->retriever->search($identifier, $storeId, 1, ['product'], false);
                if ($hits !== []) {
                    $row = ['title' => $hits[0]['title'], 'url' => $hits[0]['url'], 'attributes' => json_encode($hits[0]['attributes']), 'body' => $hits[0]['text']];
                }
            }
            if ($row) {
                $attributes = json_decode((string)$row['attributes'], true) ?: [];
                $found[] = [
                    'card' => $this->cards->product($attributes, (string)$row['title'], $row['url']),
                    'attributes' => $attributes,
                    'text' => (string)$row['body'],
                ];
            }
        }
        return $found;
    }

    /**
     * @return array{query: string, filters: array}
     */
    public function parse(string $query): array
    {
        $filters = [];
        $text = ' ' . mb_strtolower($query) . ' ';
        $number = '(\d[\d,]*(?:\.\d+)?)\s*(k|thousand)?';
        $toAmount = function (array $m): float {
            $value = (float)str_replace(',', '', $m[1]);
            return !empty($m[2]) ? $value * 1000 : $value;
        };
        if (preg_match('/\b(?:between|from)\s*(?:pkr|rs\.?|usd|\$)?\s*' . $number . '\s*(?:and|to|-)\s*(?:pkr|rs\.?|usd|\$)?\s*' . $number . '/u', $text, $m)) {
            $filters['price_min'] = $toAmount([$m[0], $m[1], $m[2] ?? '']);
            $filters['price_max'] = $toAmount([$m[0], $m[3], $m[4] ?? '']);
            $text = str_replace($m[0], ' ', $text);
        } elseif (preg_match('/\b(?:under|below|less than|max(?:imum)?|up ?to|within|not more than|cheaper than|budget(?: of| is)?)\s*(?:pkr|rs\.?|usd|\$)?\s*' . $number . '/u', $text, $m)) {
            $filters['price_max'] = $toAmount($m);
            $text = str_replace($m[0], ' ', $text);
        } elseif (preg_match('/\b(?:above|over|more than|min(?:imum)?|at least|starting(?: from| at)?)\s*(?:pkr|rs\.?|usd|\$)?\s*' . $number . '/u', $text, $m)) {
            $filters['price_min'] = $toAmount($m);
            $text = str_replace($m[0], ' ', $text);
        }
        if (preg_match('/\b(ready to ship|in stock|available now|ships? now|immediately)\b/u', $text, $m)) {
            $filters['availability'] = ProductSync::AVAILABILITY_IN_STOCK;
            $text = str_replace($m[0], ' ', $text);
        } elseif (preg_match('/\bpre[- ]?order\b/u', $text, $m)) {
            $filters['availability'] = ProductSync::AVAILABILITY_PRE_ORDER;
        }
        $text = preg_replace('/\b(show me|looking for|do you have|i want|i need|can you (?:suggest|recommend)|recommend|suggest|something|any|please|some)\b/u', ' ', $text) ?? $text;
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
        return ['query' => $text, 'filters' => $filters];
    }

    private function passes(array $attributes, array $filters): bool
    {
        $price = (float)($attributes['final_price'] ?? 0);
        if (isset($filters['price_max']) && $price > (float)$filters['price_max']) {
            return false;
        }
        if (isset($filters['price_min']) && $price < (float)$filters['price_min']) {
            return false;
        }
        $availability = (string)($attributes['availability'] ?? '');
        if (!empty($filters['availability'])) {
            if ($filters['availability'] === ProductSync::AVAILABILITY_IN_STOCK && $availability !== ProductSync::AVAILABILITY_IN_STOCK) {
                return false;
            }
            if ($filters['availability'] === ProductSync::AVAILABILITY_PRE_ORDER && $availability === ProductSync::AVAILABILITY_OUT_OF_STOCK) {
                return false;
            }
        } elseif ($availability === ProductSync::AVAILABILITY_OUT_OF_STOCK) {
            // Never recommend what cannot be bought unless the shopper explicitly asked to include it.
            return !empty($filters['include_out_of_stock']);
        }
        return true;
    }

    /**
     * Drops the "Product:/SKU:/Price:" header lines so the summary shows the descriptive part.
     */
    private function stripSheet(string $text): string
    {
        $lines = [];
        foreach (explode("\n", $text) as $line) {
            if (preg_match('/^(Product|SKU|Price|Availability|Categories|Product page|Variants|Available (colors|sizes)|Summary|Description|Ready to ship):/', $line)) {
                continue;
            }
            if (preg_match('/^- /', $line) || trim($line) === '') {
                continue;
            }
            $lines[] = trim($line);
        }
        $lines = array_slice($lines, 1); // first line is the title
        return implode(' ', $lines);
    }
}
