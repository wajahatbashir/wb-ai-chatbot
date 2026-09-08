<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Intent;

use WB\AiChatbot\Controller\Adminhtml\Crud\NewActionAction;

class NewAction extends NewActionAction
{
    public const ADMIN_RESOURCE = 'WB_AiChatbot::intents';
    public const TABLE = 'wb_aichatbot_intent';
    public const ID_FIELD = 'intent_id';
    public const ROUTE = 'wb_aichatbot/intent';
    public const MENU = 'WB_AiChatbot::intents';
    public const TITLE = 'Intent Dictionaries';
    public const ITEM_TITLE = 'intent dictionary';
    public const STATUS_FIELD = 'is_enabled';
}
