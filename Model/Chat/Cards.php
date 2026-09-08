<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use WB\AiChatbot\Model\Kb\Sync\Provider\Product as ProductSync;

/**
 * Builds the UI cards the widget renders (products, orders, links, tables) from knowledge base / tool data.
 */
class Cards
{
    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    public function __construct(PriceCurrencyInterface $priceCurrency)
    {
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * @param array $attributes the product document's structured attributes
     */
    public function product(array $attributes, string $name, ?string $url): array
    {
        $currency = (string)($attributes['currency'] ?? '');
        $final = (float)($attributes['final_price'] ?? 0);
        $regular = (float)($attributes['price'] ?? $final);
        $availability = (string)($attributes['availability'] ?? ProductSync::AVAILABILITY_OUT_OF_STOCK);
        return [
            'type' => 'product',
            'id' => (int)($attributes['product_id'] ?? 0),
            'name' => $name,
            'sku' => (string)($attributes['sku'] ?? ''),
            'url' => $url,
            'image' => $attributes['image'] ?? null,
            'price' => $regular,
            'final_price' => $final,
            'currency' => $currency,
            'price_text' => $this->format($final, $currency),
            'regular_price_text' => $regular > $final ? $this->format($regular, $currency) : null,
            'on_sale' => $regular > $final,
            'availability' => $availability,
            'availability_label' => ProductSync::availabilityLabel($availability),
            'colors' => array_values((array)($attributes['colors'] ?? [])),
            'sizes' => array_values((array)($attributes['sizes'] ?? [])),
            'can_add_to_cart' => ($attributes['type'] ?? '') === 'simple' && $availability !== ProductSync::AVAILABILITY_OUT_OF_STOCK,
        ];
    }

    public function order(array $summary): array
    {
        return ['type' => 'order'] + $summary;
    }

    public function link(string $label, string $url, ?string $description = null): array
    {
        return ['type' => 'link', 'label' => $label, 'url' => $url, 'description' => $description];
    }

    public function file(string $label, string $url, ?string $description = null): array
    {
        return ['type' => 'file', 'label' => $label, 'url' => $url, 'description' => $description];
    }

    /**
     * @param string[] $columns
     * @param array<int, array<int, string>> $rows
     */
    public function table(string $title, array $columns, array $rows): array
    {
        return ['type' => 'table', 'title' => $title, 'columns' => $columns, 'rows' => $rows];
    }

    public function format(float $amount, string $currency): string
    {
        if ($currency === '') {
            return number_format($amount, 2);
        }
        try {
            return (string)$this->priceCurrency->format($amount, false, PriceCurrencyInterface::DEFAULT_PRECISION, null, $currency);
        } catch (\Throwable $e) {
            return $currency . ' ' . number_format($amount, 2);
        }
    }
}
