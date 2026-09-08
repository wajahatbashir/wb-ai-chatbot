<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use WB\AiChatbot\Api\Data\CredentialInterface;

class Status implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => CredentialInterface::STATUS_OK, 'label' => __('OK')],
            ['value' => CredentialInterface::STATUS_UNCHECKED, 'label' => __('Unchecked')],
            ['value' => CredentialInterface::STATUS_EMPTY_KEY, 'label' => __('Empty key')],
            ['value' => CredentialInterface::STATUS_INVALID_KEY, 'label' => __('Invalid key')],
            ['value' => CredentialInterface::STATUS_INSUFFICIENT_QUOTA, 'label' => __('Insufficient quota')],
            ['value' => CredentialInterface::STATUS_RATE_LIMITED, 'label' => __('Rate limited')],
            ['value' => CredentialInterface::STATUS_MODEL_UNAVAILABLE, 'label' => __('Model unavailable')],
            ['value' => CredentialInterface::STATUS_TRANSIENT_ERROR, 'label' => __('Transient error')],
        ];
    }
}
