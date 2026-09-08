<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Crud;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;

/**
 * Sets STATUS_FIELD to the "status" request parameter for the selected rows.
 */
abstract class MassStatusAction extends AbstractCrud implements HttpPostActionInterface
{
    public function execute(): ResultInterface
    {
        $status = (string)$this->getRequest()->getParam('status');
        $ids = $this->store->massIds($this->getRequest(), static::TABLE, static::ID_FIELD);
        $updated = $this->store->update(static::TABLE, static::ID_FIELD, $ids, [static::STATUS_FIELD => $status]);
        $this->messageManager->addSuccessMessage(__('A total of %1 record(s) were updated.', $updated));
        return $this->redirectToGrid();
    }
}
