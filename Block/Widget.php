<?php
declare(strict_types=1);

namespace WB\AiChatbot\Block;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use WB\AiChatbot\Model\Config;

/**
 * Renders the widget host + static configuration only (no visitor data), so pages stay full-page-cacheable.
 * Visitor/customer/conversation data is fetched by the widget from /aichatbot/chat/bootstrap.
 */
class Widget extends Template
{
    /**
     * @var string
     */
    protected $_template = 'WB_AiChatbot::widget.phtml';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ResourceConnection
     */
    private $resource;

    public function __construct(Context $context, Config $config, ResourceConnection $resource, array $data = [])
    {
        $this->config = $config;
        $this->resource = $resource;
        parent::__construct($context, $data);
    }

    public function isVisible(): bool
    {
        $storeId = (int)$this->_storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId)) {
            return false;
        }
        $action = (string)$this->getRequest()->getFullActionName();
        foreach ($this->config->getExcludedPages($storeId) as $rule) {
            if ($rule === $action) {
                return false;
            }
        }
        if ($this->config->isHideUntilSynced($storeId) && !$this->hasIndexedDocuments()) {
            return false;
        }
        return true;
    }

    public function getConfigJson(): string
    {
        $store = $this->_storeManager->getStore();
        $storeId = (int)$store->getId();
        $avatar = trim($this->config->getAvatar($storeId));
        $urlPatterns = [];
        foreach ($this->config->getExcludedPages($storeId) as $rule) {
            if ($rule !== '' && ($rule[0] === '/' || strpos($rule, '*') !== false)) {
                $urlPatterns[] = $rule;
            }
        }
        $config = [
            'urls' => [
                'bootstrap' => $this->getUrl('aichatbot/chat/bootstrap'),
                'send' => $this->getUrl('aichatbot/chat/send'),
                'feedback' => $this->getUrl('aichatbot/chat/feedback'),
                'rate' => $this->getUrl('aichatbot/chat/rate'),
                'reset' => $this->getUrl('aichatbot/chat/reset'),
                'addToCart' => $this->getUrl('checkout/cart/add'),
                'cart' => $this->getUrl('checkout/cart'),
            ],
            'assistantName' => $this->config->getAssistantName($storeId),
            'welcomeMessage' => $this->config->getWelcomeMessage($storeId),
            'defaultQuestions' => $this->config->getDefaultQuestions($storeId),
            'position' => $this->config->getWidgetPosition($storeId),
            'primaryColor' => $this->config->getPrimaryColor($storeId) ?: '#1b1f1d',
            'launcherTitle' => $this->config->getLauncherTitle($storeId),
            'windowTitle' => $this->config->getWindowTitle($storeId) ?: $this->config->getAssistantName($storeId),
            'windowSubtitle' => $this->config->getWindowSubtitle($storeId),
            'avatar' => $avatar !== '' ? $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'wb_aichatbot/' . ltrim($avatar, '/') : null,
            'invitationMessage' => $this->config->getInvitationMessage($storeId),
            'invitationDelay' => $this->config->getInvitationDelay($storeId),
            'bottomOffsetMobile' => $this->config->getBottomOffsetMobile($storeId),
            'sound' => $this->config->isSoundEnabled($storeId),
            'allowAttachments' => $this->config->isAttachmentsAllowed($storeId),
            'allowHumanHandoff' => $this->config->isHumanHandoffAllowed($storeId),
            'linkTarget' => $this->config->getLinkTarget($storeId) ?: '_self',
            'excludedUrlPatterns' => $urlPatterns,
            'customCss' => $this->config->getCustomCss($storeId),
            'maxMessageLength' => $this->config->getMaxMessageLength(),
            'storeName' => $this->config->getStoreName($storeId) ?: $store->getFrontendName(),
        ];
        return (string)json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    private function hasIndexedDocuments(): bool
    {
        $connection = $this->resource->getConnection();
        return (bool)$connection->fetchOne(
            $connection->select()->from($this->resource->getTableName('wb_aichatbot_kb_document'), 'document_id')
                ->where('sync_status = ?', 'indexed')->limit(1)
        );
    }

    /**
     * Cache the static config per store for an hour; it contains no visitor data.
     */
    public function getCacheLifetime()
    {
        return 3600;
    }

    public function getCacheKeyInfo()
    {
        $info = parent::getCacheKeyInfo();
        $info[] = 'wb_aichatbot_widget';
        $info[] = (string)$this->_storeManager->getStore()->getId();
        $info[] = (string)$this->getRequest()->getFullActionName();
        return $info;
    }

    protected function getCacheTags()
    {
        return array_merge(parent::getCacheTags(), ['wb_aichatbot_widget', \Magento\Framework\App\Config::CACHE_TAG]);
    }
}
