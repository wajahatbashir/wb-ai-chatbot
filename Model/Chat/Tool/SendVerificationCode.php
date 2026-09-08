<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat\Tool;

use WB\AiChatbot\Model\Chat\ChatContext;
use WB\AiChatbot\Model\Chat\Verification;

class SendVerificationCode implements ToolInterface
{
    /**
     * @var Verification
     */
    private $verification;

    public function __construct(Verification $verification)
    {
        $this->verification = $verification;
    }

    public function getName(): string
    {
        return 'send_verification_code';
    }

    public function getDescription(): string
    {
        return 'Email a 6-digit one-time code to verify that the customer owns an email address. Call it when an order/request '
            . 'tool answered verification_required. Afterwards ask the customer to type the code.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => ['email' => ['type' => 'string', 'description' => 'The email address to verify']],
            'required' => ['email'],
        ];
    }

    public function isAvailable(ChatContext $context): bool
    {
        return true;
    }

    public function execute(array $arguments, ChatContext $context): ToolResult
    {
        $result = $this->verification->send($context, (string)($arguments['email'] ?? ''));
        if (!$result['ok']) {
            return ToolResult::error($result['error'], $result['message']);
        }
        return new ToolResult($result, [], $result['message'] . ' Please type the code here.');
    }
}
