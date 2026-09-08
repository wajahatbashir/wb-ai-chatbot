<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Credential;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use WB\AiChatbot\Controller\Adminhtml\Credential;

class Index extends Credential implements HttpGetActionInterface
{
    public function execute(): ResultInterface
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('WB_AiChatbot::credentials');
        $resultPage->getConfig()->getTitle()->prepend(__('AI Providers'));

        return $resultPage;
    }
}
