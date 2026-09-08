<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use WB\AiChatbot\Model\Kb\Sync\ProviderPool;
use WB\AiChatbot\Model\Kb\Sync\SyncDocument;
use WB\AiChatbot\Model\Kb\Sync\SyncProviderInterface;
use WB\AiChatbot\Model\Logger;

/**
 * Pulls documents from the sync providers into wb_aichatbot_kb_document. Unchanged content (same hash) is left
 * alone so its chunks and embeddings survive; changed or new documents are written as "pending" for the Indexer.
 */
class Synchronizer
{
    /**
     * @var ProviderPool
     */
    private $providerPool;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var ResourceModel\Document
     */
    private $documentResource;

    /**
     * @var ResourceModel\Category
     */
    private $categoryResource;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(
        ProviderPool $providerPool,
        StoreManagerInterface $storeManager,
        ResourceConnection $resource,
        ResourceModel\Document $documentResource,
        ResourceModel\Category $categoryResource,
        Logger $logger
    ) {
        $this->providerPool = $providerPool;
        $this->storeManager = $storeManager;
        $this->resource = $resource;
        $this->documentResource = $documentResource;
        $this->categoryResource = $categoryResource;
        $this->logger = $logger;
    }

    /**
     * @param string[] $types provider codes, empty = all
     * @param int[] $storeIds empty = all active store views
     * @param callable|null $progress fn(string $type, int $storeId, SyncDocument $doc, string $action)
     * @return array<string, array<int, array{created:int, updated:int, unchanged:int, deleted:int}>> stats[type][store]
     */
    public function sync(array $types = [], array $storeIds = [], bool $dryRun = false, int $limit = 0, ?callable $progress = null): array
    {
        $stats = [];
        foreach ($this->resolveProviders($types) as $provider) {
            foreach ($this->resolveStores($storeIds) as $store) {
                $stats[$provider->getCode()][(int)$store->getId()] = $this->syncProvider($provider, $store, $dryRun, $limit, $progress);
            }
        }
        return $stats;
    }

    /**
     * Re-syncs one document (all stores) after its source entity changed. Returns the affected document ids.
     *
     * @return int[]
     */
    public function syncOne(string $type, string $identifier): array
    {
        if (!$this->providerPool->has($type)) {
            return [];
        }
        $provider = $this->providerPool->get($type);
        $documentIds = [];
        foreach ($this->resolveStores([]) as $store) {
            $existing = $this->findExisting($provider->getCode(), (int)$store->getId(), [$identifier]);
            try {
                $document = $provider->fetchOne($store, $identifier);
            } catch (\Throwable $e) {
                $this->logger->error(sprintf('KB syncOne %s/%s store %d failed: %s', $type, $identifier, $store->getId(), $e->getMessage()));
                continue;
            }
            if ($document === null) {
                if (isset($existing[$identifier])) {
                    $this->resource->getConnection()->delete(
                        $this->resource->getTableName('wb_aichatbot_kb_document'),
                        ['document_id = ?' => (int)$existing[$identifier]['document_id']]
                    );
                }
                continue;
            }
            $categoryId = $this->categoryResource->ensure($provider->getCode(), $provider->getCategoryName());
            $documentIds[] = $this->upsert($provider, $store, $document, $existing[$identifier] ?? null, $categoryId, false)['document_id'];
        }
        return array_values(array_filter($documentIds));
    }

