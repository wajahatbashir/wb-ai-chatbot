<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb\Sync;

use Magento\Store\Api\Data\StoreInterface;

/**
 * A knowledge base content source. Third-party modules add their own by implementing this and registering it in
 * di.xml under WB\AiChatbot\Model\Kb\Sync\ProviderPool "providers". Yield documents - never build the full list.
 */
interface SyncProviderInterface
{
    /**
     * Machine name, also the documents' source_type (e.g. "product", "acme_recipe").
     */
    public function getCode(): string;

    public function getTitle(): string;

    /**
     * Knowledge base category the documents belong to (created automatically).
     */
    public function getCategoryName(): string;

    /**
     * @return \Generator<SyncDocument>
     */
    public function fetch(StoreInterface $store): \Generator;

    /**
     * Re-fetches one document by its identifier after the source entity changed. Null = it no longer exists.
     */
    public function fetchOne(StoreInterface $store, string $identifier): ?SyncDocument;
}
