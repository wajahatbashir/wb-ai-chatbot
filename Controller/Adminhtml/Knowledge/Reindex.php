<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Knowledge;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use WB\AiChatbot\Controller\Adminhtml\Knowledge;
use WB\AiChatbot\Model\Kb\DocumentReindexer;

class Reindex extends Knowledge implements HttpGetActionInterface
{
    /**
     * @var DocumentReindexer
     */
    private $reindexer;

    public function __construct(Action\Context $context, Registry $registry, DocumentReindexer $reindexer)
    {
        parent::__construct($context, $registry);
        $this->reindexer = $reindexer;
    }

    public function execute(): ResultInterface
    {
        $id = (int)$this->getRequest()->getParam('document_id');
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        try {
            $result = $this->reindexer->reindex([$id]);
            $this->messageManager->addSuccessMessage($this->reindexer->describe($result));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        if ($this->getRequest()->getParam('back')) {
            return $resultRedirect->setPath('*/*/edit', ['document_id' => $id]);
        }
        return $resultRedirect->setPath('*/*/');
    }
}
