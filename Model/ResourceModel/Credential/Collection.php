<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\ResourceModel\Credential;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'credential_id';

    protected function _construct(): void
    {
        $this->_init(\WB\AiChatbot\Model\Credential::class, \WB\AiChatbot\Model\ResourceModel\Credential::class);
    }
}
