<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Qa;

use WB\AiChatbot\Controller\Adminhtml\Crud\SaveAction;

class Save extends SaveAction
{
    public const ADMIN_RESOURCE = 'WB_AiChatbot::qa';
    public const TABLE = 'wb_aichatbot_qa';
    public const ID_FIELD = 'qa_id';
    public const ROUTE = 'wb_aichatbot/qa';
    public const MENU = 'WB_AiChatbot::qa';
    public const TITLE = 'Q&A Pairs';
    public const ITEM_TITLE = 'Q&A pair';
    public const FIELDS = ['question', 'alternatives', 'answer', 'url', 'store_ids', 'is_enabled'];

    protected function prepare(array $data, int $id): array
    {
        $this->require($data, ['question' => __('Question'), 'answer' => __('Answer')]);
        $data['question'] = mb_substr($data['question'], 0, 500);
        $data['store_ids'] = trim((string)($data['store_ids'] ?? ''), ',');
        if ($data['store_ids'] === '0' || $data['store_ids'] === '') {
            $data['store_ids'] = null;
        }
        $data['is_enabled'] = (int)!empty($data['is_enabled']);
        return $data;
    }

    protected function afterSave(int $id, array $data): void
    {
        // Created from the Unanswered Questions queue: close that entry.
        $unansweredId = (int)$this->getRequest()->getParam('unanswered_id');
        if ($unansweredId) {
            $this->store->update('wb_aichatbot_unanswered', 'unanswered_id', [$unansweredId], ['status' => 'answered']);
        }
    }
}
