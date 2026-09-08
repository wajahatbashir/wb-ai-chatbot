<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Provider;

use WB\AiChatbot\Api\Data\CredentialInterface;

/**
 * Anthropic Messages API. No embeddings endpoint - the pool borrows another credential's embedding model.
 */
class Anthropic extends AbstractHttpProvider
{
    private const BASE_URL = 'https://api.anthropic.com/v1';
    private const API_VERSION = '2023-06-01';

    public function getCode(): string
    {
        return 'anthropic';
    }

    public function getLabel(): string
    {
        return 'Anthropic (Claude)';
    }

    public function getChatModels(): array
    {
        return [
            'claude-sonnet-4-5' => 'Claude Sonnet 4.5 (recommended)',
            'claude-haiku-4-5' => 'Claude Haiku 4.5 (fast, low cost)',
            'claude-opus-4-1' => 'Claude Opus 4.1',
        ];
    }

    public function getEmbeddingModels(): array
    {
        return [];
    }

    public function chat(CredentialInterface $credential, array $messages, array $tools = [], array $options = []): ChatResult
    {
        $key = $this->requireKey($credential);

        $system = [];
        $vendorMessages = [];
        foreach ($messages as $message) {
            $role = (string)($message['role'] ?? 'user');
            if ($role === 'system') {
                $system[] = $this->contentToText($message['content'] ?? '');
                continue;
            }
            if ($role === 'tool') {
                $vendorMessages[] = ['role' => 'user', 'content' => [[
                    'type' => 'tool_result',
                    'tool_use_id' => (string)($message['tool_call_id'] ?? ''),
                    'content' => $this->contentToText($message['content'] ?? ''),
                ]]];
                continue;
            }
            if ($role === 'assistant') {
                $blocks = [];
                $text = $this->contentToText($message['content'] ?? '');
                if ($text !== '') {
                    $blocks[] = ['type' => 'text', 'text' => $text];
                }
                foreach ((array)($message['tool_calls'] ?? []) as $call) {
                    $blocks[] = [
                        'type' => 'tool_use',
                        'id' => (string)$call['id'],
                        'name' => (string)$call['name'],
                        'input' => (object)($call['arguments'] ?? []),
                    ];
                }
                if ($blocks !== []) {
                    $vendorMessages[] = ['role' => 'assistant', 'content' => $blocks];
                }
                continue;
            }
            $blocks = [];
            foreach ($this->contentToParts($message['content'] ?? '') as $part) {
                if (($part['type'] ?? '') === 'image') {
                    $blocks[] = ['type' => 'image', 'source' => [
                        'type' => 'base64',
                        'media_type' => $part['mime'] ?? 'image/jpeg',
                        'data' => $part['data'] ?? '',
                    ]];
                } else {
                    $blocks[] = ['type' => 'text', 'text' => (string)($part['text'] ?? '')];
                }
            }
            $vendorMessages[] = ['role' => 'user', 'content' => $blocks];
        }
        $vendorMessages = $this->mergeConsecutive($vendorMessages);

        $body = [
            'model' => $this->modelOrDefault($credential),
            'max_tokens' => (int)($options['max_tokens'] ?? 600),
            'temperature' => (float)($options['temperature'] ?? 0.3),
            'messages' => $vendorMessages,
        ];
        if ($system !== []) {
            $body['system'] = implode("\n\n", $system);
        }
        if ($tools !== []) {
            $body['tools'] = array_map(static function (array $tool): array {
                return [
                    'name' => $tool['name'],
                    'description' => $tool['description'] ?? '',
                    'input_schema' => $tool['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
                ];
            }, array_values($tools));
        }

        $response = $this->request('POST', self::BASE_URL . '/messages', $this->headers($key), $body, self::TIMEOUT_CHAT);

        $text = '';
        $toolCalls = [];
        foreach ((array)($response['content'] ?? []) as $block) {
            $type = $block['type'] ?? '';
            if ($type === 'text') {
                $text .= (string)($block['text'] ?? '');
            } elseif ($type === 'tool_use') {
                $toolCalls[] = [
                    'id' => (string)($block['id'] ?? uniqid('toolu_', true)),
                    'name' => (string)($block['name'] ?? ''),
                    'arguments' => (array)($block['input'] ?? []),
                ];
            }
        }
        $stop = (string)($response['stop_reason'] ?? 'end_turn');
        $finish = $toolCalls !== [] ? ChatResult::FINISH_TOOL_CALLS
            : ($stop === 'max_tokens' ? ChatResult::FINISH_LENGTH : ChatResult::FINISH_STOP);

        return new ChatResult(
            $text,
            $toolCalls,
            $finish,
            (int)($response['usage']['input_tokens'] ?? 0),
            (int)($response['usage']['output_tokens'] ?? 0),
            (string)($response['model'] ?? $body['model'])
        );
    }

    public function embed(CredentialInterface $credential, array $texts): array
    {
        throw new ProviderException(CredentialInterface::STATUS_MODEL_UNAVAILABLE, 'Anthropic has no embeddings API');
    }

    public function healthCheck(CredentialInterface $credential): HealthResult
    {
        try {
            $key = $this->requireKey($credential);
            $response = $this->request('GET', self::BASE_URL . '/models', $this->headers($key), null, self::TIMEOUT_HEALTH);
        } catch (ProviderException $e) {
            return new HealthResult($e->getStatus(), $e->getMessage());
        }
        $count = count((array)($response['data'] ?? []));
        return HealthResult::ok(sprintf('Key valid, %d models available', $count));
    }

    /**
     * @return string[]
     */
    private function headers(string $key): array
    {
        return ['x-api-key' => $key, 'anthropic-version' => self::API_VERSION];
    }

    /**
     * Anthropic requires strictly alternating user/assistant turns; consecutive same-role messages are merged.
     */
    private function mergeConsecutive(array $messages): array
    {
        $merged = [];
        foreach ($messages as $message) {
            $last = count($merged) - 1;
            if ($last >= 0 && $merged[$last]['role'] === $message['role']) {
                $merged[$last]['content'] = array_merge($merged[$last]['content'], $message['content']);
            } else {
                $merged[] = $message;
            }
        }
        return $merged;
    }
}
