<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use WB\AiChatbot\Model\Config;

/**
 * Built-in support requests ("tickets"): created from the chat, listed and answered in admin.
 */
class RequestManager
{
    public const TEMPLATE_CONFIRMATION = 'wb_aichatbot_request_confirmation';
    public const TEMPLATE_ADMIN = 'wb_aichatbot_request_admin';
    public const TEMPLATE_REPLY = 'wb_aichatbot_request_reply';

    public const STATUS_NEW = 'new';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CLOSED = 'closed';

    public const STATUS_LABELS = [
        self::STATUS_NEW => 'Received',
        self::STATUS_IN_PROGRESS => 'In progress',
        self::STATUS_RESOLVED => 'Resolved',
        self::STATUS_CLOSED => 'Closed',
    ];

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Mailer
     */
    private $mailer;

    public function __construct(ResourceConnection $resource, DateTime $dateTime, Config $config, Mailer $mailer)
    {
        $this->resource = $resource;
        $this->dateTime = $dateTime;
        $this->config = $config;
        $this->mailer = $mailer;
    }

    /**
     * @return array{ok:bool, code?:string, message:string, error?:string}
     */
    public function submit(ChatContext $context, string $email, ?string $name, string $subject, string $message, ?string $orderIncrementId = null): array
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'invalid_email', 'message' => 'Please give a valid email address so we can reply.'];
        }
        $subject = trim($subject) !== '' ? mb_substr(trim($subject), 0, 255) : mb_substr(trim($message), 0, 80);
        $message = trim($message);
        if ($message === '') {
            return ['ok' => false, 'error' => 'empty', 'message' => 'Please describe what you need help with.'];
        }
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('wb_aichatbot_request');
        do {
            $code = 'SR-' . strtoupper(substr(str_replace(['0', 'O', 'I', '1'], ['2', '3', '4', '5'], bin2hex(random_bytes(4))), 0, 6));
            $exists = (int)$connection->fetchOne($connection->select()->from($table, 'COUNT(*)')->where('code = ?', $code));
        } while ($exists > 0);

        $conversation = $context->getConversation();
        $connection->insert($table, [
            'code' => $code,
            'conversation_id' => (int)$conversation->getId() ?: null,
            'store_id' => $context->getStoreId(),
            'email' => $email,
            'name' => $name !== null && trim($name) !== '' ? mb_substr(trim($name), 0, 255) : null,
            'subject' => $subject,
            'message' => $message,
            'order_increment_id' => $orderIncrementId !== null && $orderIncrementId !== '' ? mb_substr($orderIncrementId, 0, 50) : null,
            'status' => self::STATUS_NEW,
            'created_at' => $this->dateTime->gmtDate('Y-m-d H:i:s'),
        ]);
        $conversation->setData('status', Conversation::STATUS_ESCALATED);

        $vars = [
            'code' => $code,
            'subject' => $subject,
            'message' => $message,
            'customer_email' => $email,
            'customer_name' => $name ?: $email,
            'order_increment_id' => $orderIncrementId ?: '',
        ];
        $storeId = $context->getStoreId();
        $this->mailer->send($this->config->getEmailTemplate('request_confirmation', $storeId), $storeId, $email, $name, $vars);
        if ($this->config->isNotifyOnRequest()) {
            $adminEmail = $this->config->getNotificationEmail();
            if ($adminEmail !== '') {
                $this->mailer->send($this->config->getEmailTemplate('request_admin', $storeId), $storeId, $adminEmail, null, $vars);
            }
        }
        return [
            'ok' => true,
            'code' => $code,
            'message' => sprintf('Your support request %s has been created. We emailed a confirmation to %s and our team will reply there.', $code, $email),
        ];
    }

    /**
     * @return array|null request summary, only when the email matches
     */
    public function find(string $code, string $email): ?array
    {
        $connection = $this->resource->getConnection();
        $row = $connection->fetchRow(
            $connection->select()->from($this->resource->getTableName('wb_aichatbot_request'))
                ->where('code = ?', strtoupper(trim($code)))
                ->where('email = ?', mb_strtolower(trim($email)))
        );
        if (!$row) {
            return null;
        }
        return [
            'code' => $row['code'],
            'status' => $row['status'],
            'status_label' => self::STATUS_LABELS[$row['status']] ?? $row['status'],
            'subject' => $row['subject'],
            'message' => $row['message'],
            'order_increment_id' => $row['order_increment_id'],
            'created_at' => $row['created_at'],
            'admin_reply' => $row['admin_reply'],
            'replied_at' => $row['replied_at'],
        ];
    }
}
