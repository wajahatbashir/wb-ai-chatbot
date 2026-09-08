<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

use WB\AiChatbot\Model\Chat\Tool\ToolRegistry;
use WB\AiChatbot\Model\Chat\Tool\ToolResult;
use WB\AiChatbot\Model\Config;

/**
 * Deterministic, button-driven flows for Assist mode (no AI needed): order status / tracking / invoice,
 * support request status, and creating a support request. State lives on the conversation (flow_state).
 */
class GuidedFlow
{
    public const FLOW_ORDER_STATUS = 'order_status';
    public const FLOW_ORDER_TRACKING = 'order_tracking';
    public const FLOW_ORDER_INVOICE = 'order_invoice';
    public const FLOW_REQUEST_STATUS = 'request_status';
    public const FLOW_REQUEST_SUBMIT = 'request_submit';

    private const ORDER_FLOWS = [
        self::FLOW_ORDER_STATUS => 'order_info',
        self::FLOW_ORDER_TRACKING => 'order_tracking',
        self::FLOW_ORDER_INVOICE => 'order_invoice',
    ];

    private const MAX_RETRIES = 2;

    /**
     * @var ToolRegistry
     */
    private $tools;

    /**
     * @var Verification
     */
    private $verification;

    /**
     * @var IntentRouter
     */
    private $intentRouter;

    /**
     * @var Config
     */
    private $config;

    public function __construct(ToolRegistry $tools, Verification $verification, IntentRouter $intentRouter, Config $config)
    {
        $this->tools = $tools;
        $this->verification = $verification;
        $this->intentRouter = $intentRouter;
        $this->config = $config;
    }

    public static function isFlow(string $code): bool
    {
        return isset(self::ORDER_FLOWS[$code]) || in_array($code, [self::FLOW_REQUEST_STATUS, self::FLOW_REQUEST_SUBMIT], true);
    }

    public function start(string $flow, ChatContext $context, string $message): ChatReply
    {
        $state = ['flow' => $flow, 'step' => null, 'data' => [], 'retries' => 0];
        $this->absorb($state, $context, $message);
        return $this->advance($state, $context);
    }

    /**
     * Continues the pending flow. Returns null when the customer clearly changed topic (state is cleared).
     */
    public function resume(ChatContext $context, string $message): ?ChatReply
    {
        $state = $context->getConversation()->getFlowState();
        if ($state === [] || !self::isFlow((string)($state['flow'] ?? ''))) {
            return null;
        }
        $intent = $this->intentRouter->detect($message, $context->getLanguage());
        if ($intent['intent'] === IntentRouter::INTENT_CANCEL) {
            $this->clear($context);
            return ChatReply::create('No problem, I have cancelled that. What else can I help you with?')->setChips($this->menuChips($context));
        }
        $step = (string)($state['step'] ?? '');
        $before = $state['data'];
        $this->absorb($state, $context, $message, $step);
        if ($state['data'] === $before) {
            // Nothing usable in the message. A strong, different intent means the customer moved on.
            if ($intent['score'] >= 30 && !in_array($intent['intent'], [IntentRouter::INTENT_UNKNOWN, IntentRouter::INTENT_GREETING], true)
                && count(Text::tokens($message)) >= 3) {
                $this->clear($context);
                return null;
            }
            $state['retries'] = (int)($state['retries'] ?? 0) + 1;
            if ($state['retries'] > self::MAX_RETRIES) {
                $this->clear($context);
                return ChatReply::create("I could not get what I need for that. Let's start again whenever you are ready, or I can create a support request for our team.")
                    ->setChips($this->menuChips($context));
            }
        }
        return $this->advance($state, $context);
    }

