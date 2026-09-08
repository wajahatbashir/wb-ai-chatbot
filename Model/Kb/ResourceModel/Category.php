<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Category extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('wb_aichatbot_kb_category', 'category_id');
    }

    /**
     * Returns the id of the category with this identifier, creating it when missing.
     */
    public function ensure(string $identifier, string $name): int
    {
        $connection = $this->getConnection();
        $id = (int)$connection->fetchOne(
            $connection->select()->from($this->getMainTable(), 'category_id')->where('identifier = ?', $identifier)
        );
        if ($id) {
            return $id;
        }
        $connection->insert($this->getMainTable(), ['identifier' => $identifier, 'name' => $name, 'is_visible' => 1]);
        return (int)$connection->lastInsertId($this->getMainTable());
    }
}
