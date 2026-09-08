<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Message extends AbstractDb
{
    private const JSON_FIELDS = ['attachments', 'tool_calls', 'tool_results', 'cards', 'sources'];

    protected function _construct(): void
    {
        $this->_init('wb_aichatbot_message', 'message_id');
    }

    protected function _beforeSave(AbstractModel $object): AbstractDb
    {
        foreach (self::JSON_FIELDS as $field) {
            $value = $object->getData($field);
            if (is_array($value)) {
                $object->setData($field, $value === [] ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
        }
        return parent::_beforeSave($object);
    }
}
