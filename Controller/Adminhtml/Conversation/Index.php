<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Conversation;

use WB\AiChatbot\Controller\Adminhtml\Crud\IndexAction;

class Index extends IndexAction
{
    public const ADMIN_RESOURCE = 'WB_AiChatbot::conversations';
    public const TABLE = 'wb_aichatbot_conversation';
    public const ID_FIELD = 'conversation_id';
    public const ROUTE = 'wb_aichatbot/conversation';
    public const MENU = 'WB_AiChatbot::conversations';
    public const TITLE = 'Conversations';
    public const ITEM_TITLE = 'conversation';
    public const STATUS_FIELD = 'status';
}
