<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat\Tool;

use WB\AiChatbot\Model\Chat\ChatContext;

/**
 * A server-side capability the assistant can invoke: in AI mode chosen by the model (function calling),
 * in Assist mode driven by the guided flows. Register implementations in di.xml under ToolRegistry "tools".
 */
interface ToolInterface
{
    public function getName(): string;

    /**
     * Shown to the model - say precisely when to use the tool.
     */
    public function getDescription(): string;

    /**
     * JSON schema of the arguments (object).
     */
    public function getParameters(): array;

    public function isAvailable(ChatContext $context): bool;

    public function execute(array $arguments, ChatContext $context): ToolResult;
}
