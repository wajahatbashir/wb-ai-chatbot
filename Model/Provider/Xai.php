<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Provider;

use WB\AiChatbot\Api\Data\CredentialInterface;

/**
 * xAI (Grok) exposes an OpenAI-compatible API; only the base URL, model list and the missing embeddings API differ.
 */
class Xai extends OpenAi
{
    protected const BASE_URL = 'https://api.x.ai/v1';

    public function getCode(): string
    {
        return 'xai';
    }

    public function getLabel(): string
    {
        return 'xAI (Grok)';
    }

    public function getChatModels(): array
    {
        return [
            'grok-4' => 'Grok 4 (recommended)',
            'grok-3-mini' => 'Grok 3 mini',
            'grok-3' => 'Grok 3',
        ];
    }

    public function getEmbeddingModels(): array
    {
        return [];
    }

    public function embed(CredentialInterface $credential, array $texts): array
    {
        throw new ProviderException(CredentialInterface::STATUS_MODEL_UNAVAILABLE, 'xAI has no embeddings API');
    }
}
