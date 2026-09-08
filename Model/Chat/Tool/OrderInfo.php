<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat\Tool;

use WB\AiChatbot\Model\Chat\Cards;
use WB\AiChatbot\Model\Chat\ChatContext;
use WB\AiChatbot\Model\Chat\OrderLookup;

class OrderInfo implements ToolInterface
{
    /**
     * @var OrderLookup
     */
    protected $lookup;

    /**
     * @var Cards
     */
    protected $cards;

    public function __construct(OrderLookup $lookup, Cards $cards)
    {
        $this->lookup = $lookup;
        $this->cards = $cards;
    }

    public function getName(): string
    {
        return 'order_info';
    }

    public function getDescription(): string
    {
        return 'Look up an order: status, items, totals, payment and shipping details. Needs the order number; for guests also the '
            . 'email used on the order, which must be verified (if the result says verification_required, call send_verification_code '
            . 'and ask the customer for the 6-digit code, then verify_code).';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_number' => ['type' => 'string', 'description' => 'The order number, e.g. 000000123'],
                'email' => ['type' => 'string', 'description' => 'Email address used on the order (not needed for logged-in customers checking their own orders)'],
            ],
            'required' => ['order_number'],
        ];
    }

    public function isAvailable(ChatContext $context): bool
    {
        return true;
    }

    public function execute(array $arguments, ChatContext $context): ToolResult
    {
        $result = $this->lookup->find($context, (string)($arguments['order_number'] ?? ''), isset($arguments['email']) ? (string)$arguments['email'] : null);
        if (isset($result['error'])) {
            $toolResult = ToolResult::error($result['error'], $result['message']);
            return isset($result['email']) ? new ToolResult($toolResult->getData() + ['email' => $result['email']], [], $result['message']) : $toolResult;
        }
        $summary = $this->lookup->summarize($result['order']);
        return new ToolResult($summary, [$this->cards->order($summary)], $this->describe($summary));
    }

    protected function describe(array $s): string
    {
        $lines = [sprintf('Order %s placed on %s is currently **%s**.', $s['increment_id'], $s['created_at'], $s['status'])];
        foreach ($s['items'] as $item) {
            $lines[] = sprintf('- %s × %s – %s', $item['name'], $item['qty'], $item['price']);
        }
        $lines[] = 'Total: ' . $s['grand_total'] . ($s['payment_method'] ? ' via ' . $s['payment_method'] : '');
        if ($s['shipping_address']) {
            $lines[] = 'Shipping to: ' . $s['shipping_address'] . ($s['shipping_method'] ? ' (' . $s['shipping_method'] . ')' : '');
        }
        if ($s['shipments'] !== []) {
            foreach ($s['shipments'] as $shipment) {
                foreach ($shipment['tracks'] as $track) {
                    $lines[] = sprintf('Shipped on %s via %s, tracking number %s.', $shipment['created_at'], $track['carrier'], $track['number']);
                }
                if ($shipment['tracks'] === []) {
                    $lines[] = sprintf('Shipped on %s.', $shipment['created_at']);
                }
            }
        } elseif (!in_array($s['state'], ['canceled', 'closed', 'complete'], true)) {
            $lines[] = 'It has not been shipped yet.';
        }
        if ($s['refunded']) {
            $lines[] = 'Refunded: ' . $s['refunded'];
        }
        return implode("\n", $lines);
    }
}
