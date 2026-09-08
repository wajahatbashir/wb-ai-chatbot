<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Manual;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'WB_AiChatbot::manual';

    public function execute(): ResultInterface
    {
        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('WB_AiChatbot::manual');
        $resultPage->getConfig()->getTitle()->prepend(__('User Manual'));
        return $resultPage;
    }
}
