<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb\Queue;

use Magento\Framework\MessageQueue\PublisherInterface;
use WB\AiChatbot\Model\Logger;

/**
 * Enqueues knowledge base work for the wbAichatbotKbIndex consumer (MysqlMq "db" connection).
 * Message format: "<action>|<payload>" - "index|1,2,3" (document ids) or "resync|product|product.123".
 */
class Publisher
{
    public const TOPIC = 'wb.aichatbot.kb.index';

    /**
     * @var PublisherInterface
     */
    private $publisher;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(PublisherInterface $publisher, Logger $logger)
    {
        $this->publisher = $publisher;
        $this->logger = $logger;
    }

    /**
     * @param int[] $documentIds
     */
    public function publishIndex(array $documentIds): void
    {
        $documentIds = array_values(array_unique(array_map('intval', $documentIds)));
        if ($documentIds === []) {
            return;
        }
        foreach (array_chunk($documentIds, 100) as $batch) {
            $this->publish('index|' . implode(',', $batch));
        }
    }

    public function publishResync(string $sourceType, string $identifier): void
    {
        $this->publish('resync|' . $sourceType . '|' . $identifier);
    }

    /**
     * Full sync of one source type (empty = all) followed by indexing of everything pending.
     */
    public function publishSync(string $sourceType = ''): void
    {
        $this->publish('sync|' . $sourceType);
    }

    private function publish(string $message): void
    {
        try {
            $this->publisher->publish(self::TOPIC, $message);
        } catch (\Throwable $e) {
            $this->logger->error('KB queue publish failed: ' . $e->getMessage());
        }
    }
}
