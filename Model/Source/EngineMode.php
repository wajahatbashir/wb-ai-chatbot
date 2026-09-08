<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use WB\AiChatbot\Model\Config;

class EngineMode implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::MODE_AUTO, 'label' => __('Auto - AI when a healthy provider exists, otherwise Assist mode')],
            ['value' => Config::MODE_ASSIST, 'label' => __('Assist only - never call an AI provider')],
        ];
    }
}
