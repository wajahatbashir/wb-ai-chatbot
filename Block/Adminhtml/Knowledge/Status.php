<?php
declare(strict_types=1);

namespace WB\AiChatbot\Block\Adminhtml\Knowledge;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use WB\AiChatbot\Model\Config;
use WB\AiChatbot\Model\Kb\Indexer;
use WB\AiChatbot\Model\Provider\Pool;

/**
 * Summary strip above the Knowledge Base grid.
 */
class Status extends Template
{
    /**
     * @var string
     */
    protected $_template = 'WB_AiChatbot::knowledge/status.phtml';

    /**
     * @var Indexer
     */
    private $indexer;

    /**
     * @var Pool
     */
    private $pool;

    /**
     * @var Config
     */
    private $config;

    public function __construct(Context $context, Indexer $indexer, Pool $pool, Config $config, array $data = [])
    {
        $this->indexer = $indexer;
        $this->pool = $pool;
        $this->config = $config;
        parent::__construct($context, $data);
    }

    public function getStatus(): array
    {
        return $this->indexer->getStatus();
    }

    public function isAiAvailable(): bool
    {
        return $this->pool->isAiAvailable();
    }

    public function isChatbotEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    public function getConfigUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit/section/wb_aichatbot');
    }

    public function getProvidersUrl(): string
    {
        return $this->getUrl('wb_aichatbot/credential/index');
    }
}
