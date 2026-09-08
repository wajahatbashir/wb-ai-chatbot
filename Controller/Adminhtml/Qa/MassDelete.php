<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Qa;

use WB\AiChatbot\Controller\Adminhtml\Crud\MassDeleteAction;

class MassDelete extends MassDeleteAction
{
    public const ADMIN_RESOURCE = 'WB_AiChatbot::qa';
    public const TABLE = 'wb_aichatbot_qa';
    public const ID_FIELD = 'qa_id';
    public const ROUTE = 'wb_aichatbot/qa';
    public const MENU = 'WB_AiChatbot::qa';
    public const TITLE = 'Q&A Pairs';
    public const ITEM_TITLE = 'Q&A pair';
    public const STATUS_FIELD = 'is_enabled';
}
