<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb\Sync\Provider;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation;
use WB\AiChatbot\Model\Kb\Sync\SyncDocument;
use WB\AiChatbot\Model\Kb\Sync\SyncProviderInterface;
use WB\AiChatbot\Model\Kb\Sync\TextCleaner;

/**
 * Active categories under the store's root: breadcrumb path, description, URL and product count.
 */
class Category implements SyncProviderInterface
{
    public const CODE = 'category';

    /**
     * @var CategoryCollectionFactory
     */
    private $collectionFactory;

    /**
     * @var Emulation
     */
    private $emulation;

    /**
     * @var TextCleaner
     */
    private $textCleaner;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var array<int, string> id => name for the current store (used for breadcrumb paths)
     */
    private $names = [];

    public function __construct(
        CategoryCollectionFactory $collectionFactory,
        Emulation $emulation,
        TextCleaner $textCleaner,
        ResourceConnection $resource
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->emulation = $emulation;
        $this->textCleaner = $textCleaner;
        $this->resource = $resource;
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getTitle(): string
    {
        return 'Categories';
    }

    public function getCategoryName(): string
    {
        return 'Catalog Categories';
    }

    public function fetch(StoreInterface $store): \Generator
    {
        $this->emulation->startEnvironmentEmulation((int)$store->getId(), Area::AREA_FRONTEND, true);
        try {
            $collection = $this->createCollection($store);
            $this->names = [];
            foreach ($collection as $category) {
                $this->names[(int)$category->getId()] = (string)$category->getName();
            }
            $counts = $this->productCounts($store);
            foreach ($collection as $category) {
                yield $this->build($store, $category, $counts[(int)$category->getId()] ?? 0);
            }
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }

    public function fetchOne(StoreInterface $store, string $identifier): ?SyncDocument
    {
        $categoryId = (int)substr($identifier, strlen(self::CODE) + 1);
        if (!$categoryId) {
            return null;
        }
        $this->emulation->startEnvironmentEmulation((int)$store->getId(), Area::AREA_FRONTEND, true);
        try {
            $collection = $this->createCollection($store);
            $this->names = [];
            foreach ($collection as $category) {
                $this->names[(int)$category->getId()] = (string)$category->getName();
            }
            $category = $collection->getItemById($categoryId);
            if (!$category) {
                return null;
            }
            $counts = $this->productCounts($store);
            return $this->build($store, $category, $counts[$categoryId] ?? 0);
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }

    private function createCollection(StoreInterface $store): \Magento\Catalog\Model\ResourceModel\Category\Collection
    {
        $rootId = (int)$store->getRootCategoryId();
        return $this->collectionFactory->create()
            ->setStoreId((int)$store->getId())
            ->addAttributeToSelect(['name', 'description', 'url_key', 'url_path', 'meta_description', 'include_in_menu'])
            ->addAttributeToFilter('is_active', 1)
            ->addFieldToFilter('path', ['like' => '1/' . $rootId . '/%'])
            ->addUrlRewriteToResult()
            ->setOrder('path', 'ASC');
    }

    private function build(StoreInterface $store, \Magento\Catalog\Model\Category $category, int $productCount): SyncDocument
    {
        $pathIds = array_map('intval', explode('/', (string)$category->getPath()));
        $crumbs = [];
        foreach ($pathIds as $id) {
            if (isset($this->names[$id])) {
                $crumbs[] = $this->names[$id];
            }
        }
        $path = implode(' > ', $crumbs);
        $lines = [];
        $lines[] = 'Category: ' . $path;
        $description = $this->textCleaner->clean((string)$category->getDescription());
        if ($description !== '') {
            $lines[] = $description;
        }
        $meta = trim((string)$category->getMetaDescription());
        if ($meta !== '' && $meta !== $description) {
            $lines[] = $meta;
        }
        $lines[] = sprintf('This category lists %d product(s).', $productCount);
        $url = (string)$category->getUrl();
        $lines[] = 'Browse it at: ' . $url;

        return new SyncDocument(
            self::CODE . '.' . $category->getId(),
            (string)$category->getName(),
            implode("\n", $lines),
            $url,
            [
                'category_id' => (int)$category->getId(),
                'path' => $path,
                'product_count' => $productCount,
                'in_menu' => (bool)$category->getIncludeInMenu(),
            ]
        );
    }

    /**
     * Visible-product count per category from the category-product index (what the storefront shows).
     *
     * @return array<int, int>
     */
    private function productCounts(StoreInterface $store): array
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('catalog_category_product_index_store' . (int)$store->getId());
        if (!$connection->isTableExists($table)) {
            $table = $this->resource->getTableName('catalog_category_product_index');
        }
        $select = $connection->select()
            ->from($table, ['category_id', 'cnt' => new \Zend_Db_Expr('COUNT(DISTINCT product_id)')])
            ->where('store_id = ?', (int)$store->getId())
            ->where('visibility IN (?)', [2, 3, 4])
            ->group('category_id');
        $counts = [];
        foreach ($connection->fetchAll($select) as $row) {
            $counts[(int)$row['category_id']] = (int)$row['cnt'];
        }
        return $counts;
    }
}
