<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Knowledge;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use WB\AiChatbot\Controller\Adminhtml\Knowledge;
use WB\AiChatbot\Model\Kb\Document;
use WB\AiChatbot\Model\Kb\DocumentFactory;
use WB\AiChatbot\Model\Kb\Indexer;
use WB\AiChatbot\Model\Kb\ResourceModel\Category as CategoryResource;
use WB\AiChatbot\Model\Kb\ResourceModel\Document as DocumentResource;

class Save extends Knowledge implements HttpPostActionInterface
{
    /**
     * @var DocumentFactory
     */
    private $documentFactory;

    /**
     * @var DocumentResource
     */
    private $documentResource;

    /**
     * @var CategoryResource
     */
    private $categoryResource;

    /**
     * @var Indexer
     */
    private $indexer;

    public function __construct(
        Action\Context $context,
        Registry $registry,
        DocumentFactory $documentFactory,
        DocumentResource $documentResource,
        CategoryResource $categoryResource,
        Indexer $indexer
    ) {
        parent::__construct($context, $registry);
        $this->documentFactory = $documentFactory;
        $this->documentResource = $documentResource;
        $this->categoryResource = $categoryResource;
        $this->indexer = $indexer;
    }

    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }
        $id = (int)($data['document_id'] ?? 0);
        $model = $this->documentFactory->create();
        if ($id) {
            $this->documentResource->load($model, $id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This document no longer exists.'));
                return $resultRedirect->setPath('*/*/');
            }
        }

        try {
            if ($model->getId() && !$model->isManual()) {
                // Synced documents: only the switches are editable; content comes from the source entity.
                $model->setData('is_enabled', (int)!empty($data['is_enabled']));
            } else {
                $title = trim((string)($data['title'] ?? ''));
                $body = trim((string)($data['body'] ?? ''));
                if ($title === '' || $body === '') {
                    throw new \Magento\Framework\Exception\LocalizedException(__('Title and content are required.'));
                }
                $model->addData([
                    'source_type' => Document::SOURCE_MANUAL,
                    'identifier' => $model->getId() ? $model->getIdentifier() : 'manual.' . bin2hex(random_bytes(6)),
                    'store_id' => (int)($data['store_id'] ?? 0),
                    'category_id' => $this->categoryResource->ensure(Document::SOURCE_MANUAL, 'Articles'),
                    'title' => mb_substr($title, 0, 255),
                    'body' => $body,
                    'url' => trim((string)($data['url'] ?? '')) ?: null,
                    'is_enabled' => (int)!empty($data['is_enabled']),
                    'sync_status' => Document::STATUS_PENDING,
                    'content_hash' => hash('sha256', $title . "\n" . $body),
                ]);
            }
            $this->documentResource->save($model);

            if ($model->isManual()) {
                $stats = $this->indexer->indexDocuments([(int)$model->getId()]);
                if ($stats['failed'] > 0) {
                    $this->messageManager->addWarningMessage(__('The article was saved but indexing failed; check var/log/wb_aichatbot.log.'));
                } else {
                    $this->messageManager->addSuccessMessage(__(
                        'The article was saved and indexed%1.',
                        $stats['embedded'] > 0 ? '' : ' (keyword search only - no embedding provider is available)'
                    ));
                }
            } else {
                $this->messageManager->addSuccessMessage(__('The document was saved.'));
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $resultRedirect->setPath('*/*/edit', ['document_id' => $model->getId()]);
        }

        if ($this->getRequest()->getParam('back')) {
            return $resultRedirect->setPath('*/*/edit', ['document_id' => $model->getId()]);
        }
        return $resultRedirect->setPath('*/*/');
    }
}
