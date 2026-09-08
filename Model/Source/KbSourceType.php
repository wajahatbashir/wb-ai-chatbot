<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use WB\AiChatbot\Model\Kb\Document;
use WB\AiChatbot\Model\Kb\Sync\ProviderPool;

class KbSourceType implements OptionSourceInterface
{
    /**
     * @var ProviderPool
     */
    private $providerPool;

    public function __construct(ProviderPool $providerPool)
    {
        $this->providerPool = $providerPool;
    }

    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->providerPool->getAll() as $code => $provider) {
            $options[] = ['value' => $code, 'label' => __($provider->getTitle())];
        }
        $options[] = ['value' => Document::SOURCE_MANUAL, 'label' => __('Manual Article')];
        return $options;
    }
}
