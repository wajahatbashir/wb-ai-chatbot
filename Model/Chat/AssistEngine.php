<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

use WB\AiChatbot\Model\Chat\Tool\ToolRegistry;
use WB\AiChatbot\Model\Config;
use WB\AiChatbot\Model\Kb\Retriever;

/**
 * Assist mode: rule-based intents, knowledge base passages returned verbatim, keyword product search, and the
 * guided flows. Everything here works with no AI provider at all.
 */
class AssistEngine
{
    private const KB_CONFIDENCE = 0.35;
    private const PRODUCT_CONFIDENCE = 0.35;
    /**
     * With no intent detected, a passage must match clearly (keyword relevance >= 8) before it is shown verbatim.
     */
    private const KB_CONFIDENCE_UNKNOWN = 0.5;

    /**
     * @var IntentRouter
     */
    private $intentRouter;

    /**
     * @var GuidedFlow
     */
    private $flow;

    /**
     * @var Retriever
     */
    private $retriever;

    /**
     * @var ProductFinder
     */
    private $productFinder;

    /**
     * @var ToolRegistry
     */
    private $tools;

    /**
     * @var Cards
     */
    private $cards;

    /**
     * @var Config
     */
    private $config;

    public function __construct(
        IntentRouter $intentRouter,
        GuidedFlow $flow,
        Retriever $retriever,
        ProductFinder $productFinder,
        ToolRegistry $tools,
        Cards $cards,
        Config $config
    ) {
        $this->intentRouter = $intentRouter;
        $this->flow = $flow;
        $this->retriever = $retriever;
        $this->productFinder = $productFinder;
        $this->tools = $tools;
        $this->cards = $cards;
        $this->config = $config;
    }

    public function respond(ChatContext $context, string $message): ChatReply
    {
        $storeId = $context->getStoreId();
        $detected = $this->intentRouter->detect($message, $context->getLanguage());
        $intent = $detected['intent'];

        switch ($intent) {
            case IntentRouter::INTENT_CANCEL:
                return ChatReply::create('Okay. What can I help you with?')->setChips($this->flow->menuChips($context));

            case IntentRouter::INTENT_GREETING:
                $name = $context->getCustomerName();
                $welcome = $this->config->getWelcomeMessage($storeId);
                if ($name) {
                    $welcome = 'Hi ' . $name . '! ' . preg_replace('/^(hi|hello|hey|welcome)[!,.]*\s*/i', '', $welcome);
                }
                return ChatReply::create($welcome)
                    ->setChips($this->questionChips($context))
                    ->setGrounded(true);

            case IntentRouter::INTENT_THANKS:
                return ChatReply::create("You're welcome! Is there anything else I can help you with?")
                    ->setChips($this->flow->menuChips($context))
                    ->setGrounded(true);

            case IntentRouter::INTENT_ORDER_STATUS:
            case IntentRouter::INTENT_ORDER_TRACKING:
            case IntentRouter::INTENT_ORDER_INVOICE:
            case IntentRouter::INTENT_REQUEST_STATUS:
                return $this->flow->start($intent, $context, $message);

            case IntentRouter::INTENT_HUMAN:
                if ($this->config->isHumanHandoffAllowed($storeId)) {
                    return $this->flow->start(GuidedFlow::FLOW_REQUEST_SUBMIT, $context, $message);
                }
                return $this->knowledge($context, $message, ['store_info', 'cms_page', 'manual'], 'You can reach our team through the contact page.');

            case IntentRouter::INTENT_CART:
                $result = $this->tools->execute('cart_info', [], $context);
                if ($result->getErrorCode() === 'unknown_tool') {
                    return ChatReply::create('I cannot see your cart from here, but you can review it on the cart page.')
                        ->addCard($this->cards->link('Open cart', '/checkout/cart/'))
                        ->setChips($this->flow->menuChips($context));
                }
                return ChatReply::create((string)$result->getText())->addCards($result->getCards())->setGrounded(true)->setChips($this->flow->menuChips($context));

            case IntentRouter::INTENT_PRODUCT_COMPARE:
                $reply = $this->compare($context, $message);
                if ($reply) {
                    return $reply;
                }
                return $this->products($context, $message) ?? $this->fallback($context, $message, null);

            case IntentRouter::INTENT_PRODUCT_SEARCH:
                return $this->products($context, $message) ?? $this->fallback($context, $message, null);

            case IntentRouter::INTENT_SHIPPING:
            case IntentRouter::INTENT_RETURNS:
            case IntentRouter::INTENT_PAYMENT:
            case IntentRouter::INTENT_STORE_INFO:
                return $this->knowledge($context, $message, ['cms_page', 'store_info', 'manual'])
                    ?? $this->fallback($context, $message, null);

            default:
                // Unknown: whichever of policy text / products is the better match wins.
                $kb = $this->retriever->search($message, $storeId, 3, ['cms_page', 'store_info', 'manual', 'category'], false);
                $kbConfidence = $kb !== [] ? (float)$kb[0]['confidence'] : 0.0;
                $found = $this->productFinder->find($message, $storeId, $this->config->getMaxProductCards($storeId));
                $productConfidence = $found['products'] !== [] ? (float)($found['products'][0]['score'] ?? 0) : 0.0;
                if ($productConfidence >= self::PRODUCT_CONFIDENCE && $productConfidence >= $kbConfidence) {
                    return $this->productReply($context, $found);
                }
                if ($kbConfidence >= self::KB_CONFIDENCE_UNKNOWN) {
                    return $this->knowledgeReply($context, $kb);
                }
                return $this->fallback($context, $message, max($kbConfidence, $productConfidence));
        }
    }

