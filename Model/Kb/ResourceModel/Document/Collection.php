<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb\ResourceModel\Document;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'document_id';

    protected function _construct(): void
    {
        $this->_init(\WB\AiChatbot\Model\Kb\Document::class, \WB\AiChatbot\Model\Kb\ResourceModel\Document::class);
    }
}
