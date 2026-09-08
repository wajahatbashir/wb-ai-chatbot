<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Fixed-window counters in wb_aichatbot_rate_limit (works without Redis, safe under concurrency).
 */
class RateLimiter
{
    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var DateTime
     */
    private $dateTime;

    public function __construct(ResourceConnection $resource, DateTime $dateTime)
    {
        $this->resource = $resource;
        $this->dateTime = $dateTime;
    }

    /**
     * Counts one hit and tells whether the limit is still respected. $limit <= 0 disables the limit.
     */
    public function hit(string $key, int $limit, int $windowSeconds): bool
    {
        if ($limit <= 0) {
            return true;
        }
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('wb_aichatbot_rate_limit');
        $now = $this->dateTime->gmtTimestamp();
        $windowStart = $this->dateTime->gmtDate('Y-m-d H:i:s', $now - ($now % $windowSeconds));
        $limitKey = mb_substr(hash('sha256', $key), 0, 48) . ':' . $windowSeconds;

        // Row starts at 0; the UPDATE below is what counts this hit (a stale window resets to 1).
        $connection->insertOnDuplicate($table, [
            'limit_key' => $limitKey,
            'window_start' => $windowStart,
            'count' => 0,
        ], []);
        $connection->query(
            'UPDATE ' . $table . ' SET count = IF(window_start = :ws, count + 1, 1), window_start = :ws WHERE limit_key = :k',
            ['ws' => $windowStart, 'k' => $limitKey]
        );
        $count = (int)$connection->fetchOne(
            $connection->select()->from($table, 'count')->where('limit_key = ?', $limitKey)
        );
        return $count <= $limit;
    }

    /**
     * Read-only check (no increment).
     */
    public function count(string $key, int $windowSeconds): int
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('wb_aichatbot_rate_limit');
        $now = $this->dateTime->gmtTimestamp();
        $windowStart = $this->dateTime->gmtDate('Y-m-d H:i:s', $now - ($now % $windowSeconds));
        $limitKey = mb_substr(hash('sha256', $key), 0, 48) . ':' . $windowSeconds;
        $row = $connection->fetchRow(
            $connection->select()->from($table, ['count', 'window_start'])->where('limit_key = ?', $limitKey)
        );
        if (!$row || $row['window_start'] !== $windowStart) {
            return 0;
        }
        return (int)$row['count'];
    }
}
