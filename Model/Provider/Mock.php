<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Provider;

use Magento\Framework\Serialize\Serializer\Json;
use WB\AiChatbot\Api\Data\CredentialInterface;
use WB\AiChatbot\Api\ProviderInterface;

/**
 * Key-less provider for development and tests. Deterministic, never leaves the server.
 *
 * - chat(): if the latest user message is "/tool <name> {json}" it emits that tool call, so the whole tool loop can be
 *   exercised; if the conversation ends with tool results it summarises them; otherwise it echoes the question and
 *   lists any retrieved context it was given.
 * - embed(): hashed bag-of-words vectors (256 dims) - crude, but overlapping vocabulary scores higher, which is enough
 *   to test chunking, storage and cosine ranking end to end without an API.
 */
class Mock implements ProviderInterface
{
    public const CODE = 'mock';
    private const DIMS = 256;

    /**
     * @var Json
     */
    private $json;

    public function __construct(Json $json)
    {
        $this->json = $json;
    }

    public function getCode(): string
    {
        return self::CODE;
    }

    public function getLabel(): string
    {
        return 'Mock (no API - development only)';
    }

    public function getChatModels(): array
    {
        return ['mock-1' => 'Mock'];
    }

    public function getEmbeddingModels(): array
    {
        return ['mock-embed-256' => 'Mock hashed embeddings (256 dims)'];
    }

    public function supportsVision(): bool
    {
        return false;
    }

    public function chat(CredentialInterface $credential, array $messages, array $tools = [], array $options = []): ChatResult
    {
        $last = $messages !== [] ? $messages[count($messages) - 1] : ['role' => 'user', 'content' => ''];
        $lastText = $this->text($last['content'] ?? '');

        if (($last['role'] ?? '') === 'tool') {
            $results = [];
            for ($i = count($messages) - 1; $i >= 0 && ($messages[$i]['role'] ?? '') === 'tool'; $i--) {
                $results[] = $this->text($messages[$i]['content'] ?? '');
            }
            $summary = implode("\n", array_reverse($results));
            return new ChatResult("Here is what I found:\n" . $summary, [], ChatResult::FINISH_STOP, 10, 10, 'mock-1');
        }

        if (preg_match('/^\/tool\s+([a-z_]+)\s*(\{.*\})?\s*$/is', $lastText, $m)) {
            $allowed = array_column($tools, 'name');
            if (in_array($m[1], $allowed, true)) {
                $arguments = [];
                if (!empty($m[2])) {
                    try {
                        $arguments = (array)$this->json->unserialize($m[2]);
                    } catch (\Throwable $e) {
                        $arguments = [];
                    }
                }
                return new ChatResult('', [[
                    'id' => 'mock_' . substr(md5($lastText), 0, 8),
                    'name' => $m[1],
                    'arguments' => $arguments,
                ]], ChatResult::FINISH_TOOL_CALLS, 10, 5, 'mock-1');
            }
        }

        $context = '';
        foreach ($messages as $message) {
            if (($message['role'] ?? '') === 'system') {
                $system = $this->text($message['content'] ?? '');
                if (preg_match('/Knowledge base results:(.*)$/s', $system, $km)) {
                    $context = trim($km[1]);
                }
            }
        }
        $reply = sprintf('[Mock AI] You asked: "%s".', mb_substr($lastText, 0, 200));
        if ($context !== '') {
            $reply .= "\n\nBased on the knowledge base:\n" . mb_substr($context, 0, 800);
        }
        return new ChatResult($reply, [], ChatResult::FINISH_STOP, (int)(strlen($lastText) / 4), 20, 'mock-1');
    }

    public function embed(CredentialInterface $credential, array $texts): array
    {
        $vectors = [];
        foreach ($texts as $text) {
            $vector = array_fill(0, self::DIMS, 0.0);
            $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower((string)$text)) ?: [];
            foreach ($tokens as $token) {
                if (mb_strlen($token) < 2) {
                    continue;
                }
                $slot = crc32($token) % self::DIMS;
                $vector[$slot] += 1.0;
            }
            $norm = sqrt(array_sum(array_map(static function (float $v): float {
                return $v * $v;
            }, $vector)));
            if ($norm > 0) {
                $vector = array_map(static function (float $v) use ($norm): float {
                    return $v / $norm;
                }, $vector);
            }
            $vectors[] = $vector;
        }
        return $vectors;
    }

    public function healthCheck(CredentialInterface $credential): HealthResult
    {
        return HealthResult::ok('Mock provider - always available, no API calls');
    }

    /**
     * @param string|array $content
     */
    private function text($content): string
    {
        if (is_string($content)) {
            return $content;
        }
        $texts = [];
        foreach ((array)$content as $part) {
            if (is_array($part) && ($part['type'] ?? '') === 'text') {
                $texts[] = (string)($part['text'] ?? '');
            }
        }
        return implode("\n", $texts);
    }
}
