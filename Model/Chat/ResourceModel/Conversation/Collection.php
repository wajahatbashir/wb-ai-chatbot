<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat\ResourceModel\Conversation;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'conversation_id';

    protected function _construct(): void
    {
        $this->_init(\WB\AiChatbot\Model\Chat\Conversation::class, \WB\AiChatbot\Model\Chat\ResourceModel\Conversation::class);
    }
}
