<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Conversation;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use WB\AiChatbot\Controller\Adminhtml\Crud\AbstractCrud;

class View extends AbstractCrud implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'WB_AiChatbot::conversations';
    public const TABLE = 'wb_aichatbot_conversation';
    public const ID_FIELD = 'conversation_id';
    public const ROUTE = 'wb_aichatbot/conversation';
    public const MENU = 'WB_AiChatbot::conversations';
    public const TITLE = 'Conversations';

    public function execute(): ResultInterface
    {
        $id = (int)$this->getRequest()->getParam(self::ID_FIELD);
        $row = $id ? $this->store->load(self::TABLE, self::ID_FIELD, $id) : null;
        if (!$row) {
            $this->messageManager->addErrorMessage(__('This conversation no longer exists.'));
            return $this->redirectToGrid();
        }
        $this->registerModel($row);
        $page = $this->page(__(self::TITLE), self::MENU);
        $page->getConfig()->getTitle()->prepend(__('Conversation #%1', $id));
        return $page;
    }
}
