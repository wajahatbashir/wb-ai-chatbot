<?php
declare(strict_types=1);

namespace WB\AiChatbot\Cron;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use WB\AiChatbot\Model\Config;
use WB\AiChatbot\Model\Logger;

/**
 * Nightly housekeeping: conversations (and, by FK cascade, their messages) older than the configured retention,
 * unanswered questions of the same age, spent/expired verification codes, stale rate-limit counters.
 */
class Retention
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(ResourceConnection $resource, DateTime $dateTime, Config $config, Logger $logger)
    {
        $this->resource = $resource;
        $this->dateTime = $dateTime;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function execute(): void
    {
        $connection = $this->resource->getConnection();
        $now = $this->dateTime->gmtTimestamp();
        $deleted = [];
        try {
            $days = $this->config->getRetentionDays();
            if ($days > 0) {
                $cutoff = $this->dateTime->gmtDate('Y-m-d H:i:s', $now - $days * 86400);
                $deleted['conversations'] = $connection->delete($this->resource->getTableName('wb_aichatbot_conversation'), ['last_message_at < ?' => $cutoff]);
                $deleted['unanswered'] = $connection->delete($this->resource->getTableName('wb_aichatbot_unanswered'), ['created_at < ?' => $cutoff, 'status <> ?' => 'new']);
            }
            $deleted['codes'] = $connection->delete(
                $this->resource->getTableName('wb_aichatbot_verification_code'),
                ['expires_at < ?' => $this->dateTime->gmtDate('Y-m-d H:i:s', $now - 86400)]
            );
            $deleted['rate_limits'] = $connection->delete(
                $this->resource->getTableName('wb_aichatbot_rate_limit'),
                ['window_start < ?' => $this->dateTime->gmtDate('Y-m-d H:i:s', $now - 2 * 86400)]
            );
            if (array_sum($deleted) > 0) {
                $this->logger->info('Retention cleanup: ' . json_encode($deleted));
            }
        } catch (\Throwable $e) {
            $this->logger->error('Retention cleanup failed: ' . $e->getMessage());
        }
    }
}
