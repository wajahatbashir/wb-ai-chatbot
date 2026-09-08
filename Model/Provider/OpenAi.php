<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Provider;

use WB\AiChatbot\Api\Data\CredentialInterface;

/**
 * OpenAI Chat Completions + Embeddings. Also the base for any OpenAI-compatible vendor (see Xai).
 */
class OpenAi extends AbstractHttpProvider
{
    protected const BASE_URL = 'https://api.openai.com/v1';

    public function getCode(): string
    {
        return 'openai';
    }

    public function getLabel(): string
    {
        return 'OpenAI (ChatGPT)';
    }

    public function getChatModels(): array
    {
        return [
            'gpt-4.1-mini' => 'GPT-4.1 mini (recommended - fast, low cost)',
            'gpt-4.1' => 'GPT-4.1',
            'gpt-4o-mini' => 'GPT-4o mini',
            'gpt-4o' => 'GPT-4o',
            'gpt-5-mini' => 'GPT-5 mini',
            'gpt-5' => 'GPT-5',
        ];
    }

    public function getEmbeddingModels(): array
    {
        return [
            'text-embedding-3-small' => 'text-embedding-3-small (recommended)',
            'text-embedding-3-large' => 'text-embedding-3-large',
        ];
    }

    public function chat(CredentialInterface $credential, array $messages, array $tools = [], array $options = []): ChatResult
    {
        $key = $this->requireKey($credential);
        $body = [
            'model' => $this->modelOrDefault($credential),
            'messages' => $this->toVendorMessages($messages),
            'max_tokens' => (int)($options['max_tokens'] ?? 600),
            'temperature' => (float)($options['temperature'] ?? 0.3),
        ];
        if ($tools !== []) {
            $body['tools'] = array_map(static function (array $tool): array {
                return [
                    'type' => 'function',
                    'function' => [
                        'name' => $tool['name'],
                        'description' => $tool['description'] ?? '',
                        'parameters' => $tool['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
                    ],
                ];
            }, array_values($tools));
            $body['tool_choice'] = $options['tool_choice'] ?? 'auto';
        }

        $response = $this->request('POST', static::BASE_URL . '/chat/completions', $this->headers($key), $body, static::TIMEOUT_CHAT);

        $choice = $response['choices'][0] ?? [];
        $message = $choice['message'] ?? [];
        $toolCalls = [];
        foreach ((array)($message['tool_calls'] ?? []) as $call) {
            $toolCalls[] = [
                'id' => (string)($call['id'] ?? uniqid('call_', true)),
                'name' => (string)($call['function']['name'] ?? ''),
                'arguments' => $this->decodeArguments($call['function']['arguments'] ?? '{}'),
            ];
        }
        $finish = (string)($choice['finish_reason'] ?? 'stop');
        if ($toolCalls !== []) {
            $finish = ChatResult::FINISH_TOOL_CALLS;
        } elseif ($finish === 'length') {
            $finish = ChatResult::FINISH_LENGTH;
        } else {
            $finish = ChatResult::FINISH_STOP;
        }

        return new ChatResult(
            (string)($message['content'] ?? ''),
            $toolCalls,
            $finish,
            (int)($response['usage']['prompt_tokens'] ?? 0),
            (int)($response['usage']['completion_tokens'] ?? 0),
            (string)($response['model'] ?? $body['model'])
        );
    }

    public function embed(CredentialInterface $credential, array $texts): array
    {
        if ($texts === []) {
            return [];
        }
        $key = $this->requireKey($credential);
        $response = $this->request('POST', static::BASE_URL . '/embeddings', $this->headers($key), [
            'model' => $this->embeddingModelOrDefault($credential),
            'input' => array_values($texts),
        ], static::TIMEOUT_EMBED);

        $vectors = [];
        foreach ((array)($response['data'] ?? []) as $row) {
            $vectors[(int)($row['index'] ?? count($vectors))] = array_map('floatval', (array)($row['embedding'] ?? []));
        }
        ksort($vectors);
        return array_values($vectors);
    }

    public function healthCheck(CredentialInterface $credential): HealthResult
    {
        try {
            $key = $this->requireKey($credential);
            $response = $this->request('GET', static::BASE_URL . '/models', $this->headers($key), null, static::TIMEOUT_HEALTH);
        } catch (ProviderException $e) {
            return new HealthResult($e->getStatus(), $e->getMessage());
        }

        $wanted = $this->modelOrDefault($credential);
        $available = array_map(static function ($row): string {
            return (string)($row['id'] ?? '');
        }, (array)($response['data'] ?? []));
        if ($wanted !== '' && $available !== [] && !in_array($wanted, $available, true)) {
            return new HealthResult(
                CredentialInterface::STATUS_MODEL_UNAVAILABLE,
                sprintf('Key is valid but model "%s" is not available to this account', $wanted)
            );
        }
        return HealthResult::ok(sprintf('Key valid, %d models available', count($available)));
    }

    /**
     * @return string[]
     */
    protected function headers(string $key): array
    {
        return ['Authorization' => 'Bearer ' . $key];
    }

    /**
     * Normalised messages -> OpenAI wire format (tool calls, tool results, multimodal user content).
     */
    protected function toVendorMessages(array $messages): array
    {
        $out = [];
        foreach ($messages as $message) {
            $role = (string)($message['role'] ?? 'user');
            if ($role === 'tool') {
                $out[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string)($message['tool_call_id'] ?? ''),
                    'content' => $this->contentToText($message['content'] ?? ''),
                ];
                continue;
            }
            if ($role === 'assistant' && !empty($message['tool_calls'])) {
                $out[] = [
                    'role' => 'assistant',
                    'content' => $this->contentToText($message['content'] ?? '') ?: null,
                    'tool_calls' => array_map(function (array $call): array {
                        return [
                            'id' => (string)$call['id'],
                            'type' => 'function',
                            'function' => [
                                'name' => (string)$call['name'],
                                'arguments' => $this->json->serialize($call['arguments'] ?? []),
                            ],
                        ];
                    }, $message['tool_calls']),
                ];
                continue;
            }

            $parts = $this->contentToParts($message['content'] ?? '');
            $hasImage = false;
            $vendorParts = [];
            foreach ($parts as $part) {
                if (($part['type'] ?? '') === 'image') {
                    $hasImage = true;
                    $vendorParts[] = [
                        'type' => 'image_url',
                        'image_url' => ['url' => sprintf('data:%s;base64,%s', $part['mime'] ?? 'image/jpeg', $part['data'] ?? '')],
                    ];
                } else {
                    $vendorParts[] = ['type' => 'text', 'text' => (string)($part['text'] ?? '')];
                }
            }
            $out[] = [
                'role' => $role,
                'content' => ($hasImage && $role === 'user') ? $vendorParts : $this->contentToText($message['content'] ?? ''),
            ];
        }
        return $out;
    }
}
