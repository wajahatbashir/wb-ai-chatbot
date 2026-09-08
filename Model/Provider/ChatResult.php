<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Provider;

/**
 * Normalised result of one chat completion, independent of the vendor's wire format.
 */
class ChatResult
{
    public const FINISH_STOP = 'stop';
    public const FINISH_TOOL_CALLS = 'tool_calls';
    public const FINISH_LENGTH = 'length';

    /**
     * @var string
     */
    private $content;

    /**
     * @var array [['id' => string, 'name' => string, 'arguments' => array], ...]
     */
    private $toolCalls;

    /**
     * @var string
     */
    private $finishReason;

    /**
     * @var int
     */
    private $promptTokens;

    /**
     * @var int
     */
    private $completionTokens;

    /**
     * @var string|null
     */
    private $model;

    public function __construct(
        string $content,
        array $toolCalls = [],
        string $finishReason = self::FINISH_STOP,
        int $promptTokens = 0,
        int $completionTokens = 0,
        ?string $model = null
    ) {
        $this->content = $content;
        $this->toolCalls = $toolCalls;
        $this->finishReason = $finishReason;
        $this->promptTokens = $promptTokens;
        $this->completionTokens = $completionTokens;
        $this->model = $model;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return array [['id' => string, 'name' => string, 'arguments' => array], ...]
     */
    public function getToolCalls(): array
    {
        return $this->toolCalls;
    }

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }

    public function getFinishReason(): string
    {
        return $this->finishReason;
    }

    public function getPromptTokens(): int
    {
        return $this->promptTokens;
    }

    public function getCompletionTokens(): int
    {
        return $this->completionTokens;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }
}
