<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat\Tool;

use WB\AiChatbot\Model\Chat\Cards;
use WB\AiChatbot\Model\Chat\ChatContext;
use WB\AiChatbot\Model\Chat\ProductFinder;

class ProductCompare implements ToolInterface
{
    /**
     * @var ProductFinder
     */
    private $finder;

    /**
     * @var Cards
     */
    private $cards;

    public function __construct(ProductFinder $finder, Cards $cards)
    {
        $this->finder = $finder;
        $this->cards = $cards;
    }

    public function getName(): string
    {
        return 'product_compare';
    }

    public function getDescription(): string
    {
        return 'Compare 2-4 products side by side (price, availability, colours, sizes, categories, details). '
            . 'Pass the product names or SKUs the customer mentioned.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'products' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Product names or SKUs (2-4)'],
            ],
            'required' => ['products'],
        ];
    }

    public function isAvailable(ChatContext $context): bool
    {
        return true;
    }

    public function execute(array $arguments, ChatContext $context): ToolResult
    {
        $identifiers = array_slice(array_values(array_filter(array_map('strval', (array)($arguments['products'] ?? [])))), 0, 4);
        if (count($identifiers) < 2) {
            return ToolResult::error('need_two', 'Tell me which two (or more) products you would like to compare.');
        }
        $found = $this->finder->findByIdentifiers($identifiers, $context->getStoreId());
        if (count($found) < 2) {
            return ToolResult::error('not_found', 'I could only find one of those products. Could you check the names or SKUs?');
        }
        $columns = ['Attribute'];
        $rows = [
            'Price' => ['Price'],
            'Availability' => ['Availability'],
            'Colours' => ['Colours'],
            'Sizes' => ['Sizes'],
            'Categories' => ['Categories'],
            'Details' => ['Details'],
        ];
        $data = [];
        foreach ($found as $item) {
            $card = $item['card'];
            $attributes = $item['attributes'];
            $columns[] = $card['name'];
            $rows['Price'][] = $card['price_text'] . ($card['on_sale'] ? ' (was ' . $card['regular_price_text'] . ')' : '');
            $rows['Availability'][] = $card['availability_label'];
            $rows['Colours'][] = implode(', ', $card['colors']) ?: '-';
            $rows['Sizes'][] = implode(', ', $card['sizes']) ?: '-';
            $rows['Categories'][] = implode('; ', array_slice((array)($attributes['categories'] ?? []), 0, 3)) ?: '-';
            $rows['Details'][] = $this->details($item['text']);
            $data[] = [
                'name' => $card['name'], 'sku' => $card['sku'], 'price' => $card['price_text'],
                'availability' => $card['availability_label'], 'colors' => $card['colors'], 'sizes' => $card['sizes'],
                'details' => $this->details($item['text']), 'url' => $card['url'],
            ];
        }
        $table = $this->cards->table('Comparison', $columns, array_values($rows));
        $cards = [$table];
        foreach ($found as $item) {
            $cards[] = $item['card'];
        }
        return new ToolResult(['products' => $data], $cards, 'Here is a side-by-side comparison:');
    }

    private function details(string $sheet): string
    {
        $details = [];
        foreach (explode("\n", $sheet) as $line) {
            if (preg_match('/^(Length|Width|Weight|Material|Fabric|Work|Occasion|Brand|Ready to ship): (.+)$/', $line, $m)) {
                $details[] = $m[1] . ' ' . $m[2];
            }
        }
        return $details !== [] ? implode(', ', $details) : '-';
    }
}
