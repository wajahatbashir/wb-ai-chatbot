<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Knowledge;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use WB\AiChatbot\Controller\Adminhtml\Knowledge;
use WB\AiChatbot\Model\Kb\DocumentFactory;
use WB\AiChatbot\Model\Kb\ResourceModel\Document as DocumentResource;

class Delete extends Knowledge implements HttpPostActionInterface
{
    /**
     * @var DocumentFactory
     */
    private $documentFactory;

    /**
     * @var DocumentResource
     */
    private $documentResource;

    public function __construct(Action\Context $context, Registry $registry, DocumentFactory $documentFactory, DocumentResource $documentResource)
    {
        parent::__construct($context, $registry);
        $this->documentFactory = $documentFactory;
        $this->documentResource = $documentResource;
    }

    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $id = (int)$this->getRequest()->getParam('document_id');
        if ($id) {
            try {
                $model = $this->documentFactory->create();
                $this->documentResource->load($model, $id);
                if ($model->getId()) {
                    $this->documentResource->delete($model);
                }
                $this->messageManager->addSuccessMessage(__('The document was deleted.'));
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }
        return $resultRedirect->setPath('*/*/');
    }
}
