<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat\Tool;

use WB\AiChatbot\Model\Chat\ChatContext;
use WB\AiChatbot\Model\Chat\ProductFinder;
use WB\AiChatbot\Model\Config;

class ProductSearch implements ToolInterface
{
    /**
     * @var ProductFinder
     */
    private $finder;

    /**
     * @var Config
     */
    private $config;

    public function __construct(ProductFinder $finder, Config $config)
    {
        $this->finder = $finder;
        $this->config = $config;
    }

    public function getName(): string
    {
        return 'product_search';
    }

    public function getDescription(): string
    {
        return 'Search the store catalog and show product cards. Use whenever the customer asks for products, recommendations, gifts, '
            . 'something in a style/colour/stone/price range, or availability ("ready to ship"). Returns price in the store currency, '
            . 'availability and the product URL. Always call this instead of guessing product names or prices.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'What to search for, in a few keywords (e.g. "gold polki jhumka earrings")'],
                'price_max' => ['type' => 'number', 'description' => 'Maximum price in the store currency, if the customer gave a budget'],
                'price_min' => ['type' => 'number', 'description' => 'Minimum price in the store currency'],
                'availability' => ['type' => 'string', 'enum' => ['in_stock', 'pre_order', 'any'], 'description' => 'Only in-stock (ready to ship) items, pre-order allowed, or any'],
                'limit' => ['type' => 'integer', 'description' => 'How many products to show (1-8)'],
            ],
            'required' => ['query'],
        ];
    }

    public function isAvailable(ChatContext $context): bool
    {
        return true;
    }

    public function execute(array $arguments, ChatContext $context): ToolResult
    {
        $query = trim((string)($arguments['query'] ?? ''));
        if ($query === '') {
            return ToolResult::error('missing_query', 'Tell me what you are looking for and I will find it.');
        }
        $filters = [
            'price_max' => isset($arguments['price_max']) ? (float)$arguments['price_max'] : null,
            'price_min' => isset($arguments['price_min']) ? (float)$arguments['price_min'] : null,
            'availability' => isset($arguments['availability']) && $arguments['availability'] !== 'any' ? (string)$arguments['availability'] : null,
        ];
        $limit = max(1, min(8, (int)($arguments['limit'] ?? $this->config->getMaxProductCards($context->getStoreId()))));
        $result = $this->finder->find($query, $context->getStoreId(), $limit, $filters);

        $data = ['query' => $result['query'], 'filters' => $result['filters'], 'count' => count($result['products']), 'products' => []];
        foreach ($result['products'] as $card) {
            $data['products'][] = [
                'name' => $card['name'],
                'sku' => $card['sku'],
                'price' => $card['price_text'],
                'regular_price' => $card['regular_price_text'],
                'availability' => $card['availability_label'],
                'colors' => $card['colors'],
                'sizes' => $card['sizes'],
                'summary' => $card['summary'],
                'url' => $card['url'],
            ];
        }
        if ($result['products'] === []) {
            $text = 'I could not find products matching that. Try different words, a wider budget, or browse our collections.';
        } else {
            $text = count($result['products']) === 1 ? 'Here is what I found:' : sprintf('Here are %d pieces you may like:', count($result['products']));
        }
        return new ToolResult($data, $result['products'], $text);
    }
}
