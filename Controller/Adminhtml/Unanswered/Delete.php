<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Unanswered;

use WB\AiChatbot\Controller\Adminhtml\Crud\DeleteAction;

class Delete extends DeleteAction
{
    public const ADMIN_RESOURCE = 'WB_AiChatbot::unanswered';
    public const TABLE = 'wb_aichatbot_unanswered';
    public const ID_FIELD = 'unanswered_id';
    public const ROUTE = 'wb_aichatbot/unanswered';
    public const MENU = 'WB_AiChatbot::unanswered';
    public const TITLE = 'Unanswered Questions';
    public const ITEM_TITLE = 'unanswered question';
    public const STATUS_FIELD = 'status';
}
