<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Position implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'bottom_right', 'label' => __('Bottom Right')],
            ['value' => 'bottom_left', 'label' => __('Bottom Left')],
        ];
    }
}
