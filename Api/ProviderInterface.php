<?php
declare(strict_types=1);

namespace WB\AiChatbot\Api;

use WB\AiChatbot\Api\Data\CredentialInterface;
use WB\AiChatbot\Model\Provider\ChatResult;
use WB\AiChatbot\Model\Provider\HealthResult;
use WB\AiChatbot\Model\Provider\ProviderException;

/**
 * One AI vendor (OpenAI, Anthropic, Gemini, xAI, Mock). Stateless: every call receives the credential to use.
 */
interface ProviderInterface
{
    public function getCode(): string;

    public function getLabel(): string;

    /**
     * Curated chat models, code => label. First entry is the recommended default.
     *
     * @return string[]
     */
    public function getChatModels(): array;

    /**
     * Embedding models, code => label. Empty when the vendor has no embeddings API.
     *
     * @return string[]
     */
    public function getEmbeddingModels(): array;

    public function supportsVision(): bool;

    /**
     * Non-streaming chat completion with optional tool calling.
     *
     * @param array $messages   [['role' => 'system|user|assistant|tool', 'content' => string|array, ...], ...]
     * @param array $tools      [['name' => ..., 'description' => ..., 'parameters' => JSON-schema array], ...]
     * @param array $options    max_tokens, temperature, tool_choice
     * @throws ProviderException
     */
    public function chat(CredentialInterface $credential, array $messages, array $tools = [], array $options = []): ChatResult;

    /**
     * @param string[] $texts
     * @return float[][] one vector per input text, in order
     * @throws ProviderException
     */
    public function embed(CredentialInterface $credential, array $texts): array;

    /**
     * Cheap authenticated call (typically "list models") that never spends tokens.
     */
    public function healthCheck(CredentialInterface $credential): HealthResult;
}
