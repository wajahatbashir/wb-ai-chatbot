<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Knowledge;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use WB\AiChatbot\Controller\Adminhtml\Knowledge;
use WB\AiChatbot\Model\Kb\Document;
use WB\AiChatbot\Model\Kb\DocumentFactory;
use WB\AiChatbot\Model\Kb\ResourceModel\Document as DocumentResource;

class Edit extends Knowledge implements HttpGetActionInterface
{
    /**
     * @var DocumentFactory
     */
    private $documentFactory;

    /**
     * @var DocumentResource
     */
    private $documentResource;

    public function __construct(
        Action\Context $context,
        Registry $registry,
        DocumentFactory $documentFactory,
        DocumentResource $documentResource
    ) {
        parent::__construct($context, $registry);
        $this->documentFactory = $documentFactory;
        $this->documentResource = $documentResource;
    }

    public function execute(): ResultInterface
    {
        $id = (int)$this->getRequest()->getParam('document_id');
        $model = $this->documentFactory->create();
        if ($id) {
            $this->documentResource->load($model, $id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This document no longer exists.'));
                return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/');
            }
        } else {
            $model->setData('source_type', Document::SOURCE_MANUAL);
            $model->setData('is_enabled', 1);
            $model->setData('store_id', 0);
        }
        $this->coreRegistry->register('current_model', $model);

        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('WB_AiChatbot::knowledge');
        $resultPage->getConfig()->getTitle()->prepend(__('Knowledge Base'));
        $resultPage->getConfig()->getTitle()->prepend($id ? $model->getTitle() : __('New Article'));
        return $resultPage;
    }
}
