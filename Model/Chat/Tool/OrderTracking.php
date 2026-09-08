<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat\Tool;

use WB\AiChatbot\Model\Chat\ChatContext;

class OrderTracking extends OrderInfo
{
    public function getName(): string
    {
        return 'order_tracking';
    }

    public function getDescription(): string
    {
        return 'Shipment tracking for an order: carrier, tracking number and tracking link, or "not shipped yet". '
            . 'Same access rules as order_info (order number + verified email for guests).';
    }

    public function execute(array $arguments, ChatContext $context): ToolResult
    {
        $result = $this->lookup->find($context, (string)($arguments['order_number'] ?? ''), isset($arguments['email']) ? (string)$arguments['email'] : null);
        if (isset($result['error'])) {
            $toolResult = ToolResult::error($result['error'], $result['message']);
            return isset($result['email']) ? new ToolResult($toolResult->getData() + ['email' => $result['email']], [], $result['message']) : $toolResult;
        }
        $summary = $this->lookup->summarize($result['order'], false);
        $data = [
            'increment_id' => $summary['increment_id'],
            'status' => $summary['status'],
            'shipments' => $summary['shipments'],
            'tracking_url' => $summary['tracking_url'] ?? null,
        ];
        $cards = [];
        if ($summary['shipments'] === []) {
            $text = in_array($summary['state'], ['canceled', 'closed'], true)
                ? sprintf('Order %s is %s, so there is no shipment to track.', $summary['increment_id'], $summary['status'])
                : sprintf('Order %s (%s) has not been shipped yet. You will receive the tracking details by email as soon as it is dispatched.', $summary['increment_id'], $summary['status']);
        } else {
            $lines = [sprintf('Tracking for order %s:', $summary['increment_id'])];
            foreach ($summary['shipments'] as $shipment) {
                foreach ($shipment['tracks'] as $track) {
                    $lines[] = sprintf('- %s, tracking number **%s** (shipped %s)', $track['carrier'], $track['number'], $shipment['created_at']);
                }
                if ($shipment['tracks'] === []) {
                    $lines[] = sprintf('- Shipment %s dispatched on %s (no tracking number recorded)', $shipment['increment_id'], $shipment['created_at']);
                }
            }
            if (!empty($summary['tracking_url'])) {
                $cards[] = $this->cards->link('Track shipment', $summary['tracking_url'], 'Live tracking status');
            }
            $text = implode("\n", $lines);
        }
        return new ToolResult($data, $cards, $text);
    }
}
