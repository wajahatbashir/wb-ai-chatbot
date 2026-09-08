<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat\Tool;

use WB\AiChatbot\Model\Chat\ChatContext;

class OrderInvoice extends OrderInfo
{
    public function getName(): string
    {
        return 'order_invoice';
    }

    public function getDescription(): string
    {
        return 'Give the customer a secure download link for the PDF invoice(s) of an order. '
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
        if ($summary['invoices'] === []) {
            return new ToolResult(
                ['increment_id' => $summary['increment_id'], 'invoices' => []],
                [],
                sprintf('Order %s has not been invoiced yet (status: %s). The invoice becomes available once payment is captured.', $summary['increment_id'], $summary['status'])
            );
        }
        $cards = [];
        foreach ($summary['invoices'] as $invoice) {
            $cards[] = $this->cards->file(
                'Invoice ' . $invoice['increment_id'] . ' (PDF)',
                $invoice['download_url'],
                sprintf('%s · %s · link valid for 30 minutes', $invoice['created_at'], $invoice['grand_total'])
            );
        }
        return new ToolResult(
            ['increment_id' => $summary['increment_id'], 'invoices' => $summary['invoices']],
            $cards,
            count($cards) === 1
                ? sprintf('Here is the invoice for order %s. The download link is valid for 30 minutes.', $summary['increment_id'])
                : sprintf('Order %s has %d invoices. The download links are valid for 30 minutes.', $summary['increment_id'], count($cards))
        );
    }
}
