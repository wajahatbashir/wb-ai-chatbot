<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat\Tool;

use WB\AiChatbot\Model\Chat\ChatContext;

class ToolRegistry
{
    /**
     * @var ToolInterface[] keyed by name
     */
    private $tools = [];

    /**
     * @param ToolInterface[] $tools
     */
    public function __construct(array $tools = [])
    {
        foreach ($tools as $tool) {
            if ($tool instanceof ToolInterface) {
                $this->tools[$tool->getName()] = $tool;
            }
        }
    }

    public function get(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return ToolInterface[]
     */
    public function getAvailable(ChatContext $context): array
    {
        return array_filter($this->tools, function (ToolInterface $tool) use ($context) {
            return $tool->isAvailable($context);
        });
    }

    /**
     * Provider-neutral tool schemas for the available tools.
     */
    public function getSchemas(ChatContext $context): array
    {
        $schemas = [];
        foreach ($this->getAvailable($context) as $tool) {
            $schemas[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'parameters' => $tool->getParameters(),
            ];
        }
        return $schemas;
    }

    public function execute(string $name, array $arguments, ChatContext $context): ToolResult
    {
        $tool = $this->get($name);
        if (!$tool || !$tool->isAvailable($context)) {
            return ToolResult::error('unknown_tool', 'That capability is not available.');
        }
        try {
            return $tool->execute($arguments, $context);
        } catch (\Throwable $e) {
            return ToolResult::error('tool_failed', 'Something went wrong while looking that up. Please try again.');
        }
    }
}
