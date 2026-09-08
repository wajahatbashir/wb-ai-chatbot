<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Crud;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;

abstract class NewActionAction extends AbstractCrud implements HttpGetActionInterface
{
    public function execute(): ResultInterface
    {
        return $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_FORWARD)->forward('edit');
    }
}
