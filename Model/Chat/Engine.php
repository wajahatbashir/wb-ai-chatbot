<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

use WB\AiChatbot\Model\Config;
use WB\AiChatbot\Model\Logger;
use WB\AiChatbot\Model\Provider\NoProviderException;
use WB\AiChatbot\Model\Provider\Pool;
use WB\AiChatbot\Model\Provider\ProviderException;

/**
 * Entry point for every customer message: limits -> conversation -> Q&A / flows / verification -> AI or Assist
 * -> persistence and accounting. The customer never sees a provider error; Assist mode is the safety net.
 */
class Engine
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Pool
     */
    private $pool;

    /**
     * @var ConversationManager
     */
    private $conversations;

    /**
     * @var RateLimiter
     */
    private $rateLimiter;

    /**
     * @var QaMatcher
     */
    private $qaMatcher;

    /**
     * @var GuidedFlow
     */
    private $flow;

    /**
     * @var Verification
     */
    private $verification;

    /**
     * @var Orchestrator
     */
    private $orchestrator;

    /**
     * @var AssistEngine
     */
    private $assist;

    /**
     * @var Guard
     */
    private $guard;

    /**
     * @var UsageRecorder
     */
    private $usage;

    /**
     * @var UnansweredRecorder
     */
    private $unanswered;

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var \WB\AiChatbot\Api\CredentialRepositoryInterface
     */
    private $credentialRepository;

    public function __construct(
        \WB\AiChatbot\Api\CredentialRepositoryInterface $credentialRepository,
        Config $config,
        Pool $pool,
        ConversationManager $conversations,
        RateLimiter $rateLimiter,
        QaMatcher $qaMatcher,
        GuidedFlow $flow,
        Verification $verification,
        Orchestrator $orchestrator,
        AssistEngine $assist,
        Guard $guard,
        UsageRecorder $usage,
        UnansweredRecorder $unanswered,
        Logger $logger
    ) {
        $this->credentialRepository = $credentialRepository;
        $this->config = $config;
        $this->pool = $pool;
        $this->conversations = $conversations;
        $this->rateLimiter = $rateLimiter;
        $this->qaMatcher = $qaMatcher;
        $this->flow = $flow;
        $this->verification = $verification;
        $this->orchestrator = $orchestrator;
        $this->assist = $assist;
        $this->guard = $guard;
        $this->usage = $usage;
        $this->unanswered = $unanswered;
        $this->logger = $logger;
    }

    /**
     * @param array $attachments [['name' => .., 'mime' => .., 'data' => base64], ...]
     */
    public function handle(ChatContext $context, string $message, array $attachments = []): ChatReply
    {
        $storeId = $context->getStoreId();
        $message = $this->guard->cleanUserMessage($message, $this->config->getMaxMessageLength());
        if ($message === '' && $attachments === []) {
            return ChatReply::create('Please type a message.');
        }
        if (!$this->rateLimiter->hit('msg:' . $context->getSessionHash(), $this->config->getMessagesPerMinute(), 60)) {
            return ChatReply::create('You are sending messages very quickly - please wait a moment and try again.');
        }

        $conversation = $this->conversations->findOpen($context->getSessionHash());
        if (!$conversation) {
            if (!$context->isPreview()
                && !$this->rateLimiter->hit('conv:' . (string)$context->getIpHash(), $this->config->getConversationsPerDay(), 86400)) {
                return ChatReply::create('The chat is unavailable for you right now. Please try again later or use the contact page.');
            }
            $conversation = $this->conversations->getOrCreate($context);
            $this->conversations->save($conversation);
            $this->usage->record($storeId, 0, 1, 0, 0, 0, 0.0);
        } else {
            $conversation = $this->conversations->getOrCreate($context);
        }
        $context->setConversation($conversation);

        $started = microtime(true);
        $redact = $this->config->isPiiRedacted();
        $userMessage = $this->conversations->addMessage($conversation, [
            'role' => Message::ROLE_USER,
            'content' => $redact ? $this->guard->redact($message) : $message,
            'attachments' => array_map(function ($a) {
                return ['name' => $a['name'] ?? 'image', 'mime' => $a['mime'] ?? '', 'size' => isset($a['data']) ? (int)(strlen($a['data']) * 3 / 4) : 0];
            }, $attachments),
        ]);

        try {
            $reply = $this->respond($context, $message, $attachments);
        } catch (\Throwable $e) {
            $this->logger->error('Chat engine failure: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $reply = ChatReply::create('Sorry, something went wrong on my side. Please try again in a moment.')
                ->setChips($this->flow->menuChips($context));
        }

        $meta = $reply->getMeta() ?? [];
        $assistantMessage = $this->conversations->addMessage($conversation, [
            'role' => Message::ROLE_ASSISTANT,
            'content' => $redact ? $this->guard->redact($reply->getText()) : $reply->getText(),
            'cards' => $reply->getCards(),
            'sources' => $reply->getSources(),
            'tool_calls' => $meta['tool_calls'] ?? [],
            'credential_id' => $meta['credential_id'] ?? null,
            'model' => $meta['model'] ?? null,
            'mode' => $reply->getMode(),
            'prompt_tokens' => (int)($meta['prompt_tokens'] ?? 0),
            'completion_tokens' => (int)($meta['completion_tokens'] ?? 0),
            'latency_ms' => (int)round((microtime(true) - $started) * 1000),
            'was_grounded' => $reply->isGrounded() ? 1 : 0,
        ]);
        $reply->setMessageId($assistantMessage->getMessageId());

        $conversation->setData('mode', $reply->getMode());
        $conversation->setData('prompt_tokens', (int)$conversation->getData('prompt_tokens') + (int)($meta['prompt_tokens'] ?? 0));
        $conversation->setData('completion_tokens', (int)$conversation->getData('completion_tokens') + (int)($meta['completion_tokens'] ?? 0));
        $conversation->setData('cost', (float)$conversation->getData('cost') + (float)($meta['cost'] ?? 0));
        $conversation->setData('language', $context->getLanguage());
        $this->conversations->save($conversation);
        $this->usage->record(
            $storeId,
            (int)($meta['credential_id'] ?? 0),
            0,
            1,
            (int)($meta['prompt_tokens'] ?? 0),
            (int)($meta['completion_tokens'] ?? 0),
            (float)($meta['cost'] ?? 0)
        );
        if ($reply->isUnanswered()) {
            $this->unanswered->record($conversation, $userMessage->getMessageId(), $message, $reply->getSources()[0]['confidence'] ?? null);
        }
        return $reply;
    }

    private function respond(ChatContext $context, string $message, array $attachments): ChatReply
    {
        $conversation = $context->getConversation();
        $storeId = $context->getStoreId();

        // 1. A guided flow in progress owns the message (unless the customer changed topic).
        $flowReply = $this->flow->resume($context, $message);
        if ($flowReply) {
            return $flowReply->setMode(Conversation::MODE_ASSIST);
        }

        // 2. A verification code typed after we emailed one (works identically in both modes).
        $pendingEmail = $this->verification->getPendingEmail($conversation);
        if ($pendingEmail && ($code = Text::looksLikeCode($message))) {
            $verified = $this->verification->verify($context, $pendingEmail, $code);
            $reply = ChatReply::create($verified['ok']
                ? $verified['message'] . ' What would you like to check?'
                : $verified['message']);
            $reply->setChips([
                ['label' => 'Order status', 'send' => 'What is the status of my order?'],
                ['label' => 'Track my order', 'send' => 'Track my order'],
                ['label' => 'Get my invoice', 'send' => 'I need the invoice for my order'],
            ]);
            return $reply->setMode($this->aiAvailable() ? Conversation::MODE_AI : Conversation::MODE_ASSIST)->setGrounded(true);
        }

        // 3. Admin Q&A pairs beat everything else - deterministic and free.
        $qa = $this->qaMatcher->match($message, $storeId);
        if ($qa) {
            $reply = ChatReply::create($qa['answer'])->setGrounded(true)->setSources([['title' => $qa['question'], 'url' => $qa['url'], 'confidence' => $qa['similarity']]]);
            if ($qa['url']) {
                $reply->addCard(['type' => 'link', 'label' => 'Read more', 'url' => $qa['url']]);
            }
            return $reply->setChips($this->flow->menuChips($context))->setMode(Conversation::MODE_ASSIST);
        }

        // 4. AI when possible, Assist otherwise - and Assist if every provider fails mid-request.
        if ($this->aiAvailable()) {
            try {
                $history = $this->conversations->getHistory($conversation, $this->config->getMaxMessages($storeId));
                array_pop($history); // the message just stored
                return $this->orchestrator->respond($context, $message, $history, $attachments);
            } catch (NoProviderException | ProviderException $e) {
                $this->logger->warning('AI unavailable for this request, using Assist mode: ' . $e->getMessage());
                $reply = $this->assist->respond($context, $message);
                if ($this->config->isAssistNoticeShown($storeId)) {
                    $reply->setNotice($this->config->getAssistNoticeText($storeId));
                }
                return $reply->setMode(Conversation::MODE_ASSIST);
            }
        }
        $reply = $this->assist->respond($context, $message);
        if ($this->config->getMode($storeId) !== Config::MODE_ASSIST && $this->config->isAssistNoticeShown($storeId) && $this->pool->getChatCandidates() === []
            && $this->hasAnyEnabledCredential()) {
            // Keys exist but none is usable right now (invalid / quota / outage): tell the customer, gently.
            $reply->setNotice($this->config->getAssistNoticeText($storeId));
        }
        return $reply->setMode(Conversation::MODE_ASSIST);
    }

    private function aiAvailable(): bool
    {
        if (!$this->pool->isAiAvailable()) {
            return false;
        }
        $budget = $this->config->getDailyTokenBudget();
        if ($budget > 0 && $this->usage->getTokensToday() >= $budget) {
            $this->logger->warning('Daily token budget reached; serving Assist mode');
            return false;
        }
        return true;
    }

    /**
     * True when the merchant configured at least one real (non-mock) key - i.e. AI was expected to work.
     */
    private function hasAnyEnabledCredential(): bool
    {
        try {
            foreach ($this->credentialRepository->getEnabledOrdered() as $credential) {
                if ($credential->getProviderCode() !== \WB\AiChatbot\Model\Provider\Mock::CODE) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    }
}
