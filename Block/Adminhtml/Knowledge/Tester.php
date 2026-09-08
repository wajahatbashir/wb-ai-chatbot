<?php
declare(strict_types=1);

namespace WB\AiChatbot\Block\Adminhtml\Knowledge;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Store\Model\System\Store as SystemStore;
use WB\AiChatbot\Model\Kb\Embedder;
use WB\AiChatbot\Model\Kb\Retriever;
use WB\AiChatbot\Model\Source\KbSourceType;

/**
 * Retrieval tester: shows exactly which knowledge base passages the assistant would receive for a question.
 */
class Tester extends Template
{
    /**
     * @var string
     */
    protected $_template = 'WB_AiChatbot::knowledge/tester.phtml';

    /**
     * @var Retriever
     */
    private $retriever;

    /**
     * @var Embedder
     */
    private $embedder;

    /**
     * @var SystemStore
     */
    private $systemStore;

    /**
     * @var KbSourceType
     */
    private $sourceType;

    /**
     * @var array|null
     */
    private $results;

    /**
     * @var float
     */
    private $elapsedMs = 0.0;

    public function __construct(
        Context $context,
        Retriever $retriever,
        Embedder $embedder,
        SystemStore $systemStore,
        KbSourceType $sourceType,
        array $data = []
    ) {
        $this->retriever = $retriever;
        $this->embedder = $embedder;
        $this->systemStore = $systemStore;
        $this->sourceType = $sourceType;
        parent::__construct($context, $data);
    }

    public function getQuery(): string
    {
        return trim((string)$this->getRequest()->getParam('q'));
    }

    public function getStoreId(): int
    {
        return (int)$this->getRequest()->getParam('store', 1);
    }

    public function getSourceTypeFilter(): string
    {
        return (string)$this->getRequest()->getParam('type', '');
    }

    public function getLimit(): int
    {
        return max(1, min(20, (int)$this->getRequest()->getParam('limit', 6)));
    }

    public function isKeywordOnly(): bool
    {
        return (bool)$this->getRequest()->getParam('keyword_only');
    }

    public function isEmbeddingAvailable(): bool
    {
        return $this->embedder->isAvailable();
    }

    public function getStoreOptions(): array
    {
        return $this->systemStore->getStoreValuesForForm(false, false);
    }

    public function getSourceTypeOptions(): array
    {
        return $this->sourceType->toOptionArray();
    }

    public function getResults(): array
    {
        if ($this->results === null) {
            $this->results = [];
            if ($this->getQuery() !== '') {
                $started = microtime(true);
                $this->results = $this->retriever->search(
                    $this->getQuery(),
                    $this->getStoreId(),
                    $this->getLimit(),
                    $this->getSourceTypeFilter() !== '' ? [$this->getSourceTypeFilter()] : [],
                    !$this->isKeywordOnly()
                );
                $this->elapsedMs = (microtime(true) - $started) * 1000;
            }
        }
        return $this->results;
    }

    public function getElapsedMs(): float
    {
        $this->getResults();
        return $this->elapsedMs;
    }

    public function getDocumentUrl(int $documentId): string
    {
        return $this->getUrl('wb_aichatbot/knowledge/edit', ['document_id' => $documentId]);
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('wb_aichatbot/knowledge/index');
    }
}
