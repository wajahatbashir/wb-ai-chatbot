<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Admin;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * Small generic persistence helper for the flat admin-managed tables (Q&A pairs, intents, guidance, requests,
 * unanswered). Grids use UI-component SearchResult collections; forms and actions go through here.
 */
class EntityStore
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    public function load(string $table, string $idField, int $id): ?array
    {
        $connection = $this->resource->getConnection();
        $row = $connection->fetchRow(
            $connection->select()->from($this->resource->getTableName($table))->where($idField . ' = ?', $id)
        );
        return $row ?: null;
    }

    /**
     * Inserts or updates; returns the id.
     */
    public function save(string $table, string $idField, array $data, ?int $id = null): int
    {
        $connection = $this->resource->getConnection();
        $tableName = $this->resource->getTableName($table);
        unset($data[$idField], $data['form_key'], $data['back']);
        if ($id) {
            $connection->update($tableName, $data, [$idField . ' = ?' => $id]);
            return $id;
        }
        $connection->insert($tableName, $data);
        return (int)$connection->lastInsertId($tableName);
    }

    public function delete(string $table, string $idField, array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return 0;
        }
        return $this->resource->getConnection()->delete($this->resource->getTableName($table), [$idField . ' IN (?)' => $ids]);
    }

    public function update(string $table, string $idField, array $ids, array $data): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return 0;
        }
        return $this->resource->getConnection()->update($this->resource->getTableName($table), $data, [$idField . ' IN (?)' => $ids]);
    }

    /**
     * Ids selected in a UI-component mass action ("selected" list, or everything minus "excluded").
     *
     * @return int[]
     */
    public function massIds(RequestInterface $request, string $table, string $idField): array
    {
        $selected = $request->getParam('selected');
        $excluded = $request->getParam('excluded');
        if (is_array($selected) && $selected !== []) {
            return array_map('intval', $selected);
        }
        if ($excluded === 'false' || is_array($excluded)) {
            $connection = $this->resource->getConnection();
            $select = $connection->select()->from($this->resource->getTableName($table), $idField);
            if (is_array($excluded) && $excluded !== []) {
                $select->where($idField . ' NOT IN (?)', array_map('intval', $excluded));
            }
            return array_map('intval', $connection->fetchCol($select));
        }
        return [];
    }

    public function count(string $table, array $where = []): int
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()->from($this->resource->getTableName($table), 'COUNT(*)');
        foreach ($where as $cond => $value) {
            $select->where($cond, $value);
        }
        return (int)$connection->fetchOne($select);
    }
}
