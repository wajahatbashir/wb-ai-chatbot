<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Intent;

use WB\AiChatbot\Controller\Adminhtml\Crud\SaveAction;

class Save extends SaveAction
{
    public const ADMIN_RESOURCE = 'WB_AiChatbot::intents';
    public const TABLE = 'wb_aichatbot_intent';
    public const ID_FIELD = 'intent_id';
    public const ROUTE = 'wb_aichatbot/intent';
    public const MENU = 'WB_AiChatbot::intents';
    public const TITLE = 'Intent Dictionaries';
    public const ITEM_TITLE = 'intent dictionary';
    public const FIELDS = ['intent_code', 'language', 'keywords', 'priority', 'is_enabled'];

    protected function prepare(array $data, int $id): array
    {
        $this->require($data, ['intent_code' => __('Intent'), 'language' => __('Language'), 'keywords' => __('Keywords')]);
        $data['language'] = mb_substr(mb_strtolower($data['language']), 0, 8);
        $data['priority'] = (int)($data['priority'] ?? 0);
        $data['is_enabled'] = (int)!empty($data['is_enabled']);
        foreach (preg_split('/\r\n|\r|\n/', (string)$data['keywords']) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && $line[0] === '/' && @preg_match($line, '') === false) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Invalid regular expression: %1', $line));
            }
        }
        return $data;
    }
}
