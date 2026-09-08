<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use WB\AiChatbot\Model\Provider\Pool;

class ProviderCode implements OptionSourceInterface
{
    /**
     * @var Pool
     */
    private $pool;

    public function __construct(Pool $pool)
    {
        $this->pool = $pool;
    }

    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->pool->getProviders() as $code => $provider) {
            $options[] = ['value' => $code, 'label' => $provider->getLabel()];
        }
        return $options;
    }
}
