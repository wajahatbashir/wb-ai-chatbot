<?php
declare(strict_types=1);

namespace WB\AiChatbot\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\System\Store as SystemStore;
use WB\AiChatbot\Model\Config;
use WB\AiChatbot\Model\Provider\Pool;

class Preview extends Template
{
    /**
     * @var string
     */
    protected $_template = 'WB_AiChatbot::preview.phtml';

    /**
     * @var SystemStore
     */
    private $systemStore;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Pool
     */
    private $pool;

    public function __construct(Context $context, SystemStore $systemStore, Config $config, Pool $pool, array $data = [])
    {
        $this->systemStore = $systemStore;
        $this->config = $config;
        $this->pool = $pool;
        parent::__construct($context, $data);
    }

    public function getSendUrl(): string
    {
        return $this->getUrl('wb_aichatbot/preview/send');
    }

    public function getStoreOptions(): array
    {
        return $this->systemStore->getStoreValuesForForm(false, false);
    }

    public function getAssistantName(): string
    {
        return $this->config->getAssistantName();
    }

    public function getWelcomeMessage(): string
    {
        return $this->config->getWelcomeMessage();
    }

    public function getDefaultQuestions(): array
    {
        return $this->config->getDefaultQuestions();
    }

    public function isAiAvailable(): bool
    {
        return $this->pool->isAiAvailable();
    }

    public function getEngineMode(): string
    {
        return $this->config->getMode();
    }

    public function isChatbotEnabled(): bool
    {
        return $this->config->isEnabled();
    }
}
