<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Daily rollups for the dashboard and the token budget (credential 0 = Assist mode).
 */
class UsageRecorder
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

    public function record(int $storeId, int $credentialId, int $conversations, int $messages, int $promptTokens, int $completionTokens, float $cost): void
    {
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('wb_aichatbot_daily_usage');
        $connection->query(
            'INSERT INTO ' . $table
            . ' (usage_date, store_id, credential_id, conversations, messages, prompt_tokens, completion_tokens, cost)'
            . ' VALUES (:d, :s, :cr, :c, :m, :p, :o, :k)'
            . ' ON DUPLICATE KEY UPDATE conversations = conversations + VALUES(conversations), messages = messages + VALUES(messages),'
            . ' prompt_tokens = prompt_tokens + VALUES(prompt_tokens), completion_tokens = completion_tokens + VALUES(completion_tokens),'
            . ' cost = cost + VALUES(cost)',
            [
                'd' => $this->dateTime->gmtDate('Y-m-d'), 's' => $storeId, 'cr' => $credentialId,
                'c' => $conversations, 'm' => $messages, 'p' => $promptTokens, 'o' => $completionTokens, 'k' => $cost,
            ]
        );
    }

    /**
     * Tokens used today across all stores/credentials (for the daily budget).
     */
    public function getTokensToday(): int
    {
        $connection = $this->resource->getConnection();
        return (int)$connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('wb_aichatbot_daily_usage'), new \Zend_Db_Expr('SUM(prompt_tokens + completion_tokens)'))
                ->where('usage_date = ?', $this->dateTime->gmtDate('Y-m-d'))
        );
    }
}
