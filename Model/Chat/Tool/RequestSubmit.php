<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat\Tool;

use WB\AiChatbot\Model\Chat\ChatContext;
use WB\AiChatbot\Model\Chat\RequestManager;
use WB\AiChatbot\Model\Config;

class RequestSubmit implements ToolInterface
{
    /**
     * @var RequestManager
     */
    private $requests;

    /**
     * @var Config
     */
    private $config;

    public function __construct(RequestManager $requests, Config $config)
    {
        $this->requests = $requests;
        $this->config = $config;
    }

    public function getName(): string
    {
        return 'request_submit';
    }

    public function getDescription(): string
    {
        return 'Create a support request for the human team (a "ticket") when the customer wants to talk to a person, has a complaint, '
            . 'or you cannot help. Collect their email (if not logged in) and a clear description first. Returns a request code.';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'email' => ['type' => 'string', 'description' => 'Customer email for the reply'],
                'name' => ['type' => 'string', 'description' => 'Customer name, if known'],
                'subject' => ['type' => 'string', 'description' => 'Short subject line'],
                'message' => ['type' => 'string', 'description' => 'The customer\'s issue in their own words, plus any useful context'],
                'order_number' => ['type' => 'string', 'description' => 'Related order number, if any'],
            ],
            'required' => ['email', 'message'],
        ];
    }

    public function isAvailable(ChatContext $context): bool
    {
        return $this->config->isHumanHandoffAllowed($context->getStoreId());
    }

    public function execute(array $arguments, ChatContext $context): ToolResult
    {
        $email = (string)($arguments['email'] ?? '') ?: (string)$context->getCustomerEmail();
        $result = $this->requests->submit(
            $context,
            $email,
            isset($arguments['name']) ? (string)$arguments['name'] : $context->getCustomerName(),
            (string)($arguments['subject'] ?? ''),
            (string)($arguments['message'] ?? ''),
            isset($arguments['order_number']) ? (string)$arguments['order_number'] : null
        );
        if (!$result['ok']) {
            return ToolResult::error($result['error'], $result['message']);
        }
        return new ToolResult($result, [], $result['message']);
    }
}
