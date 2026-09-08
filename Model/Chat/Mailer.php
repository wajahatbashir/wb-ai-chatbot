<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

use Magento\Framework\App\Area;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use WB\AiChatbot\Model\Config;
use WB\AiChatbot\Model\Logger;

/**
 * Thin wrapper around TransportBuilder for the module's transactional emails.
 */
class Mailer
{
    /**
     * @var TransportBuilder
     */
    private $transportBuilder;

    /**
     * @var StateInterface
     */
    private $inlineTranslation;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(
        TransportBuilder $transportBuilder,
        StateInterface $inlineTranslation,
        StoreManagerInterface $storeManager,
        Config $config,
        Logger $logger
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $inlineTranslation;
        $this->storeManager = $storeManager;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function send(string $templateId, int $storeId, string $toEmail, ?string $toName, array $vars): bool
    {
        $this->inlineTranslation->suspend();
        try {
            $store = $this->storeManager->getStore($storeId);
            $vars += [
                'store_name' => $this->config->getStoreName($storeId) ?: $store->getFrontendName(),
                'store_url' => $store->getBaseUrl(),
                'assistant_name' => $this->config->getAssistantName($storeId),
            ];
            $transport = $this->transportBuilder
                ->setTemplateIdentifier($templateId)
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars($vars)
                ->setFromByScope('general', $storeId)
                ->addTo($toEmail, $toName ?: $toEmail)
                ->getTransport();
            $transport->sendMessage();
            return true;
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('Email "%s" to %s failed: %s', $templateId, $toEmail, $e->getMessage()));
            return false;
        } finally {
            $this->inlineTranslation->resume();
        }
    }
}
