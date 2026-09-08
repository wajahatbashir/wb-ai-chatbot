<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb\Sync;

class ProviderPool
{
    /**
     * @var SyncProviderInterface[] keyed by code
     */
    private $providers = [];

    /**
     * @param SyncProviderInterface[] $providers
     */
    public function __construct(array $providers = [])
    {
        foreach ($providers as $provider) {
            if ($provider instanceof SyncProviderInterface) {
                $this->providers[$provider->getCode()] = $provider;
            }
        }
    }

    /**
     * @return SyncProviderInterface[] keyed by code
     */
    public function getAll(): array
    {
        return $this->providers;
    }

    public function get(string $code): SyncProviderInterface
    {
        if (!isset($this->providers[$code])) {
            throw new \InvalidArgumentException(sprintf('Unknown knowledge base source "%s"', $code));
        }
        return $this->providers[$code];
    }

    public function has(string $code): bool
    {
        return isset($this->providers[$code]);
    }
}
