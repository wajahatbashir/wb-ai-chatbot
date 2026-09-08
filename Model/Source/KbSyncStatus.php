<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use WB\AiChatbot\Model\Kb\Document;

class KbSyncStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Document::STATUS_INDEXED, 'label' => __('Indexed')],
            ['value' => Document::STATUS_PENDING, 'label' => __('Pending')],
            ['value' => Document::STATUS_DIRTY, 'label' => __('Changed (re-sync queued)')],
            ['value' => Document::STATUS_FAILED, 'label' => __('Failed')],
        ];
    }
}
