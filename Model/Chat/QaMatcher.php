<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

use Magento\Framework\App\ResourceConnection;

/**
 * Admin-written Q&A pairs, matched before any AI call in both modes. Fulltext narrows the candidates, token
 * similarity against the question and each alternative phrasing decides.
 */
class QaMatcher
{
    private const MIN_SIMILARITY = 0.55;

    /**
     * @var ResourceConnection
     */
    private $resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    /**
     * @return array{qa_id:int, question:string, answer:string, url:?string, similarity:float}|null
     */
    public function match(string $question, int $storeId): ?array
    {
        $normalized = Text::normalize($question);
        if ($normalized === '' || count(Text::tokens($question)) < 2) {
            return null;
        }
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('wb_aichatbot_qa');
        $select = $connection->select()
            ->from($table, ['qa_id', 'question', 'alternatives', 'answer', 'url', 'store_ids'])
            ->where('is_enabled = 1')
            ->where(
                'MATCH(question, alternatives) AGAINST(' . $connection->quote($normalized) . ' IN NATURAL LANGUAGE MODE)'
            )
            ->limit(20);
        $best = null;
        foreach ($connection->fetchAll($select) as $row) {
            $stores = array_filter(array_map('intval', explode(',', (string)$row['store_ids'])));
            if ($stores !== [] && !in_array($storeId, $stores, true)) {
                continue;
            }
            $phrasings = array_merge([(string)$row['question']], preg_split('/\r\n|\r|\n/', (string)$row['alternatives']) ?: []);
            foreach ($phrasings as $phrasing) {
                $phrasing = trim($phrasing);
                if ($phrasing === '') {
                    continue;
                }
                $similarity = Text::similarity($question, $phrasing);
                if ($similarity >= self::MIN_SIMILARITY && ($best === null || $similarity > $best['similarity'])) {
                    $best = [
                        'qa_id' => (int)$row['qa_id'],
                        'question' => (string)$row['question'],
                        'answer' => (string)$row['answer'],
                        'url' => $row['url'] !== null && $row['url'] !== '' ? (string)$row['url'] : null,
                        'similarity' => round($similarity, 3),
                    ];
                }
            }
        }
        if ($best) {
            $connection->update($table, ['hits' => new \Zend_Db_Expr('hits + 1')], ['qa_id = ?' => $best['qa_id']]);
        }
        return $best;
    }
}
