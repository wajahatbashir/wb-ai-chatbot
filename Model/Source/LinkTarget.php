<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class LinkTarget implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => '_self', 'label' => __('Same Window')],
            ['value' => '_blank', 'label' => __('New Tab')],
        ];
    }
}