    private function knowledge(ChatContext $context, string $message, array $types, ?string $prefix = null): ?ChatReply
    {
        // Verbatim answers need lexical overlap with the question, so Assist mode ranks passages by keywords only.
        $results = $this->retriever->search($message, $context->getStoreId(), 3, $types, false);
        if ($results === [] || (float)$results[0]['confidence'] < self::KB_CONFIDENCE) {
            return null;
        }
        $reply = $this->knowledgeReply($context, $results);
        if ($prefix !== null) {
            $reply->setText($prefix . "\n\n" . $reply->getText());
        }
        return $reply;
    }

    private function knowledgeReply(ChatContext $context, array $results): ChatReply
    {
        $best = $results[0];
        $text = $this->presentable((string)$best['text'], (string)$best['title']);
        $reply = ChatReply::create(Text::truncate($text, 700))->setGrounded(true);
        if (!empty($best['url'])) {
            $reply->addCard($this->cards->link('Read more: ' . $best['title'], (string)$best['url']));
        }
        $reply->setSources(array_map(function ($row) {
            return ['title' => $row['title'], 'url' => $row['url'], 'document_id' => $row['document_id'], 'confidence' => $row['confidence']];
        }, $results));
        $chips = [];
        foreach (array_slice($results, 1, 2) as $other) {
            if (!empty($other['url'])) {
                $chips[] = ['label' => $other['title'], 'send' => 'Tell me about ' . $other['title']];
            }
        }
        $chips[] = ['label' => 'Talk to a human', 'send' => 'I want to talk to a human'];
        return $reply->setChips($chips);
    }

    private function products(ChatContext $context, string $message): ?ChatReply
    {
        $found = $this->productFinder->find($message, $context->getStoreId(), $this->config->getMaxProductCards($context->getStoreId()));
        if ($found['products'] === []) {
            return null;
        }
        return $this->productReply($context, $found);
    }

    private function productReply(ChatContext $context, array $found): ChatReply
    {
        $count = count($found['products']);
        $filters = $found['filters'];
        $bits = [];
        if (isset($filters['price_max'])) {
            $bits[] = 'under ' . $this->cards->format((float)$filters['price_max'], (string)($found['products'][0]['currency'] ?? ''));
        }
        if (isset($filters['price_min'])) {
            $bits[] = 'above ' . $this->cards->format((float)$filters['price_min'], (string)($found['products'][0]['currency'] ?? ''));
        }
        if (($filters['availability'] ?? '') === 'in_stock') {
            $bits[] = 'ready to ship';
        }
        $text = $count === 1 ? 'Here is a piece that matches' : sprintf('Here are %d pieces that match', $count);
        $text .= $bits !== [] ? ' (' . implode(', ', $bits) . '):' : ':';
        return ChatReply::create($text)
            ->addCards($found['products'])
            ->setGrounded(true)
            ->setChips([
                ['label' => 'Ready to ship only', 'send' => $found['query'] . ' ready to ship'],
                ['label' => 'Show more', 'send' => 'Show me more ' . $found['query']],
                ['label' => 'Talk to a human', 'send' => 'I want to talk to a human'],
            ]);
    }

    private function compare(ChatContext $context, string $message): ?ChatReply
    {
        $parts = preg_split('/\b(?:vs\.?|versus|and|or|,|compare|with|between)\b/i', $message) ?: [];
        $names = [];
        foreach ($parts as $part) {
            $part = trim(preg_replace('/\b(the|difference|which|is|better|what|please)\b/i', ' ', $part) ?? $part);
            $part = trim(preg_replace('/\s+/', ' ', $part) ?? $part);
            if (mb_strlen($part) >= 3) {
                $names[] = $part;
            }
        }
        if (count($names) < 2) {
            return null;
        }
        $result = $this->tools->execute('product_compare', ['products' => array_slice($names, 0, 4)], $context);
        if ($result->isError()) {
            return null;
        }
        return ChatReply::create((string)$result->getText())->addCards($result->getCards())->setGrounded(true)->setChips($this->flow->menuChips($context));
    }

    private function fallback(ChatContext $context, string $message, ?float $bestConfidence): ChatReply
    {
        $reply = ChatReply::create("I'm not sure I have the answer to that. Here is what I can help with:")
            ->setChips($this->flow->menuChips($context))
            ->setUnanswered(true);
        return $reply;
    }

    /**
     * The chunk starts with the title line the chunker prefixed; drop it and the sheet-style prefix for CMS text.
     */
    private function presentable(string $text, string $title): string
    {
        $lines = explode("\n", $text);
        if ($lines !== [] && trim($lines[0]) === trim($title)) {
            array_shift($lines);
        }
        $text = trim(implode("\n", $lines));
        // Overlapping chunks can start mid-sentence; begin at the first sentence/paragraph boundary instead.
        if ($text !== '' && preg_match('/^\p{Ll}/u', $text)) {
            if (preg_match('/[.!?]\s+|\n/u', $text, $m, PREG_OFFSET_CAPTURE)) {
                $text = trim(mb_strcut($text, $m[0][1] + strlen($m[0][0])));
            }
        }
        return $text;
    }

    private function questionChips(ChatContext $context): array
    {
        $chips = [];
        foreach ($this->config->getDefaultQuestions($context->getStoreId()) as $question) {
            $chips[] = ['label' => $question, 'send' => $question];
        }
        return $chips !== [] ? $chips : $this->flow->menuChips($context);
    }
}
