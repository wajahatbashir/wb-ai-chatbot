<?php
declare(strict_types=1);

namespace WB\AiChatbot\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use WB\AiChatbot\Api\CredentialRepositoryInterface;
use WB\AiChatbot\Model\Config;
use WB\AiChatbot\Model\Kb\Indexer;
use WB\AiChatbot\Model\Provider\Pool;

class Dashboard extends Template
{
    /**
     * @var string
     */
    protected $_template = 'WB_AiChatbot::dashboard.phtml';

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var Pool
     */
    private $pool;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Indexer
     */
    private $indexer;

    /**
     * @var CredentialRepositoryInterface
     */
    private $credentials;

    public function __construct(
        Context $context,
        ResourceConnection $resource,
        DateTime $dateTime,
        Pool $pool,
        Config $config,
        Indexer $indexer,
        CredentialRepositoryInterface $credentials,
        array $data = []
    ) {
        $this->resource = $resource;
        $this->dateTime = $dateTime;
        $this->pool = $pool;
        $this->config = $config;
        $this->indexer = $indexer;
        $this->credentials = $credentials;
        parent::__construct($context, $data);
    }

    public function getStats(): array
    {
        $connection = $this->resource->getConnection();
        $conv = $this->resource->getTableName('wb_aichatbot_conversation');
        $msg = $this->resource->getTableName('wb_aichatbot_message');
        $today = $this->dateTime->gmtDate('Y-m-d');
        $yesterday = $this->dateTime->gmtDate('Y-m-d', $this->dateTime->gmtTimestamp() - 86400);
        $weekAgo = $this->dateTime->gmtDate('Y-m-d H:i:s', $this->dateTime->gmtTimestamp() - 7 * 86400);
        $count = function (string $table, string $where, array $bind = []) use ($connection): int {
            return (int)$connection->fetchOne($connection->select()->from($table, 'COUNT(*)')->where($where, ...$bind));
        };
        $base = 'is_preview = 0';
        $stats = [
            'today' => $count($conv, $base . ' AND DATE(started_at) = ?', [$today]),
            'yesterday' => $count($conv, $base . ' AND DATE(started_at) = ?', [$yesterday]),
            'week' => $count($conv, $base . ' AND started_at >= ?', [$weekAgo]),
            'active' => $count($conv, $base . ' AND status = "open" AND last_message_at >= ?', [$this->dateTime->gmtDate('Y-m-d H:i:s', $this->dateTime->gmtTimestamp() - 900)]),
            'messages_week' => (int)$connection->fetchOne(
                $connection->select()->from(['m' => $msg], 'COUNT(*)')->join(['c' => $conv], 'c.conversation_id = m.conversation_id', [])
                    ->where('c.is_preview = 0')->where('m.role = ?', 'user')->where('m.created_at >= ?', $weekAgo)
            ),
            'ai_share' => 0.0,
            'rating_avg' => (float)$connection->fetchOne($connection->select()->from($conv, 'AVG(rating)')->where($base . ' AND rating IS NOT NULL')),
            'rating_count' => $count($conv, $base . ' AND rating IS NOT NULL'),
            'thumbs_up' => $count($msg, 'feedback = 1'),
            'thumbs_down' => $count($msg, 'feedback = -1'),
            'escalated' => $count($conv, $base . ' AND status = "escalated" AND started_at >= ?', [$weekAgo]),
            'unanswered_new' => $count($this->resource->getTableName('wb_aichatbot_unanswered'), 'status = "new"'),
            'requests_open' => $count($this->resource->getTableName('wb_aichatbot_request'), 'status IN ("new", "in_progress")'),
        ];
        $modes = $connection->fetchPairs(
            $connection->select()->from(['m' => $msg], ['mode', 'cnt' => new \Zend_Db_Expr('COUNT(*)')])
                ->join(['c' => $conv], 'c.conversation_id = m.conversation_id', [])
                ->where('c.is_preview = 0')->where('m.role = ?', 'assistant')->where('m.created_at >= ?', $weekAgo)->group('m.mode')
        );
        $total = array_sum($modes);
        $stats['ai_share'] = $total > 0 ? round(100 * (int)($modes['ai'] ?? 0) / $total) : 0;
        return $stats;
    }

