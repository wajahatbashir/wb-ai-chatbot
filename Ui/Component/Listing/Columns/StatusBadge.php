<?php
declare(strict_types=1);

namespace WB\AiChatbot\Ui\Component\Listing\Columns;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use WB\AiChatbot\Api\Data\CredentialInterface;

/**
 * Renders the health status as a coloured badge so a broken key is visible at a glance.
 */
class StatusBadge extends Column
{
    private const COLORS = [
        CredentialInterface::STATUS_OK => '#1f7a3a',
        CredentialInterface::STATUS_UNCHECKED => '#6b7280',
        CredentialInterface::STATUS_RATE_LIMITED => '#b7791f',
        CredentialInterface::STATUS_TRANSIENT_ERROR => '#b7791f',
    ];

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $field = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            $status = (string)($item['status'] ?? CredentialInterface::STATUS_UNCHECKED);
            $color = self::COLORS[$status] ?? '#b91c1c';
            $title = htmlspecialchars((string)($item['status_message'] ?? ''), ENT_QUOTES);
            $item[$field] = sprintf(
                '<span title="%s" style="display:inline-block;padding:2px 8px;border-radius:10px;color:#fff;font-size:11px;font-weight:600;background:%s">%s</span>',
                $title,
                $color,
                htmlspecialchars(str_replace('_', ' ', $status), ENT_QUOTES)
            );
        }
        unset($item);

        return $dataSource;
    }
}
