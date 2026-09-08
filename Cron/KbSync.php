<?php
declare(strict_types=1);

namespace WB\AiChatbot\Cron;

use WB\AiChatbot\Model\Config;
use WB\AiChatbot\Model\Kb\Indexer;
use WB\AiChatbot\Model\Kb\Synchronizer;
use WB\AiChatbot\Model\Logger;

class KbSync
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Synchronizer
     */
    private $synchronizer;

    /**
     * @var Indexer
     */
    private $indexer;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(Config $config, Synchronizer $synchronizer, Indexer $indexer, Logger $logger)
    {
        $this->config = $config;
        $this->synchronizer = $synchronizer;
        $this->indexer = $indexer;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }
        try {
            $stats = $this->synchronizer->sync();
            $index = $this->indexer->indexPending();
            $this->logger->info('KB nightly sync: ' . json_encode($stats) . ' index: ' . json_encode($index));
        } catch (\Throwable $e) {
            $this->logger->error('KB nightly sync failed: ' . $e->getMessage());
        }
    }
}
