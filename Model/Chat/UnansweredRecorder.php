<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;

class UnansweredRecorder
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

    public function record(Conversation $conversation, ?int $messageId, string $question, ?float $bestScore): void
    {
        $question = trim($question);
        if ($question === '' || $conversation->isPreview()) {
            return;
        }
        $this->resource->getConnection()->insert($this->resource->getTableName('wb_aichatbot_unanswered'), [
            'conversation_id' => (int)$conversation->getId() ?: null,
            'message_id' => $messageId,
            'question' => mb_substr($question, 0, 2000),
            'language' => $conversation->getData('language'),
            'retrieval_score' => $bestScore,
            'status' => 'new',
            'created_at' => $this->dateTime->gmtDate('Y-m-d H:i:s'),
        ]);
    }
}
