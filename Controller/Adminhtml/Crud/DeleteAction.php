<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Crud;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;

abstract class DeleteAction extends AbstractCrud implements HttpPostActionInterface
{
    public function execute(): ResultInterface
    {
        $id = (int)$this->getRequest()->getParam(static::ID_FIELD);
        if ($id && $this->store->delete(static::TABLE, static::ID_FIELD, [$id])) {
            $this->messageManager->addSuccessMessage(__('The %1 was deleted.', __(static::ITEM_TITLE)));
        }
        return $this->redirectToGrid();
    }
}
