<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb;

use Magento\Framework\App\ResourceConnection;

/**
 * Admin "re-sync & index" for a handful of documents, inline: synced documents are re-fetched from their source
 * first (so a changed product shows up immediately), manual articles are just re-chunked/re-embedded.
 */
class DocumentReindexer
{
    private const INLINE_LIMIT = 200;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var Synchronizer
     */
    private $synchronizer;

    /**
     * @var Indexer
     */
    private $indexer;

    /**
     * @var Queue\Publisher
     */
    private $publisher;

    public function __construct(ResourceConnection $resource, Synchronizer $synchronizer, Indexer $indexer, Queue\Publisher $publisher)
    {
        $this->resource = $resource;
        $this->synchronizer = $synchronizer;
        $this->indexer = $indexer;
        $this->publisher = $publisher;
    }

    /**
     * @param int[] $documentIds
     * @return array{queued:int, indexed:int, embedded:int, failed:int, removed:int}
     */
    public function reindex(array $documentIds): array
    {
        $documentIds = array_values(array_unique(array_filter(array_map('intval', $documentIds))));
        $result = ['queued' => 0, 'indexed' => 0, 'embedded' => 0, 'failed' => 0, 'removed' => 0];
        if ($documentIds === []) {
            return $result;
        }
        if (count($documentIds) > self::INLINE_LIMIT) {
            $this->markPending($documentIds);
            $this->publisher->publishIndex($documentIds);
            $result['queued'] = count($documentIds);
            return $result;
        }

        $connection = $this->resource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->resource->getTableName('wb_aichatbot_kb_document'), ['document_id', 'source_type', 'identifier'])
                ->where('document_id IN (?)', $documentIds)
        );
        $toIndex = [];
        $seen = [];
        foreach ($rows as $row) {
            if ($row['source_type'] === Document::SOURCE_MANUAL) {
                $toIndex[] = (int)$row['document_id'];
                continue;
            }
            $key = $row['source_type'] . '|' . $row['identifier'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $refreshed = $this->synchronizer->syncOne((string)$row['source_type'], (string)$row['identifier']);
            if ($refreshed === []) {
                $result['removed']++;
            }
            foreach ($refreshed as $id) {
                $toIndex[] = $id;
            }
        }
        if ($toIndex !== []) {
            $stats = $this->indexer->indexDocuments(array_values(array_unique($toIndex)));
            $result['indexed'] = $stats['indexed'];
            $result['embedded'] = $stats['embedded'];
            $result['failed'] = $stats['failed'];
        }
        return $result;
    }

    public function describe(array $result): \Magento\Framework\Phrase
    {
        if ($result['queued'] > 0) {
            return __('%1 document(s) were queued for re-indexing; the wbAichatbotKbIndex consumer will process them shortly.', $result['queued']);
        }
        $parts = [__('%1 document(s) re-indexed', $result['indexed'])];
        if ($result['embedded'] > 0) {
            $parts[] = __('%1 with embeddings', $result['embedded']);
        } else {
            $parts[] = __('keyword search only - no embedding provider is available');
        }
        if ($result['removed'] > 0) {
            $parts[] = __('%1 removed because the source no longer exists or is hidden', $result['removed']);
        }
        if ($result['failed'] > 0) {
            $parts[] = __('%1 failed (see var/log/wb_aichatbot.log)', $result['failed']);
        }
        return __(implode(', ', array_map('strval', $parts)) . '.');
    }

    /**
     * @param int[] $documentIds
     */
    private function markPending(array $documentIds): void
    {
        $this->resource->getConnection()->update(
            $this->resource->getTableName('wb_aichatbot_kb_document'),
            ['sync_status' => Document::STATUS_PENDING],
            ['document_id IN (?)' => $documentIds]
        );
    }
}
