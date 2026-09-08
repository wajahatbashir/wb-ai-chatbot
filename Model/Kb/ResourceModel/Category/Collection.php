<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb\ResourceModel\Category;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'category_id';

    protected function _construct(): void
    {
        $this->_init(\WB\AiChatbot\Model\Kb\Category::class, \WB\AiChatbot\Model\Kb\ResourceModel\Category::class);
    }
}
