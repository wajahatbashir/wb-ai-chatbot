<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

use WB\AiChatbot\Api\Data\CredentialInterface;
use WB\AiChatbot\Api\ProviderInterface;
use WB\AiChatbot\Model\Chat\Tool\ToolRegistry;
use WB\AiChatbot\Model\Config;
use WB\AiChatbot\Model\Kb\Retriever;
use WB\AiChatbot\Model\Logger;
use WB\AiChatbot\Model\Provider\ChatResult;
use WB\AiChatbot\Model\Provider\Pool;

/**
 * AI mode: retrieval -> prompt -> model -> tool calls (looped) -> final reply with cards.
 *
 * @throws \WB\AiChatbot\Model\Provider\NoProviderException|\WB\AiChatbot\Model\Provider\ProviderException when no
 *         provider could answer - the Engine then falls back to Assist mode.
 */
class Orchestrator
{
    private const RETRIEVAL_LIMIT = 6;

    /**
     * @var Pool
     */
    private $pool;

    /**
     * @var Retriever
     */
    private $retriever;

    /**
     * @var PromptBuilder
     */
    private $promptBuilder;

    /**
     * @var ToolRegistry
     */
    private $tools;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Guard
     */
    private $guard;

    /**
     * @var CostCalculator
     */
    private $costCalculator;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(
        Pool $pool,
        Retriever $retriever,
        PromptBuilder $promptBuilder,
        ToolRegistry $tools,
        Config $config,
        Guard $guard,
        CostCalculator $costCalculator,
        Logger $logger
    ) {
        $this->pool = $pool;
        $this->retriever = $retriever;
        $this->promptBuilder = $promptBuilder;
        $this->tools = $tools;
        $this->config = $config;
        $this->guard = $guard;
        $this->costCalculator = $costCalculator;
        $this->logger = $logger;
    }

    /**
     * @param Message[] $history
     */
    public function respond(ChatContext $context, string $message, array $history, array $attachments = []): ChatReply
    {
        $storeId = $context->getStoreId();
        $retrieved = $this->retriever->search($message, $storeId, self::RETRIEVAL_LIMIT);
        $schemas = $this->tools->getSchemas($context);
        $options = [
            'max_tokens' => $this->config->getMaxReplyTokens($storeId),
            'temperature' => $this->config->getTemperature($storeId),
        ];

        $usedCredential = null;
        $usedModel = null;
        $promptTokens = 0;
        $completionTokens = 0;
        $cards = [];
        $chips = [];
        $toolCallsLog = [];
        $toolText = [];
        $messages = null;
        $final = null;

        $iterations = max(1, $this->config->getMaxToolIterations($storeId)) + 1;
        for ($i = 0; $i < $iterations; $i++) {
            $withTools = $i < $iterations - 1; // last round: force a text answer
            /** @var ChatResult $result */
            $result = $this->pool->execute(function (ProviderInterface $provider, CredentialInterface $credential) use (
                &$messages, $context, $history, $message, $retrieved, $attachments, $schemas, $options, $withTools, &$usedCredential, &$usedModel
            ) {
                if ($messages === null || $usedCredential !== $credential->getCredentialId()) {
                    // (Re)build for this provider - vision parts only when it can read images.
                    $built = $this->promptBuilder->build($context, $history, $message, $retrieved, $attachments, $provider->supportsVision() && (bool)$credential->getSupportsVision());
                    $messages = $messages === null ? $built : array_merge($built, array_slice($messages, count($built)));
                }
                $usedCredential = $credential->getCredentialId();
                $usedModel = null;
                if ($this->config->isDebugLog()) {
                    $this->logger->debug(sprintf(
                        'AI request via "%s": %d messages, %d tools, system prompt %d chars',
                        $credential->getAlias(),
                        count($messages),
                        $withTools ? count($schemas) : 0,
                        strlen((string)($messages[0]['content'] ?? ''))
                    ));
                }
                return $provider->chat($credential, $messages, $withTools ? $schemas : [], $options);
            });
            $promptTokens += $result->getPromptTokens();
            $completionTokens += $result->getCompletionTokens();
            $usedModel = $result->getModel() ?: $usedModel;

            if (!$result->hasToolCalls()) {
                $final = $result;
                break;
            }
            $messages[] = ['role' => 'assistant', 'content' => $result->getContent(), 'tool_calls' => $result->getToolCalls()];
            foreach ($result->getToolCalls() as $call) {
                $toolResult = $this->tools->execute((string)$call['name'], (array)($call['arguments'] ?? []), $context);
                $toolCallsLog[] = ['name' => $call['name'], 'arguments' => $call['arguments'] ?? [], 'result' => $this->compact($toolResult->getData())];
                $cards = array_merge($cards, $toolResult->getCards());
                $chips = array_merge($chips, $toolResult->getChips());
                if ($toolResult->getText() !== null) {
                    $toolText[] = $toolResult->getText();
                }
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string)($call['id'] ?? ''),
                    'name' => (string)$call['name'],
                    'content' => json_encode($this->compact($toolResult->getData()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ];
            }
        }

        $text = $final ? trim($final->getContent()) : '';
        if ($text === '') {
            $text = $toolText !== [] ? implode("\n\n", array_unique($toolText)) : 'Here is what I found.';
        }
        $reply = ChatReply::create($text)
            ->addCards($this->dedupeCards($cards))
            ->setChips($chips)
            ->setMode(Conversation::MODE_AI)
            ->setSources(array_map(function ($row) {
                return ['title' => $row['title'], 'url' => $row['url'], 'document_id' => $row['document_id'], 'confidence' => $row['confidence']];
            }, array_slice($retrieved, 0, 3)))
            ->setGrounded($toolCallsLog !== [] || ($retrieved !== [] && $retrieved[0]['confidence'] >= 0.3))
            ->setMeta([
                'credential_id' => $usedCredential,
                'model' => $usedModel,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'cost' => $this->costCalculator->cost($usedModel, $promptTokens, $completionTokens),
                'tool_calls' => $toolCallsLog,
            ]);
        $bestConfidence = $retrieved !== [] ? (float)$retrieved[0]['confidence'] : 0.0;
        if ($toolCallsLog === [] && $this->guard->looksUnanswered($text) && $bestConfidence < 0.45) {
            $reply->setUnanswered(true);
        }
        return $reply;
    }

    /**
     * Tool payloads for the model: drop UI-only keys and cap long lists.
     */
    private function compact(array $data): array
    {
        unset($data['download_url_signed']);
        if (isset($data['products']) && is_array($data['products'])) {
            $data['products'] = array_slice($data['products'], 0, 8);
        }
        return $data;
    }

    private function dedupeCards(array $cards): array
    {
        $seen = [];
        $unique = [];
        foreach ($cards as $card) {
            $key = ($card['type'] ?? '') . ':' . ($card['id'] ?? $card['url'] ?? $card['title'] ?? json_encode($card));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $card;
        }
        return $unique;
    }
}
