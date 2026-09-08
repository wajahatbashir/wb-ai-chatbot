<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Knowledge;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use Magento\Ui\Component\MassAction\Filter;
use WB\AiChatbot\Controller\Adminhtml\Knowledge;
use WB\AiChatbot\Model\Kb\ResourceModel\Document\CollectionFactory;

class MassDelete extends Knowledge implements HttpPostActionInterface
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
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $deleted = 0;
        foreach ($collection as $document) {
            $document->delete();
            $deleted++;
        }
        $this->messageManager->addSuccessMessage(__('A total of %1 document(s) were deleted.', $deleted));
        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/');
    }
}
