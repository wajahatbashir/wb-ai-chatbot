<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Credential;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Ui\Component\MassAction\Filter;
use WB\AiChatbot\Controller\Adminhtml\Credential;
use WB\AiChatbot\Model\ResourceModel\Credential\CollectionFactory;

class MassDelete extends Credential implements HttpPostActionInterface
{
    /**
     * @var Filter
     */
    private $filter;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    public function __construct(Action\Context $context, Registry $registry, Filter $filter, CollectionFactory $collectionFactory)
    {
        parent::__construct($context, $registry);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
    }

    public function execute(): ResultInterface
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $deleted = 0;
        foreach ($collection as $credential) {
            $credential->delete();
            $deleted++;
        }

        $this->messageManager->addSuccessMessage(__('A total of %1 record(s) were deleted.', $deleted));
        return $resultRedirect->setPath('*/*/');
    }
}
