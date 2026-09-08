<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Knowledge;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use WB\AiChatbot\Controller\Adminhtml\Knowledge;

class Index extends Knowledge implements HttpGetActionInterface
{
    public function execute(): ResultInterface
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('WB_AiChatbot::knowledge');
        $resultPage->getConfig()->getTitle()->prepend(__('Knowledge Base'));
        return $resultPage;
    }
}