    /**
     * Pulls order numbers, emails, codes and free text out of the message into the flow data.
     */
    private function absorb(array &$state, ChatContext $context, string $message, ?string $step = null): void
    {
        $flow = $state['flow'];
        $data = &$state['data'];
        if ($step === 'code') {
            $code = Text::looksLikeCode($message);
            if ($code) {
                $data['code'] = $code;
                return;
            }
        }
        if (isset(self::ORDER_FLOWS[$flow]) || $flow === self::FLOW_REQUEST_STATUS) {
            if ($flow === self::FLOW_REQUEST_STATUS && preg_match('/\bSR-[A-Z0-9]{6}\b/i', $message, $m)) {
                $data['request_code'] = strtoupper($m[0]);
            }
            if (isset(self::ORDER_FLOWS[$flow]) && ($order = Text::looksLikeOrderNumber($message))) {
                $data['order_number'] = $order;
            }
            if ($email = Text::looksLikeEmail($message)) {
                $data['email'] = $email;
            }
            return;
        }
        if ($flow === self::FLOW_REQUEST_SUBMIT) {
            if ($step === 'email' || empty($data['email'])) {
                if ($email = Text::looksLikeEmail($message)) {
                    $data['email'] = $email;
                    if ($step === 'email') {
                        return;
                    }
                }
            }
            if ($step === 'message') {
                $data['message'] = trim($message);
                return;
            }
            if ($step === null && !Text::looksLikeEmail($message)) {
                // The opening message may already describe the problem ("I want to talk to someone, my ring arrived
                // broken") - but a bare "I want to talk to a human" is only the trigger, not the description.
                $stripped = preg_replace(
                    '/\b(i (want|need|would like|d like) to )?(talk|speak|chat) (to|with) (a |an |some)?(human|person|someone|agent|representative|real person|support|customer (service|support))\b|\b(customer (service|support)|contact (support|you)|support request|raise a ticket|open a ticket|complaint)\b/i',
                    ' ',
                    $message
                ) ?? $message;
                if (count(Text::tokens($stripped)) >= 6) {
                    $data['message'] = trim($message);
                }
            }
            if ($order = Text::looksLikeOrderNumber($message)) {
                $data['order_number'] = $order;
            }
        }
    }

    private function advance(array $state, ChatContext $context): ChatReply
    {
        $flow = $state['flow'];
        $data = $state['data'];
        $conversation = $context->getConversation();

        if ($flow === self::FLOW_REQUEST_SUBMIT) {
            if (empty($data['email']) && $context->getCustomerEmail()) {
                $data['email'] = $context->getCustomerEmail();
                $state['data'] = $data;
            }
            if (empty($data['email'])) {
                return $this->ask($state, 'email', $context, 'I will pass this to our team. What email address should we reply to?');
            }
            if (empty($data['message'])) {
                return $this->ask($state, 'message', $context, 'Please describe what you need help with in one message (include your order number if it is about an order).');
            }
            $result = $this->tools->execute('request_submit', [
                'email' => $data['email'],
                'name' => $context->getCustomerName(),
                'message' => $data['message'],
                'order_number' => $data['order_number'] ?? null,
            ], $context);
            $this->clear($context);
            return $this->fromTool($result, $context);
        }

        if ($flow === self::FLOW_REQUEST_STATUS) {
            if (empty($data['request_code'])) {
                return $this->ask($state, 'request_code', $context, 'Sure. What is the request code? It looks like SR-A1B2C3 and is in the confirmation email.');
            }
            if (empty($data['email'])) {
                if ($context->getCustomerEmail()) {
                    $data['email'] = $context->getCustomerEmail();
                } else {
                    return $this->ask($state, 'email', $context, 'And which email address was the request created with?');
                }
            }
            return $this->verifiedStep($state, $context, 'request_info', ['code' => $data['request_code'], 'email' => $data['email']]);
        }

        // Order flows
        if (empty($data['order_number'])) {
            $prompts = [
                self::FLOW_ORDER_STATUS => 'Sure, I can check that. What is your order number? It looks like 000000123 and is in your confirmation email.',
                self::FLOW_ORDER_TRACKING => 'Happy to track it. What is your order number? It looks like 000000123 and is in your confirmation email.',
                self::FLOW_ORDER_INVOICE => 'I can get the invoice for you. What is your order number? It looks like 000000123 and is in your confirmation email.',
            ];
            return $this->ask($state, 'order_number', $context, $prompts[$flow]);
        }
        $toolName = self::ORDER_FLOWS[$flow];
        if ($context->isLoggedIn() && empty($data['email'])) {
            // Their own order needs no email; a different order will come back as email_required.
            $result = $this->tools->execute($toolName, ['order_number' => $data['order_number']], $context);
            if ($result->getErrorCode() !== OrderLookup::ERROR_EMAIL_REQUIRED) {
                return $this->finishOrder($state, $context, $result);
            }
        }
        if (empty($data['email'])) {
            // An email already verified earlier in this chat is tried first, so repeat lookups need no re-typing.
            foreach ($conversation->getVerifiedEmails() as $verifiedEmail) {
                $result = $this->tools->execute($toolName, ['order_number' => $data['order_number'], 'email' => $verifiedEmail], $context);
                if ($result->getErrorCode() !== OrderLookup::ERROR_NOT_FOUND) {
                    return $this->finishOrder($state, $context, $result);
                }
            }
        }
        if (empty($data['email'])) {
            return $this->ask($state, 'email', $context, 'Thanks. For security, which email address was used on the order?');
        }
        return $this->verifiedStep($state, $context, $toolName, ['order_number' => $data['order_number'], 'email' => $data['email']]);
    }