    /**
     * @return array{created:int, updated:int, unchanged:int, deleted:int}
     */
    private function syncProvider(SyncProviderInterface $provider, StoreInterface $store, bool $dryRun, int $limit, ?callable $progress): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'deleted' => 0];
        $storeId = (int)$store->getId();
        $categoryId = $dryRun ? 0 : $this->categoryResource->ensure($provider->getCode(), $provider->getCategoryName());
        $keepIds = [];
        $count = 0;
        $batch = [];

        $flush = function () use (&$batch, &$stats, &$keepIds, $provider, $store, $categoryId, $dryRun, $progress) {
            if ($batch === []) {
                return;
            }
            $existing = $this->findExisting($provider->getCode(), (int)$store->getId(), array_keys($batch));
            foreach ($batch as $identifier => $document) {
                $result = $this->upsert($provider, $store, $document, $existing[$identifier] ?? null, $categoryId, $dryRun);
                $stats[$result['action']]++;
                if ($result['document_id']) {
                    $keepIds[] = $result['document_id'];
                }
                if ($progress) {
                    $progress($provider->getCode(), (int)$store->getId(), $document, $result['action']);
                }
            }
            $batch = [];
        };

        try {
            foreach ($provider->fetch($store) as $document) {
                $batch[$document->getIdentifier()] = $document;
                $count++;
                if (count($batch) >= 100) {
                    $flush();
                }
                if ($limit > 0 && $count >= $limit) {
                    break;
                }
            }
            $flush();
        } catch (\Throwable $e) {
            $flush();
            $this->logger->error(sprintf('KB sync %s store %d aborted: %s', $provider->getCode(), $storeId, $e->getMessage()));
            throw $e;
        }

        // Only a complete run may remove documents whose source disappeared.
        if (!$dryRun && $limit === 0) {
            $stats['deleted'] = $this->documentResource->deleteStale($provider->getCode(), $storeId, $keepIds);
        }
        return $stats;
    }

    /**
     * @return array{action: string, document_id: int|null}
     */
    private function upsert(SyncProviderInterface $provider, StoreInterface $store, SyncDocument $document, ?array $existing, int $categoryId, bool $dryRun): array
    {
        $hash = $document->getContentHash();
        if ($existing && $existing['content_hash'] === $hash && $existing['sync_status'] === Document::STATUS_INDEXED) {
            return ['action' => 'unchanged', 'document_id' => (int)$existing['document_id']];
        }
        $action = $existing ? 'updated' : 'created';
        if ($dryRun) {
            return ['action' => $action, 'document_id' => $existing ? (int)$existing['document_id'] : null];
        }
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('wb_aichatbot_kb_document');
        $row = [
            'identifier' => $document->getIdentifier(),
            'source_type' => $provider->getCode(),
            'store_id' => (int)$store->getId(),
            'category_id' => $categoryId ?: null,
            'title' => mb_substr($document->getTitle(), 0, 255),
            'body' => $document->getBody(),
            'url' => $document->getUrl() !== null ? mb_substr($document->getUrl(), 0, 512) : null,
            'attributes' => json_encode($document->getAttributes(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'content_hash' => $hash,
            'sync_status' => Document::STATUS_PENDING,
        ];
        if ($existing) {
            $connection->update($table, $row, ['document_id = ?' => (int)$existing['document_id']]);
            return ['action' => 'updated', 'document_id' => (int)$existing['document_id']];
        }
        $connection->insert($table, $row);
        return ['action' => 'created', 'document_id' => (int)$connection->lastInsertId($table)];
    }

    /**
     * @param string[] $identifiers
     * @return array<string, array{document_id: int, content_hash: string|null, sync_status: string}>
     */
    private function findExisting(string $sourceType, int $storeId, array $identifiers): array
    {
        if ($identifiers === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('wb_aichatbot_kb_document'), ['identifier', 'document_id', 'content_hash', 'sync_status'])
            ->where('source_type = ?', $sourceType)
            ->where('store_id = ?', $storeId)
            ->where('identifier IN (?)', $identifiers);
        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            $rows[(string)$row['identifier']] = $row;
        }
        return $rows;
    }

    /**
     * @param string[] $types
     * @return SyncProviderInterface[]
     */
    private function resolveProviders(array $types): array
    {
        if ($types === []) {
            return array_values($this->providerPool->getAll());
        }
        $providers = [];
        foreach ($types as $type) {
            $providers[] = $this->providerPool->get(trim($type));
        }
        return $providers;
    }

    /**
     * @param int[] $storeIds
     * @return StoreInterface[]
     */
    private function resolveStores(array $storeIds): array
    {
        $stores = [];
        foreach ($this->storeManager->getStores() as $store) {
            if (!$store->getIsActive()) {
                continue;
            }
            if ($storeIds !== [] && !in_array((int)$store->getId(), array_map('intval', $storeIds), true)) {
                continue;
            }
            $stores[] = $store;
        }
        return $stores;
    }
}
