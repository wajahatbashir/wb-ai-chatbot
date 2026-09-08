<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Crud;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;

abstract class IndexAction extends AbstractCrud implements HttpGetActionInterface
{
    public function execute(): ResultInterface
    {
        return $this->page(__(static::TITLE), static::MENU);
    }
}
