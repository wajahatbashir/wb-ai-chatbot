<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat\Tool;

use WB\AiChatbot\Model\Chat\ChatContext;
use WB\AiChatbot\Model\Chat\RequestManager;

class RequestInfo implements ToolInterface
{
    /**
     * @var RequestManager
     */
    private $requests;

    public function __construct(RequestManager $requests)
    {
        $this->requests = $requests;
    }

    public function getName(): string
    {
        return 'request_info';
    }

    public function getDescription(): string
    {
        return 'Status and reply of an existing support request by its code (e.g. SR-A1B2C3) and the email it was created with. '
            . 'The email must be verified for guests (send_verification_code / verify_code).';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'code' => ['type' => 'string', 'description' => 'Request code, e.g. SR-A1B2C3'],
                'email' => ['type' => 'string', 'description' => 'Email used when the request was created'],
            ],
            'required' => ['code', 'email'],
        ];
    }

    public function isAvailable(ChatContext $context): bool
    {
        return true;
    }

    public function execute(array $arguments, ChatContext $context): ToolResult
    {
        $email = mb_strtolower(trim((string)($arguments['email'] ?? '')));
        $code = strtoupper(trim((string)($arguments['code'] ?? '')));
        if ($email === '' || $code === '') {
            return ToolResult::error('missing', 'Please give the request code (e.g. SR-A1B2C3) and the email address you used.');
        }
        if (!$context->getConversation()->isEmailVerified($email)) {
            return new ToolResult(
                ['error' => 'verification_required', 'email' => $email, 'message' => sprintf('I need to verify %s with a one-time code before showing the request.', $email)],
                [],
                sprintf('I need to verify %s with a one-time code before showing the request.', $email)
            );
        }
        $request = $this->requests->find($code, $email);
        if (!$request) {
            return ToolResult::error('not_found', sprintf('I could not find request %s for %s.', $code, $email));
        }
        $lines = [sprintf('Request %s ("%s") is **%s** (opened %s).', $request['code'], $request['subject'], $request['status_label'], date('j M Y', strtotime((string)$request['created_at'])))];
        if ($request['admin_reply']) {
            $lines[] = 'Our reply: ' . $request['admin_reply'];
        } else {
            $lines[] = 'Our team has not replied yet; you will receive the answer by email.';
        }
        return new ToolResult($request, [], implode("\n", $lines));
    }
}
