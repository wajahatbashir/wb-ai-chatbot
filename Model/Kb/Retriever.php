<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb;

use Magento\Framework\App\ResourceConnection;
use WB\AiChatbot\Model\Logger;

/**
 * Hybrid retrieval: MySQL fulltext narrows the candidate chunks, then (when the query and the chunks share an
 * embedding model) cosine similarity re-ranks them. Without embeddings the fulltext score alone is used, so
 * retrieval keeps working when no AI provider is usable.
 */
class Retriever
{
    private const CANDIDATES = 300;

    private const STOPWORDS = [
        'a', 'an', 'the', 'is', 'are', 'do', 'does', 'you', 'your', 'i', 'my', 'me', 'we', 'it', 'of', 'to', 'in',
        'on', 'for', 'and', 'or', 'with', 'can', 'could', 'would', 'what', 'which', 'how', 'have', 'has', 'any',
        'please', 'want', 'need', 'looking', 'show', 'tell', 'about', 'there', 'this', 'that', 'be', 'at', 'from',
    ];

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var Embedder
     */
    private $embedder;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(ResourceConnection $resource, Embedder $embedder, Logger $logger)
    {
        $this->resource = $resource;
        $this->embedder = $embedder;
        $this->logger = $logger;
    }

    /**
     * @param string[] $sourceTypes restrict to these source types (empty = all)
     * @return array<int, array{document_id:int, chunk_id:int, title:string, text:string, url:?string, source_type:string, attributes:array, score:float, keyword_score:float, vector_score:?float}>
     */
    public function search(string $query, int $storeId, int $limit = 6, array $sourceTypes = [], bool $useEmbeddings = true): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }
        $candidates = $this->keywordCandidates($query, $storeId, $sourceTypes);

        $queryVector = null;
        $model = null;
        if ($useEmbeddings && $this->embedder->isAvailable()) {
            try {
                $vectors = $this->embedder->embed([$query]);
                $queryVector = $vectors[0] ?? null;
                $model = $this->embedder->getLastModel();
            } catch (\Throwable $e) {
                $this->logger->warning('KB retrieval continues keyword-only: ' . $e->getMessage());
            }
        }

        if ($queryVector !== null && $model !== null) {
            // Fulltext found little: widen the candidate set so semantic matching can still surface documents.
            if (count($candidates) < $limit * 3) {
                $candidates = $candidates + $this->recentCandidates($storeId, $sourceTypes, $model, array_keys($candidates));
            }
            foreach ($candidates as $chunkId => $row) {
                $vector = null;
                if (!empty($row['embedding']) && $row['embedding_model'] === $model) {
                    $vector = ChunkStorage::unpack($row['embedding']);
                }
                $vectorScore = $vector ? ChunkStorage::cosine($queryVector, $vector) : null;
                // Absolute squash (8 -> 0.5, 24 -> 0.75): a weak keyword hit stays weak even when it is the best one,
                // so the final score doubles as a confidence value.
                $keyword = (float)$row['keyword_score'];
                $keywordNorm = $keyword / ($keyword + 8.0);
                $candidates[$chunkId]['vector_score'] = $vectorScore;
                $score = $vectorScore !== null
                    ? 0.7 * max(0.0, $vectorScore) + 0.3 * $keywordNorm
                    : 0.3 * $keywordNorm;
                $candidates[$chunkId]['score'] = $score * $this->sourceBoost((string)$row['source_type']);
            }
        } else {
            foreach ($candidates as $chunkId => $row) {
                $candidates[$chunkId]['vector_score'] = null;
                $candidates[$chunkId]['score'] = (float)$row['keyword_score'] * $this->sourceBoost((string)$row['source_type']);
            }
        }

        uasort($candidates, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // One chunk per document keeps the context diverse.
        $results = [];
        $seenDocuments = [];
        foreach ($candidates as $chunkId => $row) {
            if (isset($seenDocuments[$row['document_id']])) {
                continue;
            }
            if ($row['score'] <= 0) {
                continue;
            }
            $seenDocuments[$row['document_id']] = true;
            $attributes = json_decode((string)$row['attributes'], true);
            $keyword = (float)$row['keyword_score'];
            $results[] = [
                'document_id' => (int)$row['document_id'],
                'chunk_id' => (int)$chunkId,
                'title' => (string)$row['title'],
                'text' => (string)$row['text'],
                'url' => $row['url'] !== null ? (string)$row['url'] : null,
                'source_type' => (string)$row['source_type'],
                'attributes' => is_array($attributes) ? $attributes : [],
                'score' => round((float)$row['score'], 4),
                'keyword_score' => round($keyword, 4),
                'vector_score' => $row['vector_score'] !== null ? round((float)$row['vector_score'], 4) : null,
                // 0..1 on both paths: hybrid score as is, raw fulltext relevance squashed (8 -> 0.5, 24 -> 0.75).
                'confidence' => $row['vector_score'] !== null
                    ? round(min(1.0, (float)$row['score']), 4)
                    : round($keyword / ($keyword + 8.0), 4),
            ];
            if (count($results) >= $limit) {
                break;
            }
        }
        return $results;
    }

    /**
     * @return array<int, array> keyed by chunk id
     */
    private function keywordCandidates(string $query, int $storeId, array $sourceTypes): array
    {
        $terms = $this->terms($query);
        if ($terms === []) {
            return [];
        }
        $connection = $this->resource->getConnection();
        $boolean = implode(' ', array_map(function ($term) {
            return mb_strlen($term) >= 3 ? $term . '*' : $term;
        }, $terms));
        $natural = implode(' ', $terms);

        $select = $this->baseSelect($storeId, $sourceTypes)
            ->columns(['keyword_score' => new \Zend_Db_Expr(
                'MATCH(c.text) AGAINST(' . $connection->quote($natural) . ')'
                . ' + 0.5 * MATCH(d.title, d.body) AGAINST(' . $connection->quote($natural) . ')'
            )])
            ->where(
                'MATCH(c.text) AGAINST(' . $connection->quote($boolean) . ' IN BOOLEAN MODE)'
                . ' OR MATCH(d.title, d.body) AGAINST(' . $connection->quote($boolean) . ' IN BOOLEAN MODE)'
            )
            ->order('keyword_score DESC')
            ->limit(self::CANDIDATES);

        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            $rows[(int)$row['chunk_id']] = $row;
        }
        return $rows;
    }

    /**
     * Fallback candidate set for semantic-only matching: embedded chunks of the store, newest documents first.
     *
     * @param int[] $excludeChunkIds
     * @return array<int, array>
     */
    private function recentCandidates(int $storeId, array $sourceTypes, string $model, array $excludeChunkIds): array
    {
        $connection = $this->resource->getConnection();
        $select = $this->baseSelect($storeId, $sourceTypes)
            ->columns(['keyword_score' => new \Zend_Db_Expr('0')])
            ->where('c.embedding IS NOT NULL')
            ->where('c.embedding_model = ?', $model)
            ->order('d.document_id DESC')
            ->limit(2000);
        if ($excludeChunkIds !== []) {
            $select->where('c.chunk_id NOT IN (?)', $excludeChunkIds);
        }
        $rows = [];
        foreach ($connection->fetchAll($select) as $row) {
            $rows[(int)$row['chunk_id']] = $row;
        }
        return $rows;
    }

    private function baseSelect(int $storeId, array $sourceTypes): \Magento\Framework\DB\Select
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['c' => $this->resource->getTableName('wb_aichatbot_kb_chunk')], ['chunk_id', 'document_id', 'text', 'embedding', 'embedding_model'])
            ->join(
                ['d' => $this->resource->getTableName('wb_aichatbot_kb_document')],
                'd.document_id = c.document_id',
                ['title', 'url', 'source_type', 'attributes']
            )
            ->where('d.is_enabled = 1')
            ->where('d.store_id IN (?)', [0, $storeId]);
        if ($sourceTypes !== []) {
            $select->where('d.source_type IN (?)', $sourceTypes);
        }
        return $select;
    }

    /**
     * Admin-written articles are curated answers and outrank generated documents on equal evidence.
     */
    private function sourceBoost(string $sourceType): float
    {
        return $sourceType === Document::SOURCE_MANUAL ? 1.25 : 1.0;
    }

    /**
     * @return string[]
     */
    private function terms(string $query): array
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query)) ?: [];
        $terms = [];
        foreach ($tokens as $token) {
            if (mb_strlen($token) >= 2 && !in_array($token, self::STOPWORDS, true)) {
                $terms[] = $token;
            }
        }
        return array_slice(array_values(array_unique($terms)), 0, 12);
    }
}
