<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb;

use Magento\Framework\App\ResourceConnection;
use WB\AiChatbot\Model\Logger;
use WB\AiChatbot\Model\Provider\NoProviderException;
use WB\AiChatbot\Model\Provider\ProviderException;

/**
 * Turns pending documents into chunks (+ embeddings when an embedding provider is usable). Without embeddings
 * the chunks are still written so keyword retrieval works - the chatbot never depends on an API key.
 */
class Indexer
{
    private const DOCS_PER_BATCH = 25;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var Chunker
     */
    private $chunker;

    /**
     * @var ChunkStorage
     */
    private $chunkStorage;

    /**
     * @var Embedder
     */
    private $embedder;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(
        ResourceConnection $resource,
        Chunker $chunker,
        ChunkStorage $chunkStorage,
        Embedder $embedder,
        Logger $logger
    ) {
        $this->resource = $resource;
        $this->chunker = $chunker;
        $this->chunkStorage = $chunkStorage;
        $this->embedder = $embedder;
        $this->logger = $logger;
    }

    /**
     * Indexes documents that are pending/dirty (or, with $reembed, every enabled document).
     *
     * @param callable|null $progress fn(int $done, int $total)
     * @return array{indexed:int, failed:int, embedded:int, total:int}
     */
    public function indexPending(int $limit = 0, bool $reembed = false, ?callable $progress = null): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('wb_aichatbot_kb_document'), ['document_id'])
            ->where('is_enabled = 1')
            ->order('document_id ASC');
        if (!$reembed) {
            $statuses = [Document::STATUS_PENDING, Document::STATUS_DIRTY, Document::STATUS_FAILED];
            $model = $this->embedder->getActiveModel();
            if ($model !== null) {
                // An embedding provider is usable now: also pick up documents indexed keyword-only (or with another
                // model) so a newly added API key upgrades the whole knowledge base on the next run.
                $chunkTable = $this->resource->getTableName('wb_aichatbot_kb_chunk');
                $select->where(
                    'sync_status IN (?) OR document_id IN (SELECT document_id FROM ' . $chunkTable
                    . ' WHERE embedding IS NULL OR embedding_model <> ' . $connection->quote($model) . ')',
                    $statuses
                );
            } else {
                $select->where('sync_status IN (?)', $statuses);
            }
        }
        if ($limit > 0) {
            $select->limit($limit);
        }
        $ids = array_map('intval', $connection->fetchCol($select));
        return $this->indexDocuments($ids, $progress);
    }

    /**
     * @param int[] $documentIds
     * @param callable|null $progress fn(int $done, int $total)
     * @return array{indexed:int, failed:int, embedded:int, total:int}
     */
    public function indexDocuments(array $documentIds, ?callable $progress = null): array
    {
        $stats = ['indexed' => 0, 'failed' => 0, 'embedded' => 0, 'total' => count($documentIds)];
        $embeddingsAvailable = $this->embedder->isAvailable();
        $done = 0;
        foreach (array_chunk($documentIds, self::DOCS_PER_BATCH) as $batchIds) {
            $documents = $this->loadDocuments($batchIds);
            $chunksByDoc = [];
            $texts = [];
            foreach ($documents as $documentId => $row) {
                $chunks = $this->chunker->chunk((string)$row['title'], (string)$row['body']);
                $chunksByDoc[$documentId] = $chunks;
                foreach ($chunks as $chunk) {
                    $texts[] = $chunk['text'];
                }
            }

            $vectors = [];
            $model = null;
            if ($embeddingsAvailable && $texts !== []) {
                try {
                    $vectors = $this->embedder->embed($texts);
                    $model = $this->embedder->getLastModel();
                } catch (NoProviderException $e) {
                    $embeddingsAvailable = false;
                    $this->logger->warning('KB indexing continues without embeddings: ' . $e->getMessage());
                } catch (ProviderException $e) {
                    $embeddingsAvailable = false;
                    $this->logger->warning('KB embedding failed, continuing keyword-only: ' . $e->getMessage());
                }
            }

            $offset = 0;
            foreach ($chunksByDoc as $documentId => $chunks) {
                try {
                    foreach ($chunks as $i => $chunk) {
                        $chunks[$i]['embedding'] = $vectors[$offset] ?? null;
                        $offset++;
                    }
                    $this->chunkStorage->replaceForDocument((int)$documentId, $chunks, $model);
                    $this->setStatus((int)$documentId, Document::STATUS_INDEXED);
                    $stats['indexed']++;
                    if ($vectors !== []) {
                        $stats['embedded']++;
                    }
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $this->setStatus((int)$documentId, Document::STATUS_FAILED);
                    $this->logger->error(sprintf('KB index document %d failed: %s', $documentId, $e->getMessage()));
                }
                $done++;
                if ($progress) {
                    $progress($done, $stats['total']);
                }
            }
        }
        return $stats;
    }

    /**
     * @return array{documents:int, indexed:int, pending:int, failed:int, chunks:int, embedded:int, embedding_model:?string}
     */
    public function getStatus(): array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('wb_aichatbot_kb_document');
        $counts = [];
        foreach ($connection->fetchAll($connection->select()->from($table, ['sync_status', 'cnt' => new \Zend_Db_Expr('COUNT(*)')])->group('sync_status')) as $row) {
            $counts[(string)$row['sync_status']] = (int)$row['cnt'];
        }
        $totals = $this->chunkStorage->getTotals();
        return [
            'documents' => array_sum($counts),
            'indexed' => $counts[Document::STATUS_INDEXED] ?? 0,
            'pending' => ($counts[Document::STATUS_PENDING] ?? 0) + ($counts[Document::STATUS_DIRTY] ?? 0),
            'failed' => $counts[Document::STATUS_FAILED] ?? 0,
            'chunks' => $totals['chunks'],
            'embedded' => $totals['embedded'],
            'embedding_model' => $this->embedder->getActiveModel(),
        ];
    }

    /**
     * @param int[] $ids
     * @return array<int, array{title: string, body: string}>
     */
    private function loadDocuments(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('wb_aichatbot_kb_document'), ['document_id', 'title', 'body'])
            ->where('document_id IN (?)', $ids)
            ->where('is_enabled = 1');
        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            $rows[(int)$row['document_id']] = $row;
        }
        return $rows;
    }

    private function setStatus(int $documentId, string $status): void
    {
        $this->resource->getConnection()->update(
            $this->resource->getTableName('wb_aichatbot_kb_document'),
            ['sync_status' => $status],
            ['document_id = ?' => $documentId]
        );
    }
}
