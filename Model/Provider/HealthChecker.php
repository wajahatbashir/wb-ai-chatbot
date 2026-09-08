<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Provider;

use Magento\Framework\Stdlib\DateTime\DateTime;
use WB\AiChatbot\Api\CredentialRepositoryInterface;
use WB\AiChatbot\Api\Data\CredentialInterface;

/**
 * Runs a provider's cheap authenticated call and records the outcome on the credential.
 */
class HealthChecker
{
    /**
     * @var Pool
     */
    private $pool;

    /**
     * @var CredentialRepositoryInterface
     */
    private $credentialRepository;

    /**
     * @var DateTime
     */
    private $dateTime;

    public function __construct(Pool $pool, CredentialRepositoryInterface $credentialRepository, DateTime $dateTime)
    {
        $this->pool = $pool;
        $this->credentialRepository = $credentialRepository;
        $this->dateTime = $dateTime;
    }

    public function check(CredentialInterface $credential): HealthResult
    {
        $code = (string)$credential->getProviderCode();
        if (!$this->pool->hasProvider($code)) {
            $result = new HealthResult(CredentialInterface::STATUS_MODEL_UNAVAILABLE, sprintf('Unknown provider "%s"', $code));
        } else {
            $result = $this->pool->getProvider($code)->healthCheck($credential);
        }

        if ($result->isHealthy()) {
            $credential->setStatus(CredentialInterface::STATUS_OK);
            $credential->setStatusMessage(mb_substr($result->getMessage(), 0, 1000));
            $credential->setLastCheckedAt($this->dateTime->gmtDate('Y-m-d H:i:s'));
            $credential->setRetryAfter(null);
            $this->credentialRepository->save($credential);
        } else {
            // Same bookkeeping as a failed live call: permanent statuses stay excluded until the admin
            // re-saves the key, transient ones get a retry_after backoff so the pool skips them for now.
            $this->pool->markFailed($credential, new ProviderException($result->getStatus(), $result->getMessage()));
        }

        return $result;
    }
}
