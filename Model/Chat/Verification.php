<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use WB\AiChatbot\Model\Config;
use WB\AiChatbot\Model\Logger;

/**
 * Email verification for guest self-service: 6-digit code, configurable TTL (default 10 min), single use,
 * 5 attempts, rate-limited per email and per IP. A verified address is remembered for the conversation.
 */
class Verification
{
    public const TEMPLATE = 'wb_aichatbot_verification_code';
    private const MAX_ATTEMPTS = 5;

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
     * @var RateLimiter
     */
    private $rateLimiter;

    /**
     * @var Mailer
     */
    private $mailer;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(
        ResourceConnection $resource,
        DateTime $dateTime,
        Config $config,
        RateLimiter $rateLimiter,
        Mailer $mailer,
        Logger $logger
    ) {
        $this->resource = $resource;
        $this->dateTime = $dateTime;
        $this->config = $config;
        $this->rateLimiter = $rateLimiter;
        $this->mailer = $mailer;
        $this->logger = $logger;
    }

    /**
     * @return array{ok:bool, error?:string, message:string}
     */
    public function send(ChatContext $context, string $email): array
    {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'invalid_email', 'message' => 'That does not look like a valid email address.'];
        }
        $conversation = $context->getConversation();
        if ($conversation->isEmailVerified($email)) {
            return ['ok' => true, 'already_verified' => true, 'message' => 'This email address is already verified for this chat.'];
        }
        if (!$this->rateLimiter->hit('code:email:' . $email, $this->config->getCodesPerHourPerEmail(), 3600)
            || !$this->rateLimiter->hit('code:ip:' . (string)$context->getIpHash(), $this->config->getCodesPerHourPerIp(), 3600)) {
            return ['ok' => false, 'error' => 'rate_limited', 'message' => 'Too many verification codes were requested. Please try again in an hour.'];
        }

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $ttl = max(1, $this->config->getCodeTtlMinutes());
        $connection = $this->resource->getConnection();
        $connection->insert($this->resource->getTableName('wb_aichatbot_verification_code'), [
            'email' => $email,
            'code_hash' => $this->hash($code, $email),
            'conversation_id' => (int)$conversation->getId(),
            'ip_hash' => $context->getIpHash(),
            'attempts' => 0,
            'expires_at' => $this->dateTime->gmtDate('Y-m-d H:i:s', $this->dateTime->gmtTimestamp() + $ttl * 60),
            'created_at' => $this->dateTime->gmtDate('Y-m-d H:i:s'),
        ]);

        $sent = $this->mailer->send($this->config->getEmailTemplate('verification', $context->getStoreId()), $context->getStoreId(), $email, null, [
            'code' => $code,
            'ttl_minutes' => $ttl,
        ]);
        if ($this->config->isMockAllowed() && $this->config->isDebugLog()) {
            // Development only (both developer flags must be on): lets local tests read the code from the log.
            $this->logger->debug(sprintf('Verification code for %s: %s', $email, $code));
        }
        if (!$sent) {
            return ['ok' => false, 'error' => 'send_failed', 'message' => 'The verification email could not be sent right now. Please try again later.'];
        }
        return ['ok' => true, 'message' => sprintf('A 6-digit verification code was sent to %s. It expires in %d minutes.', $email, $ttl)];
    }

    /**
     * @return array{ok:bool, error?:string, message:string}
     */
    public function verify(ChatContext $context, string $email, string $code): array
    {
        $email = mb_strtolower(trim($email));
        $code = preg_replace('/\D+/', '', $code) ?? '';
        $conversation = $context->getConversation();
        if ($conversation->isEmailVerified($email)) {
            return ['ok' => true, 'message' => 'Email already verified.'];
        }
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('wb_aichatbot_verification_code');
        $row = $connection->fetchRow(
            $connection->select()->from($table)
                ->where('email = ?', $email)
                ->where('used_at IS NULL')
                ->where('expires_at > ?', $this->dateTime->gmtDate('Y-m-d H:i:s'))
                ->where('attempts < ?', self::MAX_ATTEMPTS)
                ->order('code_id DESC')
                ->limit(1)
        );
        if (!$row) {
            return ['ok' => false, 'error' => 'no_code', 'message' => 'There is no active code for this email. Please request a new one.'];
        }
        if (strlen($code) !== 6 || !hash_equals((string)$row['code_hash'], $this->hash($code, $email))) {
            $connection->update($table, ['attempts' => new \Zend_Db_Expr('attempts + 1')], ['code_id = ?' => (int)$row['code_id']]);
            $left = self::MAX_ATTEMPTS - (int)$row['attempts'] - 1;
            return [
                'ok' => false,
                'error' => 'wrong_code',
                'message' => $left > 0
                    ? sprintf('That code is not correct. %d attempt(s) left.', $left)
                    : 'That code is not correct and no attempts are left. Please request a new code.',
            ];
        }
        $connection->update($table, ['used_at' => $this->dateTime->gmtDate('Y-m-d H:i:s')], ['code_id = ?' => (int)$row['code_id']]);
        $conversation->addVerifiedEmail($email);
        return ['ok' => true, 'message' => 'Thank you, your email is verified.'];
    }

    /**
     * Email with an active, unused code in this conversation (the customer is expected to type it next).
     */
    public function getPendingEmail(Conversation $conversation): ?string
    {
        if (!$conversation->getId()) {
            return null;
        }
        $connection = $this->resource->getConnection();
        $email = $connection->fetchOne(
            $connection->select()->from($this->resource->getTableName('wb_aichatbot_verification_code'), 'email')
                ->where('conversation_id = ?', (int)$conversation->getId())
                ->where('used_at IS NULL')
                ->where('expires_at > ?', $this->dateTime->gmtDate('Y-m-d H:i:s'))
                ->where('attempts < ?', self::MAX_ATTEMPTS)
                ->order('code_id DESC')
                ->limit(1)
        );
        return $email ? (string)$email : null;
    }

    private function hash(string $code, string $email): string
    {
        return hash('sha256', $code . '|' . $email);
    }
}
