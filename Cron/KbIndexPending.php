<?php
declare(strict_types=1);

namespace WB\AiChatbot\Cron;

use WB\AiChatbot\Model\Config;
use WB\AiChatbot\Model\Kb\Indexer;
use WB\AiChatbot\Model\Logger;

class KbIndexPending
{
    private const BATCH = 500;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Indexer
     */
    private $indexer;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(Config $config, Indexer $indexer, Logger $logger)
    {
        $this->config = $config;
        $this->indexer = $indexer;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }
        try {
            $stats = $this->indexer->indexPending(self::BATCH);
            if ($stats['total'] > 0) {
                $this->logger->info('KB catch-up index: ' . json_encode($stats));
            }
        } catch (\Throwable $e) {
            $this->logger->error('KB catch-up index failed: ' . $e->getMessage());
        }
    }
}