    /**
     * @return array<int, array{date: string, conversations: int, messages: int, tokens: int, cost: float}>
     */
    public function getDaily(int $days = 14): array
    {
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()->from($this->resource->getTableName('wb_aichatbot_daily_usage'), [
                'usage_date',
                'conversations' => new \Zend_Db_Expr('SUM(conversations)'),
                'messages' => new \Zend_Db_Expr('SUM(messages)'),
                'tokens' => new \Zend_Db_Expr('SUM(prompt_tokens + completion_tokens)'),
                'cost' => new \Zend_Db_Expr('SUM(cost)'),
            ])->where('usage_date >= ?', $this->dateTime->gmtDate('Y-m-d', $this->dateTime->gmtTimestamp() - ($days - 1) * 86400))
                ->group('usage_date')->order('usage_date ASC')
        );
        $byDate = [];
        foreach ($rows as $row) {
            $byDate[$row['usage_date']] = $row;
        }
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $this->dateTime->gmtDate('Y-m-d', $this->dateTime->gmtTimestamp() - $i * 86400);
            $row = $byDate[$date] ?? [];
            $series[] = [
                'date' => $date,
                'conversations' => (int)($row['conversations'] ?? 0),
                'messages' => (int)($row['messages'] ?? 0),
                'tokens' => (int)($row['tokens'] ?? 0),
                'cost' => (float)($row['cost'] ?? 0),
            ];
        }
        return $series;
    }

    public function getCostByCredential(int $days = 14): array
    {
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()->from($this->resource->getTableName('wb_aichatbot_daily_usage'), [
                'credential_id', 'messages' => new \Zend_Db_Expr('SUM(messages)'),
                'tokens' => new \Zend_Db_Expr('SUM(prompt_tokens + completion_tokens)'), 'cost' => new \Zend_Db_Expr('SUM(cost)'),
            ])->where('usage_date >= ?', $this->dateTime->gmtDate('Y-m-d', $this->dateTime->gmtTimestamp() - ($days - 1) * 86400))
                ->group('credential_id')->order('cost DESC')
        );
        $names = [];
        foreach ($this->credentials->getEnabledOrdered() as $credential) {
            $names[(int)$credential->getCredentialId()] = $credential->getAlias() . ' (' . $credential->getProviderCode() . ')';
        }
        foreach ($rows as &$row) {
            $id = (int)$row['credential_id'];
            $row['label'] = $id === 0 ? (string)__('Assist mode (no AI)') : ($names[$id] ?? (string)__('Credential #%1', $id));
        }
        return $rows;
    }

    public function getTopQuestions(int $limit = 8): array
    {
        $connection = $this->resource->getConnection();
        return $connection->fetchAll(
            $connection->select()->from(['m' => $this->resource->getTableName('wb_aichatbot_message')], ['content', 'cnt' => new \Zend_Db_Expr('COUNT(*)')])
                ->join(['c' => $this->resource->getTableName('wb_aichatbot_conversation')], 'c.conversation_id = m.conversation_id', [])
                ->where('c.is_preview = 0')->where('m.role = ?', 'user')->where('LENGTH(m.content) BETWEEN 8 AND 120')
                ->group('m.content')->order('cnt DESC')->limit($limit)
        );
    }

    public function getEngine(): array
    {
        $candidates = $this->pool->getChatCandidates();
        return [
            'enabled' => $this->config->isEnabled(),
            'mode' => $this->config->getMode(),
            'ai_available' => $this->pool->isAiAvailable(),
            'candidates' => count($candidates),
            'first' => $candidates !== [] ? $candidates[0]->getAlias() . ' / ' . ($candidates[0]->getModel() ?: 'default model') : null,
            'kb' => $this->indexer->getStatus(),
        ];
    }

    public function url(string $route, array $params = []): string
    {
        return $this->getUrl($route, $params);
    }
}
