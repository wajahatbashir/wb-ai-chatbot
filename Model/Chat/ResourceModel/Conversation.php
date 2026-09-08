<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Conversation extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('wb_aichatbot_conversation', 'conversation_id');
    }
}