    /**
     * Runs a tool that needs a verified email, sending/checking the code when necessary.
     */
    private function verifiedStep(array $state, ChatContext $context, string $toolName, array $arguments): ChatReply
    {
        $data = $state['data'];
        $conversation = $context->getConversation();
        $email = (string)$data['email'];

        if (!empty($data['code']) && !$conversation->isEmailVerified($email)) {
            $verify = $this->verification->verify($context, $email, (string)$data['code']);
            unset($state['data']['code']);
            if (!$verify['ok']) {
                if ($verify['error'] === 'no_code') {
                    $this->clear($context);
                    return ChatReply::create($verify['message'])->setChips($this->menuChips($context));
                }
                return $this->ask($state, 'code', $context, $verify['message'] . ' Please type the 6-digit code from the email.');
            }
        }

        if (!$conversation->isEmailVerified($email)) {
            if (($state['step'] ?? '') === 'code') {
                return $this->ask($state, 'code', $context, 'Please type the 6-digit code we emailed to ' . $email . '.');
            }
            $sent = $this->verification->send($context, $email);
            if (!$sent['ok']) {
                $this->clear($context);
                return ChatReply::create($sent['message'])->setChips($this->menuChips($context));
            }
            return $this->ask($state, 'code', $context, $sent['message'] . ' Please type the code here.');
        }

        $result = $this->tools->execute($toolName, $arguments, $context);
        if (isset(self::ORDER_FLOWS[$state['flow']])) {
            return $this->finishOrder($state, $context, $result);
        }
        $this->clear($context);
        return $this->fromTool($result, $context);
    }

    private function finishOrder(array $state, ChatContext $context, ToolResult $result): ChatReply
    {
        if ($result->getErrorCode() === OrderLookup::ERROR_NOT_FOUND) {
            $state['retries'] = (int)($state['retries'] ?? 0) + 1;
            unset($state['data']['order_number']);
            if ($state['retries'] > self::MAX_RETRIES) {
                $this->clear($context);
                return ChatReply::create($result->getText() . ' I can pass this to our team instead - would you like to create a support request?')
                    ->setChips([['label' => 'Create a support request', 'send' => 'I want to talk to a human'], ['label' => 'Cancel']]);
            }
            return $this->ask($state, 'order_number', $context, $result->getText() . ' Please type the order number again, or say cancel.');
        }
        $this->clear($context);
        return $this->fromTool($result, $context);
    }

    private function ask(array $state, string $step, ChatContext $context, string $question): ChatReply
    {
        $state['step'] = $step;
        $context->getConversation()->setFlowState($state);
        return ChatReply::create($question)->setChips([['label' => 'Cancel']]);
    }

    private function fromTool(ToolResult $result, ChatContext $context): ChatReply
    {
        $reply = ChatReply::create((string)($result->getText() ?? 'Done.'))
            ->addCards($result->getCards())
            ->setGrounded(!$result->isError());
        $chips = $result->getChips();
        if ($chips === []) {
            $chips = $this->menuChips($context);
        }
        return $reply->setChips($chips);
    }

    private function clear(ChatContext $context): void
    {
        $context->getConversation()->setFlowState(null);
    }

    /**
     * @return array<int, array{label: string, send: string}>
     */
    public function menuChips(ChatContext $context): array
    {
        $chips = [
            ['label' => 'Track my order', 'send' => 'Track my order'],
            ['label' => 'Find a product', 'send' => 'Show me your ready to ship earrings'],
            ['label' => 'Shipping & returns', 'send' => 'What is your shipping and return policy?'],
        ];
        if ($this->config->isHumanHandoffAllowed($context->getStoreId())) {
            $chips[] = ['label' => 'Talk to a human', 'send' => 'I want to talk to a human'];
        }
        return $chips;
    }
}
