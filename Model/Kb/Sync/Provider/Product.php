<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb\Sync\Provider;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation;
use WB\AiChatbot\Model\Kb\Sync\SyncDocument;
use WB\AiChatbot\Model\Kb\Sync\SyncProviderInterface;
use WB\AiChatbot\Model\Kb\Sync\TextCleaner;

/**
 * Visible, enabled products of a store view. Each document is a readable product sheet (name, SKU, price in the
 * store's currency, availability, categories, variants, description, measurements) plus structured attributes
 * for product cards. Runs under store emulation so per-store-view prices and URLs come out right.
 *
 * Availability mirrors the storefront's tier rules (WB_StockSort) without depending on that module:
 *   pre_order_status = 1 -> Pre-order; = 2 and not salable -> Pre-order; salable -> In stock; else Out of stock.
 */
class Product implements SyncProviderInterface
{
    public const CODE = 'product';

    public const AVAILABILITY_IN_STOCK = 'in_stock';
    public const AVAILABILITY_PRE_ORDER = 'pre_order';
    public const AVAILABILITY_OUT_OF_STOCK = 'out_of_stock';

    private const PAGE_SIZE = 200;

    private const ATTRIBUTES = [
        'name', 'sku', 'description', 'short_description', 'color', 'size', 'price', 'special_price',
        'special_from_date', 'special_to_date', 'ready_to_ship', 'length', 'width', 'weight', 'meta_description',
        'url_key', 'small_image', 'image', 'pre_order_status', 'status', 'visibility', 'material', 'fabric',
        'work', 'occasion', 'brand', 'manufacturer',
    ];

    /**
     * Attributes rendered as "Label: value" lines when present (code => label).
     */
    private const DETAIL_ATTRIBUTES = [
        'material' => 'Material', 'fabric' => 'Fabric', 'work' => 'Work', 'occasion' => 'Occasion',
        'brand' => 'Brand', 'manufacturer' => 'Manufacturer', 'length' => 'Length', 'width' => 'Width',
        'weight' => 'Weight',
    ];

    /**
     * @var ProductCollectionFactory
     */
    private $productCollectionFactory;

    /**
     * @var CategoryCollectionFactory
     */
    private $categoryCollectionFactory;

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
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @var array<int, string>
     */
    private $categoryNames = [];

    /**
     * @var array<int, int> product id => stock status (cataloginventory_stock_status, website 0)
     */
    private $stockStatus = [];

