<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Dashboard;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'WB_AiChatbot::dashboard';

    public function execute(): ResultInterface
    {
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('WB_AiChatbot::dashboard');
        $page->getConfig()->getTitle()->prepend(__('AI Chatbot Dashboard'));
        return $page;
    }
}
