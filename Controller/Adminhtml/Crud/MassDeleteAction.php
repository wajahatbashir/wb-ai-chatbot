<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Crud;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;

abstract class MassDeleteAction extends AbstractCrud implements HttpPostActionInterface
{
    public function execute(): ResultInterface
    {
        $ids = $this->store->massIds($this->getRequest(), static::TABLE, static::ID_FIELD);
        $deleted = $this->store->delete(static::TABLE, static::ID_FIELD, $ids);
        $this->messageManager->addSuccessMessage(__('A total of %1 record(s) were deleted.', $deleted));
        return $this->redirectToGrid();
    }
}
