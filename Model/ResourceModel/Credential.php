<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Credential extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('wb_aichatbot_credential', 'credential_id');
    }
}
