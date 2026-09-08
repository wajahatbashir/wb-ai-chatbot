<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat\Tool;

use WB\AiChatbot\Model\Chat\Cards;
use WB\AiChatbot\Model\Chat\ChatContext;

class CartInfo implements ToolInterface
{
    /**
     * @var Cards
     */
    private $cards;

    public function __construct(Cards $cards)
    {
        $this->cards = $cards;
    }

    public function getName(): string
    {
        return 'cart_info';
    }

    public function getDescription(): string
    {
        return 'Show what is currently in the customer\'s shopping cart (items, quantities, subtotal).';
    }

    public function getParameters(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass()];
    }

    public function isAvailable(ChatContext $context): bool
    {
        return $context->getQuote() !== null;
    }

    public function execute(array $arguments, ChatContext $context): ToolResult
    {
        $quote = $context->getQuote();
        if (!$quote || !$quote->getItemsCount()) {
            return new ToolResult(['items' => [], 'count' => 0], [], 'Your cart is empty at the moment.');
        }
        $currency = (string)$quote->getQuoteCurrencyCode();
        $items = [];
        $lines = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $items[] = [
                'name' => $item->getName(),
                'sku' => $item->getSku(),
                'qty' => (float)$item->getQty(),
                'price' => $this->cards->format((float)$item->getPriceInclTax() ?: (float)$item->getPrice(), $currency),
                'row_total' => $this->cards->format((float)$item->getRowTotalInclTax() ?: (float)$item->getRowTotal(), $currency),
            ];
            $lines[] = sprintf('- %s × %s – %s', $item->getName(), (float)$item->getQty(), $this->cards->format((float)($item->getRowTotalInclTax() ?: $item->getRowTotal()), $currency));
        }
        $subtotal = $this->cards->format((float)($quote->getSubtotalWithDiscount() ?: $quote->getSubtotal()), $currency);
        $data = ['count' => count($items), 'items' => $items, 'subtotal' => $subtotal, 'currency' => $currency];
        $text = "Here is what is in your cart:\n" . implode("\n", $lines) . "\nSubtotal: " . $subtotal;
        return new ToolResult($data, [$this->cards->link('Go to cart', '/checkout/cart/', 'Review your bag and check out')], $text);
    }
}
