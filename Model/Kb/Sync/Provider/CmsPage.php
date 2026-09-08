<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb\Sync\Provider;

use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Framework\App\Area;
use WB\AiChatbot\Model\Kb\Sync\SyncDocument;
use WB\AiChatbot\Model\Kb\Sync\SyncProviderInterface;
use WB\AiChatbot\Model\Kb\Sync\TextCleaner;

/**
 * Active CMS pages, rendered through the CMS template filter so {{block}}/{{widget}} directives expand to real
 * text (many policy pages on this site are only a directive), then flattened to plain text.
 */
class CmsPage implements SyncProviderInterface
{
    public const CODE = 'cms_page';

    /**
     * Pages that carry no customer-facing knowledge.
     */
    private const SKIP_IDENTIFIERS = ['home', 'no-route', 'enable-cookies', 'privacy-policy-cookie-restriction-mode'];

    /**
     * @var PageCollectionFactory
     */
    private $pageCollectionFactory;

    /**
     * @var FilterProvider
     */
    private $filterProvider;

    /**
     * @var Emulation
     */
    private $emulation;

    /**
     * @var TextCleaner
     */
    private $textCleaner;

    public function __construct(
        PageCollectionFactory $pageCollectionFactory,
        FilterProvider $filterProvider,
        Emulation $emulation,
        TextCleaner $textCleaner
    ) {
        $this->pageCollectionFactory = $pageCollectionFactory;
        $this->filterProvider = $filterProvider;
        $this->emulation = $emulation;
        $this->textCleaner = $textCleaner;
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getTitle(): string
    {
        return 'CMS Pages';
    }

    public function getCategoryName(): string
    {
        return 'Policies & Information Pages';
    }

    public function fetch(StoreInterface $store): \Generator
    {
        $collection = $this->pageCollectionFactory->create()
            ->addStoreFilter((int)$store->getId())
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('identifier', ['nin' => self::SKIP_IDENTIFIERS])
            ->setOrder('title', 'ASC');

        $this->emulation->startEnvironmentEmulation((int)$store->getId(), Area::AREA_FRONTEND, true);
        try {
            foreach ($collection as $page) {
                $document = $this->build($store, $page);
                if ($document) {
                    yield $document;
                }
            }
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }

    public function fetchOne(StoreInterface $store, string $identifier): ?SyncDocument
    {
        $pageId = (int)substr($identifier, strlen(self::CODE) + 1);
        if (!$pageId) {
            return null;
        }
        $collection = $this->pageCollectionFactory->create()
            ->addStoreFilter((int)$store->getId())
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('page_id', $pageId);
        $page = $collection->getFirstItem();
        if (!$page->getId() || in_array($page->getIdentifier(), self::SKIP_IDENTIFIERS, true)) {
            return null;
        }
        $this->emulation->startEnvironmentEmulation((int)$store->getId(), Area::AREA_FRONTEND, true);
        try {
            return $this->build($store, $page);
        } finally {
            $this->emulation->stopEnvironmentEmulation();
        }
    }

    private function build(StoreInterface $store, \Magento\Cms\Model\Page $page): ?SyncDocument
    {
        $raw = (string)$page->getContent();
        try {
            $html = $this->filterProvider->getPageFilter()->filter($raw);
        } catch (\Throwable $e) {
            $html = $raw;
        }
        // A broken block/widget directive renders as an error notice in developer mode; fall back to the raw HTML.
        if (stripos($html, 'Error filtering template') !== false) {
            $html = $raw;
        }
        $body = $this->textCleaner->clean($html);
        if (stripos($body, 'Error filtering template') !== false || preg_match('/^\{\{[^}]+\}\}$/', $body)) {
            return null;
        }
        if ($body === '') {
            return null;
        }
        $heading = trim((string)$page->getContentHeading());
        if ($heading !== '' && mb_stripos($body, $heading) !== 0) {
            $body = $heading . "\n\n" . $body;
        }
        $url = rtrim((string)$store->getBaseUrl(), '/') . '/' . ltrim((string)$page->getIdentifier(), '/');
        return new SyncDocument(
            self::CODE . '.' . $page->getId(),
            (string)$page->getTitle(),
            $body,
            $url,
            ['identifier' => (string)$page->getIdentifier(), 'page_id' => (int)$page->getId()]
        );
    }
}
