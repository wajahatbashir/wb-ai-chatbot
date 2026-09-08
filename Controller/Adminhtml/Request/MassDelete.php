<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Request;

use WB\AiChatbot\Controller\Adminhtml\Crud\MassDeleteAction;

class MassDelete extends MassDeleteAction
{
    public const ADMIN_RESOURCE = 'WB_AiChatbot::requests';
    public const TABLE = 'wb_aichatbot_request';
    public const ID_FIELD = 'request_id';
    public const ROUTE = 'wb_aichatbot/request';
    public const MENU = 'WB_AiChatbot::requests';
    public const TITLE = 'Support Requests';
    public const ITEM_TITLE = 'support request';
    public const STATUS_FIELD = 'status';
}
