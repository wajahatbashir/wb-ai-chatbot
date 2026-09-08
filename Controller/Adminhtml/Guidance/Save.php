<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Guidance;

use WB\AiChatbot\Controller\Adminhtml\Crud\SaveAction;

class Save extends SaveAction
{
    public const ADMIN_RESOURCE = 'WB_AiChatbot::guidance';
    public const TABLE = 'wb_aichatbot_guidance';
    public const ID_FIELD = 'guidance_id';
    public const ROUTE = 'wb_aichatbot/guidance';
    public const MENU = 'WB_AiChatbot::guidance';
    public const TITLE = 'Guidance Rules';
    public const ITEM_TITLE = 'guidance rule';
    public const FIELDS = ['name', 'trigger_description', 'instructions', 'status', 'sort_order'];

    protected function prepare(array $data, int $id): array
    {
        $this->require($data, ['name' => __('Name'), 'trigger_description' => __('When (trigger)'), 'instructions' => __('Instructions')]);
        $data['sort_order'] = (int)($data['sort_order'] ?? 0);
        if (!in_array($data['status'] ?? '', ['enabled', 'disabled', 'testing'], true)) {
            $data['status'] = 'enabled';
        }
        return $data;
    }
}
