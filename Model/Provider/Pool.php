<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Provider;

use Magento\Framework\Stdlib\DateTime\DateTime;
use WB\AiChatbot\Api\CredentialRepositoryInterface;
use WB\AiChatbot\Api\Data\CredentialInterface;
use WB\AiChatbot\Api\ProviderInterface;
use WB\AiChatbot\Model\Config;
use WB\AiChatbot\Model\Logger;

/**
 * Ordered fallback chain over the configured credentials. A failed call marks the credential (status, retry_after)
 * and moves on to the next one; when nothing is usable the caller falls back to Assist mode.
 */
class Pool
{
    /**
     * @var ProviderInterface[] keyed by provider code
     */
    private $providers;

    /**
     * @var CredentialRepositoryInterface
     */
    private $credentialRepository;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var CredentialInterface[]|null
     */
    private $enabledCache;

    /**
     * @var FailureNotifier
     */
    private $failureNotifier;

    /**
     * @param ProviderInterface[] $providers
     */
    public function __construct(
        array $providers,
        CredentialRepositoryInterface $credentialRepository,
        Config $config,
        DateTime $dateTime,
        Logger $logger,
        FailureNotifier $failureNotifier
    ) {
        $this->failureNotifier = $failureNotifier;
        $this->providers = [];
        foreach ($providers as $provider) {
            if ($provider instanceof ProviderInterface) {
                $this->providers[$provider->getCode()] = $provider;
            }
        }
        $this->credentialRepository = $credentialRepository;
        $this->config = $config;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
    }

    /**
     * @return ProviderInterface[] keyed by code
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    public function getProvider(string $code): ProviderInterface
    {
        if (!isset($this->providers[$code])) {
            throw new \InvalidArgumentException(sprintf('Unknown AI provider "%s"', $code));
        }
        return $this->providers[$code];
    }

    public function hasProvider(string $code): bool
    {
        return isset($this->providers[$code]);
    }

    /**
     * Credentials worth trying right now, in fallback order: enabled, known provider, not permanently broken,
     * past their retry_after, and Mock only when the developer flag allows it.
     *
     * @return CredentialInterface[]
     */
    public function getChatCandidates(): array
    {
        $now = $this->dateTime->gmtTimestamp();
        $candidates = [];
        foreach ($this->getEnabled() as $credential) {
            $code = (string)$credential->getProviderCode();
            if (!$this->hasProvider($code)) {
                continue;
            }
            if ($code === Mock::CODE && !$this->config->isMockAllowed()) {
                continue;
            }
            if ($this->isPermanentlyBroken($credential)) {
                continue;
            }
            $retryAfter = $credential->getRetryAfter();
            if ($retryAfter && strtotime($retryAfter) > $now) {
                continue;
            }
            $candidates[] = $credential;
        }
        return $candidates;
    }

    /**
     * @return CredentialInterface[]
     */
    public function getEmbeddingCandidates(): array
    {
        $candidates = [];
        foreach ($this->getChatCandidates() as $credential) {
            $provider = $this->getProvider((string)$credential->getProviderCode());
            if ($provider->getEmbeddingModels() !== []) {
                $candidates[] = $credential;
            }
        }
        return $candidates;
    }

    public function isAiAvailable(): bool
    {
        return $this->config->getMode() !== Config::MODE_ASSIST && $this->getChatCandidates() !== [];
    }

    /**
     * Runs $callback(ProviderInterface $provider, CredentialInterface $credential) against each chat candidate in
     * order until one succeeds. Failures are recorded on the credential.
     *
     * @throws ProviderException when every candidate failed (the last failure)
     * @throws NoProviderException when there was nothing to try
     */
    public function execute(callable $callback, bool $needEmbeddings = false)
    {
        $candidates = $needEmbeddings ? $this->getEmbeddingCandidates() : $this->getChatCandidates();
        if ($candidates === []) {
            throw new NoProviderException('No usable AI provider credential');
        }
        $lastException = null;
        foreach ($candidates as $credential) {
            $provider = $this->getProvider((string)$credential->getProviderCode());
            try {
                $result = $callback($provider, $credential);
                $this->markHealthy($credential);
                return $result;
            } catch (ProviderException $e) {
                $lastException = $e;
                $this->markFailed($credential, $e);
                $this->logger->warning(sprintf(
                    'Provider "%s" (%s) failed with %s: %s - trying next credential',
                    $credential->getAlias(),
                    $credential->getProviderCode(),
                    $e->getStatus(),
                    $e->getMessage()
                ));
            }
        }
        throw $lastException;
    }

    public function markFailed(CredentialInterface $credential, ProviderException $e): void
    {
        $nowTs = $this->dateTime->gmtTimestamp();
        $credential->setStatus($e->getStatus());
        $credential->setStatusMessage(mb_substr($e->getMessage(), 0, 1000));
        $credential->setLastErrorAt($this->dateTime->gmtDate('Y-m-d H:i:s', $nowTs));
        $credential->setLastCheckedAt($this->dateTime->gmtDate('Y-m-d H:i:s', $nowTs));
        if (!$e->isPermanent()) {
            $minutes = $this->config->getRetryMinutes();
            // Double the wait while the previous failure is still recent, capped at an hour.
            $previous = $credential->getRetryAfter();
            if ($previous && strtotime($previous) > $nowTs - 3600) {
                $minutes = min(60, $minutes * 2);
            }
            $credential->setRetryAfter($this->dateTime->gmtDate('Y-m-d H:i:s', $nowTs + $minutes * 60));
        } else {
            $credential->setRetryAfter(null);
        }
        $this->persist($credential);
        $this->enabledCache = null;
        try {
            $this->failureNotifier->notify($credential, $e, $this->getChatCandidates() !== []);
        } catch (\Throwable $notifyError) {
            $this->logger->error('Provider failure notification error: ' . $notifyError->getMessage());
        }
    }

    public function markHealthy(CredentialInterface $credential): void
    {
        if ($credential->getStatus() === CredentialInterface::STATUS_OK && !$credential->getRetryAfter()) {
            return;
        }
        $credential->setStatus(CredentialInterface::STATUS_OK);
        $credential->setStatusMessage('OK');
        $credential->setRetryAfter(null);
        $credential->setLastCheckedAt($this->dateTime->gmtDate('Y-m-d H:i:s'));
        $this->persist($credential);
    }

    private function isPermanentlyBroken(CredentialInterface $credential): bool
    {
        return in_array($credential->getStatus(), [
            CredentialInterface::STATUS_INVALID_KEY,
            CredentialInterface::STATUS_EMPTY_KEY,
            CredentialInterface::STATUS_MODEL_UNAVAILABLE,
        ], true);
    }

    /**
     * @return CredentialInterface[]
     */
    private function getEnabled(): array
    {
        if ($this->enabledCache === null) {
            $this->enabledCache = $this->credentialRepository->getEnabledOrdered();
        }
        return $this->enabledCache;
    }

    private function persist(CredentialInterface $credential): void
    {
        try {
            $this->credentialRepository->save($credential);
        } catch (\Exception $e) {
            $this->logger->error('Could not persist credential status: ' . $e->getMessage());
        }
    }
}
