<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat\Tool;

use WB\AiChatbot\Model\Chat\ChatContext;
use WB\AiChatbot\Model\Chat\Verification;

class VerifyCode implements ToolInterface
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
        return 'verify_code';
    }

    public function getDescription(): string
    {
        return 'Check the 6-digit code the customer typed after send_verification_code. On success the email is verified for '
            . 'this conversation and the order/request tools can be called again.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'email' => ['type' => 'string', 'description' => 'The email address the code was sent to'],
                'code' => ['type' => 'string', 'description' => 'The 6-digit code'],
            ],
            'required' => ['email', 'code'],
        ];
    }

    public function isAvailable(ChatContext $context): bool
    {
        return true;
    }

    public function execute(array $arguments, ChatContext $context): ToolResult
    {
        $result = $this->verification->verify($context, (string)($arguments['email'] ?? ''), (string)($arguments['code'] ?? ''));
        if (!$result['ok']) {
            return ToolResult::error($result['error'], $result['message']);
        }
        return new ToolResult($result, [], $result['message']);
    }
}
