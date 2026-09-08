<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Knowledge;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use WB\AiChatbot\Controller\Adminhtml\Knowledge;
use WB\AiChatbot\Model\Kb\Indexer;
use WB\AiChatbot\Model\Kb\Queue\Publisher;
use WB\AiChatbot\Model\Kb\Sync\ProviderPool;
use WB\AiChatbot\Model\Kb\Synchronizer;

/**
 * "Sync now" button. Small sources (store info, CMS pages, categories) run inline; the product catalog and
 * "everything" go through the message queue so the admin request returns immediately.
 */
class Sync extends Knowledge implements HttpGetActionInterface
{
    private const INLINE_TYPES = ['store_info', 'cms_page', 'category'];

    /**
     * @var ProviderPool
     */
    private $providerPool;

    /**
     * @var Synchronizer
     */
    private $synchronizer;

    /**
     * @var Indexer
     */
    private $indexer;

    /**
     * @var Publisher
     */
    private $publisher;

    public function __construct(
        Action\Context $context,
        Registry $registry,
        ProviderPool $providerPool,
        Synchronizer $synchronizer,
        Indexer $indexer,
        Publisher $publisher
    ) {
        parent::__construct($context, $registry);
        $this->providerPool = $providerPool;
        $this->synchronizer = $synchronizer;
        $this->indexer = $indexer;
        $this->publisher = $publisher;
    }

    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/');
        $type = trim((string)$this->getRequest()->getParam('type'));
        if ($type !== '' && !$this->providerPool->has($type)) {
            $this->messageManager->addErrorMessage(__('Unknown knowledge base source "%1".', $type));
            return $resultRedirect;
        }

        if ($type === '' || !in_array($type, self::INLINE_TYPES, true)) {
            $this->publisher->publishSync($type);
            $this->messageManager->addSuccessMessage(__(
                'The %1 sync was queued. The wbAichatbotKbIndex consumer processes it in the background (run "bin/magento wb:aichatbot:sync" for an immediate run).',
                $type === '' ? __('full knowledge base') : $this->providerPool->get($type)->getTitle()
            ));
            return $resultRedirect;
        }

        try {
            set_time_limit(0);
            $stats = $this->synchronizer->sync([$type]);
            $index = $this->indexer->indexPending();
            $totals = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'deleted' => 0];
            foreach ($stats[$type] ?? [] as $byStore) {
                foreach ($totals as $key => $value) {
                    $totals[$key] += $byStore[$key];
                }
            }
            $this->messageManager->addSuccessMessage(__(
                '%1 synced: %2 created, %3 updated, %4 unchanged, %5 deleted. %6 document(s) indexed%7.',
                $this->providerPool->get($type)->getTitle(),
                $totals['created'],
                $totals['updated'],
                $totals['unchanged'],
                $totals['deleted'],
                $index['indexed'],
                $index['indexed'] > 0 && $index['embedded'] === 0 ? ' (keyword search only - no embedding provider is available)' : ''
            ));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Sync failed: %1', $e->getMessage()));
        }
        return $resultRedirect;
    }
}
