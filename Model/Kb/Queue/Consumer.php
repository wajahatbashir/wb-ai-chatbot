<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb\Queue;

use WB\AiChatbot\Model\Kb\Indexer;
use WB\AiChatbot\Model\Kb\Synchronizer;
use WB\AiChatbot\Model\Logger;

class Consumer
{
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

    public function __construct(Synchronizer $synchronizer, Indexer $indexer, Logger $logger)
    {
        $this->synchronizer = $synchronizer;
        $this->indexer = $indexer;
        $this->logger = $logger;
    }

    public function process(string $message): void
    {
        try {
            $parts = explode('|', $message, 3);
            $action = $parts[0] ?? '';
            if ($action === 'index') {
                $ids = array_filter(array_map('intval', explode(',', $parts[1] ?? '')));
                $this->indexer->indexDocuments(array_values($ids));
            } elseif ($action === 'resync' && isset($parts[1], $parts[2])) {
                $documentIds = $this->synchronizer->syncOne($parts[1], $parts[2]);
                if ($documentIds !== []) {
                    $this->indexer->indexDocuments($documentIds);
                }
            } elseif ($action === 'sync') {
                $types = isset($parts[1]) && $parts[1] !== '' ? [$parts[1]] : [];
                $stats = $this->synchronizer->sync($types);
                $index = $this->indexer->indexPending();
                $this->logger->info('KB queued sync: ' . json_encode($stats) . ' index: ' . json_encode($index));
            } else {
                $this->logger->warning('KB queue: unknown message "' . $message . '"');
            }
        } catch (\Throwable $e) {
            // Never rethrow: a poison message must not stall the consumer. The nightly sync repairs anything missed.
            $this->logger->error('KB queue message "' . $message . '" failed: ' . $e->getMessage());
        }
    }
}
