<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb;

use Magento\Framework\App\ResourceConnection;

/**
 * Raw access to wb_aichatbot_kb_chunk. Embeddings are stored as packed little-endian float32 (pack('f*')),
 * which is 4 bytes per dimension and unpacks straight back into a PHP float array.
 */
class ChunkStorage
{
    private const TABLE = 'wb_aichatbot_kb_chunk';

    /**
     * @var ResourceConnection
     */
    private $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Replaces every chunk of a document.
     *
     * @param array $chunks [['text' => string, 'token_count' => int, 'embedding' => float[]|null], ...]
     */
    public function replaceForDocument(int $documentId, array $chunks, ?string $embeddingModel): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName(self::TABLE);
        $connection->beginTransaction();
        try {
            $connection->delete($table, ['document_id = ?' => $documentId]);
            $rows = [];
            foreach (array_values($chunks) as $index => $chunk) {
                $embedding = $chunk['embedding'] ?? null;
                $rows[] = [
                    'document_id' => $documentId,
                    'chunk_index' => $index,
                    'text' => (string)$chunk['text'],
                    'token_count' => (int)($chunk['token_count'] ?? 0),
                    'embedding' => $embedding ? self::pack($embedding) : null,
                    'embedding_model' => $embedding ? $embeddingModel : null,
                ];
            }
            if ($rows !== []) {
                $connection->insertMultiple($table, $rows);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }

    public function deleteForDocument(int $documentId): void
    {
        $this->resource->getConnection()->delete($this->resource->getTableName(self::TABLE), ['document_id = ?' => $documentId]);
    }

    public function countForDocument(int $documentId): int
    {
        $connection = $this->resource->getConnection();
        return (int)$connection->fetchOne(
            $connection->select()->from($this->resource->getTableName(self::TABLE), 'COUNT(*)')->where('document_id = ?', $documentId)
        );
    }

    /**
     * @return array{chunks:int, embedded:int}
     */
    public function getTotals(): array
    {
        $connection = $this->resource->getConnection();
        $row = $connection->fetchRow($connection->select()->from(
            $this->resource->getTableName(self::TABLE),
            ['chunks' => new \Zend_Db_Expr('COUNT(*)'), 'embedded' => new \Zend_Db_Expr('SUM(embedding IS NOT NULL)')]
        ));
        return ['chunks' => (int)($row['chunks'] ?? 0), 'embedded' => (int)($row['embedded'] ?? 0)];
    }

    /**
     * @param float[] $vector
     */
    public static function pack(array $vector): string
    {
        return pack('f*', ...array_map('floatval', $vector));
    }

    /**
     * @return float[]
     */
    public static function unpack(string $blob): array
    {
        if ($blob === '') {
            return [];
        }
        return array_values(unpack('f*', $blob) ?: []);
    }

    /**
     * @param float[] $a
     * @param float[] $b
     */
    public static function cosine(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na == 0.0 || $nb == 0.0) {
            return 0.0;
        }
        return $dot / (sqrt($na) * sqrt($nb));
    }
}
