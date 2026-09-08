<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Guidance;

use WB\AiChatbot\Controller\Adminhtml\Crud\NewActionAction;

class NewAction extends NewActionAction
{
    public const ADMIN_RESOURCE = 'WB_AiChatbot::guidance';
    public const TABLE = 'wb_aichatbot_guidance';
    public const ID_FIELD = 'guidance_id';
    public const ROUTE = 'wb_aichatbot/guidance';
    public const MENU = 'WB_AiChatbot::guidance';
    public const TITLE = 'Guidance Rules';
    public const ITEM_TITLE = 'guidance rule';
    public const STATUS_FIELD = 'status';
}
