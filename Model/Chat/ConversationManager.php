<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use WB\AiChatbot\Model\Chat\ResourceModel\Conversation as ConversationResource;
use WB\AiChatbot\Model\Chat\ResourceModel\Message as MessageResource;
use WB\AiChatbot\Model\Chat\ResourceModel\Message\CollectionFactory as MessageCollectionFactory;

/**
 * Session <-> conversation bookkeeping and message persistence.
 */
class ConversationManager
{
    /**
     * A conversation left idle for this long is closed and a fresh one starts.
     */
    private const IDLE_HOURS = 24;

    /**
     * @var ConversationFactory
     */
    private $conversationFactory;

    /**
     * @var ConversationResource
     */
    private $conversationResource;

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var MessageResource
     */
    private $messageResource;

    /**
     * @var MessageCollectionFactory
     */
    private $messageCollectionFactory;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var DateTime
     */
    private $dateTime;

    public function __construct(
        ConversationFactory $conversationFactory,
        ConversationResource $conversationResource,
        MessageFactory $messageFactory,
        MessageResource $messageResource,
        MessageCollectionFactory $messageCollectionFactory,
        ResourceConnection $resource,
        DateTime $dateTime
    ) {
        $this->conversationFactory = $conversationFactory;
        $this->conversationResource = $conversationResource;
        $this->messageFactory = $messageFactory;
        $this->messageResource = $messageResource;
        $this->messageCollectionFactory = $messageCollectionFactory;
        $this->resource = $resource;
        $this->dateTime = $dateTime;
    }

    /**
     * Current open conversation of a session, or null.
     */
    public function findOpen(string $sessionHash): ?Conversation
    {
        $connection = $this->resource->getConnection();
        $id = (int)$connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('wb_aichatbot_conversation'), 'conversation_id')
                ->where('session_hash = ?', $sessionHash)
                ->where('status IN (?)', [Conversation::STATUS_OPEN, Conversation::STATUS_ESCALATED])
                ->where('last_message_at > ?', $this->dateTime->gmtDate('Y-m-d H:i:s', $this->dateTime->gmtTimestamp() - self::IDLE_HOURS * 3600))
                ->order('conversation_id DESC')
                ->limit(1)
        );
        if (!$id) {
            return null;
        }
        return $this->load($id);
    }

    public function load(int $conversationId): ?Conversation
    {
        $conversation = $this->conversationFactory->create();
        $this->conversationResource->load($conversation, $conversationId);
        return $conversation->getId() ? $conversation : null;
    }

    /**
     * Finds the open conversation or starts one; refreshes customer/context fields either way.
     */
    public function getOrCreate(ChatContext $context): Conversation
    {
        $conversation = $this->findOpen($context->getSessionHash());
        if (!$conversation) {
            $conversation = $this->conversationFactory->create();
            $conversation->addData([
                'session_hash' => $context->getSessionHash(),
                'store_id' => $context->getStoreId(),
                'status' => Conversation::STATUS_OPEN,
                'mode' => Conversation::MODE_ASSIST,
                'language' => $context->getLanguage(),
                'ip_hash' => $context->getIpHash(),
                'page_url_first' => $context->getPageUrl() !== null ? mb_substr($context->getPageUrl(), 0, 512) : null,
                'is_preview' => $context->isPreview() ? 1 : 0,
                'started_at' => $this->dateTime->gmtDate('Y-m-d H:i:s'),
                'last_message_at' => $this->dateTime->gmtDate('Y-m-d H:i:s'),
            ]);
        }
        if ($context->getCustomerId()) {
            $conversation->setData('customer_id', $context->getCustomerId());
        }
        if ($context->getCustomerEmail()) {
            $conversation->setData('customer_email', $context->getCustomerEmail());
        }
        if ($context->getCustomerName()) {
            $conversation->setData('visitor_name', $context->getCustomerName());
        }
        if ($context->getCountry()) {
            $conversation->setData('country', $context->getCountry());
        }
        return $conversation;
    }

    public function save(Conversation $conversation): void
    {
        $this->conversationResource->save($conversation);
    }

    public function close(Conversation $conversation, string $status = Conversation::STATUS_CLOSED): void
    {
        $conversation->setData('status', $status);
        $conversation->setFlowState(null);
        $this->conversationResource->save($conversation);
    }

    public function addMessage(Conversation $conversation, array $data): Message
    {
        $message = $this->messageFactory->create();
        $message->addData($data + [
            'conversation_id' => $conversation->getId(),
            'mode' => $conversation->getData('mode') ?: Conversation::MODE_ASSIST,
            'created_at' => $this->dateTime->gmtDate('Y-m-d H:i:s'),
        ]);
        $this->messageResource->save($message);

        $conversation->setData('messages_count', (int)$conversation->getData('messages_count') + 1);
        $conversation->setData('last_message_at', $this->dateTime->gmtDate('Y-m-d H:i:s'));
        if (($data['role'] ?? '') === Message::ROLE_USER && !$conversation->getData('title')) {
            $conversation->setData('title', mb_substr(trim((string)($data['content'] ?? '')), 0, 255));
        }
        return $message;
    }

    /**
     * @return Message[] oldest first
     */
    public function getHistory(Conversation $conversation, int $limit = 40): array
    {
        $collection = $this->messageCollectionFactory->create()
            ->addFieldToFilter('conversation_id', (int)$conversation->getId())
            ->addFieldToFilter('role', ['in' => [Message::ROLE_USER, Message::ROLE_ASSISTANT]])
            ->setOrder('message_id', 'DESC')
            ->setPageSize($limit);
        $messages = array_values($collection->getItems());
        return array_reverse($messages);
    }

    public function setFeedback(int $messageId, Conversation $conversation, ?int $feedback): bool
    {
        return (bool)$this->resource->getConnection()->update(
            $this->resource->getTableName('wb_aichatbot_message'),
            ['feedback' => $feedback],
            ['message_id = ?' => $messageId, 'conversation_id = ?' => (int)$conversation->getId()]
        );
    }
}