    public function __construct(
        ProductCollectionFactory $productCollectionFactory,
        CategoryCollectionFactory $categoryCollectionFactory,
        Emulation $emulation,
        TextCleaner $textCleaner,
        ResourceConnection $resource,
        PriceCurrencyInterface $priceCurrency
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->emulation = $emulation;
        $this->textCleaner = $textCleaner;
        $this->resource = $resource;
        $this->priceCurrency = $priceCurrency;
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getTitle(): string
    {
        return 'Products';
    }

    public function getCategoryName(): string
    {
        return 'Products';
    }

    public function fetch(StoreInterface $store): \Generator
    {
        $this->emulation->startEnvironmentEmulation((int)$store->getId(), Area::AREA_FRONTEND, true);
        try {
            $this->loadCategoryNames($store);
            $page = 1;
            do {
                $collection = $this->createCollection($store)->setPageSize(self::PAGE_SIZE)->setCurPage($page);
                $ids = $collection->getAllIds(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);
                if ($ids === []) {
                    break;
                }
                $this->loadStockStatus($ids);
                $count = 0;
                foreach ($collection as $product) {
                    $count++;
                    yield $this->build($store, $product);
                }
                $collection->clear();
                $page++;
            } while ($count === self::PAGE_SIZE);
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }

    public function fetchOne(StoreInterface $store, string $identifier): ?SyncDocument
    {
        $productId = (int)substr($identifier, strlen(self::CODE) + 1);
        if (!$productId) {
            return null;
        }
        $this->emulation->startEnvironmentEmulation((int)$store->getId(), Area::AREA_FRONTEND, true);
        try {
            $this->loadCategoryNames($store);
            $collection = $this->createCollection($store)->addIdFilter($productId);
            $product = $collection->getFirstItem();
            if (!$product->getId()) {
                return null;
            }
            $this->loadStockStatus([$productId]);
            return $this->build($store, $product);
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }

    private function createCollection(StoreInterface $store): \Magento\Catalog\Model\ResourceModel\Product\Collection
    {
        $collection = $this->productCollectionFactory->create()
            ->setStore($store)
            ->addStoreFilter($store)
            ->addWebsiteFilter([(int)$store->getWebsiteId()])
            ->addAttributeToSelect(self::ATTRIBUTES)
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['in' => [
                Visibility::VISIBILITY_IN_CATALOG, Visibility::VISIBILITY_IN_SEARCH, Visibility::VISIBILITY_BOTH,
            ]])
            ->addCategoryIds()
            ->addUrlRewrite()
            ->setOrder('entity_id', 'ASC');
        $collection->addPriceData(null, (int)$store->getWebsiteId());
        return $collection;
    }

    private function build(StoreInterface $store, \Magento\Catalog\Model\Product $product): SyncDocument
    {
        $currency = (string)$store->getCurrentCurrencyCode();
        $prices = $this->prices($product);
        $availability = $this->availability($product);
        $categories = $this->categoryPaths($product);
        $variants = $product->getTypeId() === 'configurable' ? $this->variants($product) : [];
        $url = (string)$product->getProductUrl();

        $lines = [];
        $lines[] = 'Product: ' . $product->getName();
        $lines[] = 'SKU: ' . $product->getSku();
        $lines[] = 'Price: ' . $this->formatPrice($prices['final'], $currency)
            . ($prices['regular'] > $prices['final']
                ? ' (regular price ' . $this->formatPrice($prices['regular'], $currency) . ', on sale)'
                : '');
        $lines[] = 'Availability: ' . self::availabilityLabel($availability);
        if ($categories !== []) {
            $lines[] = 'Categories: ' . implode('; ', $categories);
        }
        $color = $this->optionText($product, 'color');
        $size = $this->optionText($product, 'size');
        if ($variants !== []) {
            $colors = array_values(array_unique(array_filter(array_column($variants, 'color'))));
            $sizes = array_values(array_unique(array_filter(array_column($variants, 'size'))));
            if ($colors !== []) {
                $lines[] = 'Available colors: ' . implode(', ', $colors);
            }
            if ($sizes !== []) {
                $lines[] = 'Available sizes: ' . implode(', ', $sizes);
            }
            $variantLines = [];
            foreach ($variants as $variant) {
                $label = trim(implode(' / ', array_filter([$variant['color'], $variant['size']])));
                $variantLines[] = '- ' . ($label !== '' ? $label : $variant['sku']) . ': '
                    . $this->formatPrice($variant['final_price'], $currency) . ', ' . self::availabilityLabel($variant['availability']);
            }
            $lines[] = "Variants:\n" . implode("\n", $variantLines);
        } else {
            if ($color !== '') {
                $lines[] = 'Color: ' . $color;
            }
            if ($size !== '') {
                $lines[] = 'Size: ' . $size;
            }
        }
        foreach (self::DETAIL_ATTRIBUTES as $code => $label) {
            $value = $this->optionText($product, $code);
            if ($value !== '' && $value !== '0') {
                $lines[] = $label . ': ' . $value;
            }
        }
        if ((int)$product->getData('ready_to_ship') === 1) {
            $lines[] = 'Ready to ship: yes';
        }
        $short = $this->textCleaner->clean((string)$product->getShortDescription());
        $description = $this->textCleaner->clean((string)$product->getDescription());
        if ($short !== '' && $short !== $description) {
            $lines[] = "Summary:\n" . $short;
        }
        if ($description !== '') {
            $lines[] = "Description:\n" . $description;
        }
        $lines[] = 'Product page: ' . $url;

        $attributes = [
            'product_id' => (int)$product->getId(),
            'sku' => (string)$product->getSku(),
            'type' => (string)$product->getTypeId(),
            'price' => $prices['regular'],
            'final_price' => $prices['final'],
            'currency' => $currency,
            'availability' => $availability,
            'image' => $this->imageUrl($store, $product),
            'categories' => $categories,
            'category_ids' => array_map('intval', (array)$product->getCategoryIds()),
            'colors' => $variants !== [] ? array_values(array_unique(array_filter(array_column($variants, 'color')))) : array_filter([$color]),
            'sizes' => $variants !== [] ? array_values(array_unique(array_filter(array_column($variants, 'size')))) : array_filter([$size]),
            'variants' => $variants,
        ];

        return new SyncDocument(self::CODE . '.' . $product->getId(), (string)$product->getName(), implode("\n", $lines), $url, $attributes);
    }

    /**
     * @return array{regular: float, final: float}
     */
    private function prices(\Magento\Catalog\Model\Product $product): array
    {
        try {
            $info = $product->getPriceInfo();
            $final = (float)$info->getPrice('final_price')->getAmount()->getValue();
            $regular = (float)$info->getPrice('regular_price')->getAmount()->getValue();
        } catch (\Throwable $e) {
            $final = (float)$product->getFinalPrice();
            $regular = (float)$product->getPrice();
        }
        if ($final <= 0 && $regular > 0) {
            $final = $regular;
        }
        return ['regular' => round($regular, 2), 'final' => round($final, 2)];
    }

    private function availability(\Magento\Catalog\Model\Product $product): string
    {
        if ($product->getTypeId() === 'configurable') {
            // Aggregate like the storefront: any in-stock child wins, then pre-order, else out of stock.
            $tiers = [];
            foreach ($this->children($product) as $child) {
                $tiers[] = $this->simpleAvailability($child);
            }
            if (in_array(self::AVAILABILITY_IN_STOCK, $tiers, true)) {
                return self::AVAILABILITY_IN_STOCK;
            }
            if (in_array(self::AVAILABILITY_PRE_ORDER, $tiers, true)) {
                return self::AVAILABILITY_PRE_ORDER;
            }
            return $tiers === [] ? $this->simpleAvailability($product) : self::AVAILABILITY_OUT_OF_STOCK;
        }
        return $this->simpleAvailability($product);
    }

    private function simpleAvailability(\Magento\Catalog\Model\Product $product): string
    {
        $preOrder = (int)$product->getData('pre_order_status');
        $salable = ($this->stockStatus[(int)$product->getId()] ?? 0) === 1;
        if ($preOrder === 1) {
            return self::AVAILABILITY_PRE_ORDER;
        }
        if ($preOrder === 2 && !$salable) {
            return self::AVAILABILITY_PRE_ORDER;
        }
        return $salable ? self::AVAILABILITY_IN_STOCK : self::AVAILABILITY_OUT_OF_STOCK;
    }

    public static function availabilityLabel(string $availability): string
    {
        switch ($availability) {
            case self::AVAILABILITY_IN_STOCK:
                return 'In stock';
            case self::AVAILABILITY_PRE_ORDER:
                return 'Available on pre-order';
            default:
                return 'Out of stock';
        }
    }

    /**
     * @return array<int, array{sku: string, color: string, size: string, final_price: float, availability: string}>
     */
    private function variants(\Magento\Catalog\Model\Product $product): array
    {
        $variants = [];
        foreach ($this->children($product) as $child) {
            $prices = $this->prices($child);
            $variants[] = [
                'sku' => (string)$child->getSku(),
                'color' => $this->optionText($child, 'color'),
                'size' => $this->optionText($child, 'size'),
                'final_price' => $prices['final'],
                'availability' => $this->simpleAvailability($child),
            ];
        }
        return $variants;
    }

    /**
     * @return \Magento\Catalog\Model\Product[]
     */
    private function children(\Magento\Catalog\Model\Product $product): array
    {
        $cached = $product->getData('wb_aichatbot_children');
        if (is_array($cached)) {
            return $cached;
        }
        $children = [];
        try {
            $typeInstance = $product->getTypeInstance();
            if (method_exists($typeInstance, 'getUsedProducts')) {
                $children = $typeInstance->getUsedProducts($product);
                $ids = [];
                foreach ($children as $child) {
                    $ids[] = (int)$child->getId();
                }
                $this->loadStockStatus($ids, true);
            }
        } catch (\Throwable $e) {
            $children = [];
        }
        $children = array_values(array_filter($children, function ($child) {
            return (int)$child->getStatus() === Status::STATUS_ENABLED;
        }));
        $product->setData('wb_aichatbot_children', $children);
        return $children;
    }

    private function optionText(\Magento\Catalog\Model\Product $product, string $code): string
    {
        $value = $product->getData($code);
        if ($value === null || $value === '') {
            return '';
        }
        try {
            $attribute = $product->getResource()->getAttribute($code);
            if ($attribute && $attribute->usesSource()) {
                $text = $attribute->getSource()->getOptionText($value);
                if (is_array($text)) {
                    $text = implode(', ', $text);
                }
                return trim((string)$text);
            }
        } catch (\Throwable $e) {
            // fall through to the raw value
        }
        return trim($this->textCleaner->clean((string)$value));
    }

    /**
     * @return string[] breadcrumb paths of the product's categories in this store
     */
    private function categoryPaths(\Magento\Catalog\Model\Product $product): array
    {
        $paths = [];
        foreach ((array)$product->getCategoryIds() as $categoryId) {
            $path = $this->categoryNames[(int)$categoryId] ?? null;
            if ($path !== null) {
                $paths[] = $path;
            }
        }
        return array_values(array_unique($paths));
    }

    private function loadCategoryNames(StoreInterface $store): void
    {
        $rootId = (int)$store->getRootCategoryId();
        $collection = $this->categoryCollectionFactory->create()
            ->setStoreId((int)$store->getId())
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('is_active', 1)
            ->addFieldToFilter('path', ['like' => '1/' . $rootId . '/%']);
        $names = [];
        foreach ($collection as $category) {
            $names[(int)$category->getId()] = (string)$category->getName();
        }
        $this->categoryNames = [];
        foreach ($collection as $category) {
            $crumbs = [];
            foreach (explode('/', (string)$category->getPath()) as $id) {
                if (isset($names[(int)$id])) {
                    $crumbs[] = $names[(int)$id];
                }
            }
            $this->categoryNames[(int)$category->getId()] = implode(' > ', $crumbs);
        }
    }

    /**
     * @param int[] $ids
     */
    private function loadStockStatus(array $ids, bool $merge = false): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$merge) {
            $this->stockStatus = [];
        }
        $ids = array_diff($ids, array_keys($this->stockStatus));
        if ($ids === []) {
            return;
        }
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName('cataloginventory_stock_status'), ['product_id', 'stock_status'])
            ->where('website_id = 0')
            ->where('product_id IN (?)', $ids);
        foreach ($ids as $id) {
            $this->stockStatus[$id] = 0;
        }
        foreach ($connection->fetchPairs($select) as $productId => $status) {
            $this->stockStatus[(int)$productId] = (int)$status;
        }
    }

    private function imageUrl(StoreInterface $store, \Magento\Catalog\Model\Product $product): ?string
    {
        $file = (string)($product->getSmallImage() ?: $product->getImage());
        if ($file === '' || $file === 'no_selection') {
            return null;
        }
        return rtrim((string)$store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/') . '/catalog/product' . $file;
    }

    private function formatPrice(float $amount, string $currency): string
    {
        try {
            return (string)$this->priceCurrency->format($amount, false, PriceCurrencyInterface::DEFAULT_PRECISION, null, $currency);
        } catch (\Throwable $e) {
            return $currency . ' ' . number_format($amount, 2);
        }
    }
}
