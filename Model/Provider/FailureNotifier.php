<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Provider;

use Magento\Store\Model\StoreManagerInterface;
use WB\AiChatbot\Api\Data\CredentialInterface;
use WB\AiChatbot\Model\Chat\Mailer;
use WB\AiChatbot\Model\Chat\RateLimiter;
use WB\AiChatbot\Model\Config;
use WB\AiChatbot\Model\Logger;

/**
 * Emails the admin when a provider credential fails (Notifications > Notify on provider failure), at most once
 * per credential per day so an outage does not flood the inbox.
 */
class FailureNotifier
{
    public const TEMPLATE = 'wb_aichatbot_provider_failure';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Mailer
     */
    private $mailer;

    /**
     * @var RateLimiter
     */
    private $rateLimiter;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(Config $config, Mailer $mailer, RateLimiter $rateLimiter, StoreManagerInterface $storeManager, Logger $logger)
    {
        $this->config = $config;
        $this->mailer = $mailer;
        $this->rateLimiter = $rateLimiter;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    public function notify(CredentialInterface $credential, ProviderException $exception, bool $anyLeft): void
    {
        if (!$this->config->isNotifyOnProviderFailure() || $credential->getProviderCode() === Mock::CODE) {
            return;
        }
        $email = $this->config->getNotificationEmail();
        if ($email === '') {
            return;
        }
        if (!$this->rateLimiter->hit('notify:provider:' . (int)$credential->getCredentialId(), 1, 86400)) {
            return;
        }
        try {
            $storeId = (int)$this->storeManager->getDefaultStoreView()->getId();
        } catch (\Throwable $e) {
            $storeId = 0;
        }
        $sent = $this->mailer->send($this->config->getEmailTemplate('provider_failure'), $storeId, $email, null, [
            'alias' => $credential->getAlias(),
            'provider' => $credential->getProviderCode(),
            'model' => $credential->getModel() ?: 'default',
            'status' => $exception->getStatus(),
            'error' => mb_substr($exception->getMessage(), 0, 500),
            'permanent' => $exception->isPermanent() ? 1 : 0,
            'any_left' => $anyLeft ? 1 : 0,
        ]);
        $this->logger->info(sprintf('Provider failure notification for "%s" %s', $credential->getAlias(), $sent ? 'sent' : 'FAILED'));
    }
}
