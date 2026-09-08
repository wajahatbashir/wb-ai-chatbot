<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Document extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('wb_aichatbot_kb_document', 'document_id');
    }

    protected function _beforeSave(AbstractModel $object): AbstractDb
    {
        $attributes = $object->getData('attributes');
        if (is_array($attributes)) {
            $object->setData('attributes', json_encode($attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        return parent::_beforeSave($object);
    }

    /**
     * Sets sync_status for every document of an identifier (all store views), e.g. after a product save.
     */
    public function markStatusByIdentifier(string $identifier, string $status): int
    {
        return $this->getConnection()->update(
            $this->getMainTable(),
            ['sync_status' => $status],
            ['identifier = ?' => $identifier]
        );
    }

    public function deleteByIdentifier(string $identifier): int
    {
        return $this->getConnection()->delete($this->getMainTable(), ['identifier = ?' => $identifier]);
    }

    /**
     * Removes synced documents of one source/store that were not touched by the latest sync run.
     *
     * @param int[] $keepIds
     */
    public function deleteStale(string $sourceType, int $storeId, array $keepIds): int
    {
        $where = ['source_type = ?' => $sourceType, 'store_id = ?' => $storeId];
        if ($keepIds !== []) {
            $where['document_id NOT IN (?)'] = $keepIds;
        }
        return $this->getConnection()->delete($this->getMainTable(), $where);
    }

    /**
     * @return array<string, int> counts keyed by sync_status
     */
    public function getStatusCounts(): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['sync_status', 'cnt' => new \Zend_Db_Expr('COUNT(*)')])
            ->group('sync_status');
        $counts = [];
        foreach ($connection->fetchAll($select) as $row) {
            $counts[(string)$row['sync_status']] = (int)$row['cnt'];
        }
        return $counts;
    }
}
