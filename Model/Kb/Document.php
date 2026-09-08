<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb;

use Magento\Framework\Model\AbstractModel;

/**
 * One knowledge base document: a product, category, CMS page, store-info sheet, or an admin-written article.
 */
class Document extends AbstractModel
{
    public const SOURCE_STORE_INFO = 'store_info';
    public const SOURCE_PRODUCT = 'product';
    public const SOURCE_CATEGORY = 'category';
    public const SOURCE_CMS_PAGE = 'cms_page';
    public const SOURCE_MANUAL = 'manual';

    public const STATUS_PENDING = 'pending';   // synced, needs chunking/embedding
    public const STATUS_INDEXED = 'indexed';
    public const STATUS_DIRTY = 'dirty';       // source entity changed, needs re-sync
    public const STATUS_FAILED = 'failed';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\Document::class);
    }

    public function getDocumentId(): ?int
    {
        $id = $this->getData('document_id');
        return $id === null ? null : (int)$id;
    }

    public function getIdentifier(): string
    {
        return (string)$this->getData('identifier');
    }

    public function getSourceType(): string
    {
        return (string)$this->getData('source_type');
    }

    public function getStoreId(): int
    {
        return (int)$this->getData('store_id');
    }

    public function getCategoryId(): ?int
    {
        $id = $this->getData('category_id');
        return $id === null || $id === '' ? null : (int)$id;
    }

    public function getTitle(): string
    {
        return (string)$this->getData('title');
    }

    public function getBody(): string
    {
        return (string)$this->getData('body');
    }

    public function getUrl(): ?string
    {
        $url = $this->getData('url');
        return $url === null || $url === '' ? null : (string)$url;
    }

    public function getAttributes(): array
    {
        $raw = $this->getData('attributes');
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    public function getContentHash(): ?string
    {
        return $this->getData('content_hash');
    }

    public function getIsEnabled(): bool
    {
        return (bool)$this->getData('is_enabled');
    }

    public function getSyncStatus(): string
    {
        return (string)($this->getData('sync_status') ?: self::STATUS_PENDING);
    }

    public function isManual(): bool
    {
        return $this->getSourceType() === self::SOURCE_MANUAL;
    }
}
