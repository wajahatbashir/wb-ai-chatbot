<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\ResourceModel\Table;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Unanswered extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('wb_aichatbot_unanswered', 'unanswered_id');
    }
}
