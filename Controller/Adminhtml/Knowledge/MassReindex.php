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
use WB\AiChatbot\Model\Kb\DocumentReindexer;
use WB\AiChatbot\Model\Kb\ResourceModel\Document\CollectionFactory;

class MassReindex extends Knowledge implements HttpPostActionInterface
{
    /**
     * @var Filter
     */
    private $filter;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var DocumentReindexer
     */
    private $reindexer;

    public function __construct(
        Action\Context $context,
        Registry $registry,
        Filter $filter,
        CollectionFactory $collectionFactory,
        DocumentReindexer $reindexer
    ) {
        parent::__construct($context, $registry);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->reindexer = $reindexer;
    }

    public function execute(): ResultInterface
    {
        $ids = array_map('intval', $this->filter->getCollection($this->collectionFactory->create())->getAllIds());
        try {
            $result = $this->reindexer->reindex($ids);
            $this->messageManager->addSuccessMessage($this->reindexer->describe($result));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }
        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/');
    }
}
