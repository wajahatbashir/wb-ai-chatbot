<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Provider;

use WB\AiChatbot\Api\Data\CredentialInterface;

/**
 * Google Gemini (Generative Language API): generateContent with function calling, batchEmbedContents.
 */
class Gemini extends AbstractHttpProvider
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    public function getCode(): string
    {
        return 'gemini';
    }

    public function getLabel(): string
    {
        return 'Google Gemini';
    }

    public function getChatModels(): array
    {
        return [
            'gemini-2.5-flash' => 'Gemini 2.5 Flash (recommended - fast, low cost)',
            'gemini-2.5-pro' => 'Gemini 2.5 Pro',
        ];
    }

    public function getEmbeddingModels(): array
    {
        return [
            'gemini-embedding-001' => 'gemini-embedding-001 (recommended)',
            'text-embedding-004' => 'text-embedding-004',
        ];
    }

    public function chat(CredentialInterface $credential, array $messages, array $tools = [], array $options = []): ChatResult
    {
        $key = $this->requireKey($credential);
        $model = $this->modelOrDefault($credential);

        $system = [];
        $contents = [];
        foreach ($messages as $message) {
            $role = (string)($message['role'] ?? 'user');
            if ($role === 'system') {
                $system[] = $this->contentToText($message['content'] ?? '');
                continue;
            }
            if ($role === 'tool') {
                $contents[] = ['role' => 'user', 'parts' => [[
                    'functionResponse' => [
                        'name' => (string)($message['name'] ?? 'tool'),
                        'response' => ['result' => $this->contentToText($message['content'] ?? '')],
                    ],
                ]]];
                continue;
            }
            if ($role === 'assistant') {
                $parts = [];
                $text = $this->contentToText($message['content'] ?? '');
                if ($text !== '') {
                    $parts[] = ['text' => $text];
                }
                foreach ((array)($message['tool_calls'] ?? []) as $call) {
                    $parts[] = ['functionCall' => ['name' => (string)$call['name'], 'args' => (object)($call['arguments'] ?? [])]];
                }
                if ($parts !== []) {
                    $contents[] = ['role' => 'model', 'parts' => $parts];
                }
                continue;
            }
            $parts = [];
            foreach ($this->contentToParts($message['content'] ?? '') as $part) {
                if (($part['type'] ?? '') === 'image') {
                    $parts[] = ['inline_data' => ['mime_type' => $part['mime'] ?? 'image/jpeg', 'data' => $part['data'] ?? '']];
                } else {
                    $parts[] = ['text' => (string)($part['text'] ?? '')];
                }
            }
            $contents[] = ['role' => 'user', 'parts' => $parts];
        }

        $body = [
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => (int)($options['max_tokens'] ?? 600),
                'temperature' => (float)($options['temperature'] ?? 0.3),
            ],
        ];
        if ($system !== []) {
            $body['system_instruction'] = ['parts' => [['text' => implode("\n\n", $system)]]];
        }
        if ($tools !== []) {
            $body['tools'] = [['function_declarations' => array_map(static function (array $tool): array {
                return [
                    'name' => $tool['name'],
                    'description' => $tool['description'] ?? '',
                    'parameters' => $tool['parameters'] ?? ['type' => 'object', 'properties' => new \stdClass()],
                ];
            }, array_values($tools))]];
        }

        $url = sprintf('%s/models/%s:generateContent?key=%s', self::BASE_URL, rawurlencode($model), rawurlencode($key));
        $response = $this->request('POST', $url, [], $body, self::TIMEOUT_CHAT);

        $candidate = $response['candidates'][0] ?? [];
        $text = '';
        $toolCalls = [];
        foreach ((array)($candidate['content']['parts'] ?? []) as $part) {
            if (isset($part['text'])) {
                $text .= (string)$part['text'];
            } elseif (isset($part['functionCall'])) {
                $toolCalls[] = [
                    'id' => uniqid('gem_', true),
                    'name' => (string)($part['functionCall']['name'] ?? ''),
                    'arguments' => (array)($part['functionCall']['args'] ?? []),
                ];
            }
        }
        $reason = (string)($candidate['finishReason'] ?? 'STOP');
        $finish = $toolCalls !== [] ? ChatResult::FINISH_TOOL_CALLS
            : ($reason === 'MAX_TOKENS' ? ChatResult::FINISH_LENGTH : ChatResult::FINISH_STOP);

        return new ChatResult(
            $text,
            $toolCalls,
            $finish,
            (int)($response['usageMetadata']['promptTokenCount'] ?? 0),
            (int)($response['usageMetadata']['candidatesTokenCount'] ?? 0),
            $model
        );
    }

    public function embed(CredentialInterface $credential, array $texts): array
    {
        if ($texts === []) {
            return [];
        }
        $key = $this->requireKey($credential);
        $model = 'models/' . $this->embeddingModelOrDefault($credential);
        $requests = [];
        foreach (array_values($texts) as $text) {
            $requests[] = ['model' => $model, 'content' => ['parts' => [['text' => $text]]]];
        }
        $url = sprintf('%s/%s:batchEmbedContents?key=%s', self::BASE_URL, $model, rawurlencode($key));
        $response = $this->request('POST', $url, [], ['requests' => $requests], self::TIMEOUT_EMBED);

        $vectors = [];
        foreach ((array)($response['embeddings'] ?? []) as $row) {
            $vectors[] = array_map('floatval', (array)($row['values'] ?? []));
        }
        return $vectors;
    }

    public function healthCheck(CredentialInterface $credential): HealthResult
    {
        try {
            $key = $this->requireKey($credential);
            $response = $this->request('GET', self::BASE_URL . '/models?key=' . rawurlencode($key), [], null, self::TIMEOUT_HEALTH);
        } catch (ProviderException $e) {
            return new HealthResult($e->getStatus(), $e->getMessage());
        }
        $count = count((array)($response['models'] ?? []));
        return HealthResult::ok(sprintf('Key valid, %d models available', $count));
    }
}
