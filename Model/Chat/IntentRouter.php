<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

use Magento\Framework\App\ResourceConnection;

/**
 * Rule-based intent detection for Assist mode. Dictionaries live in wb_aichatbot_intent (admin-editable,
 * per language); the built-in English defaults below are seeded by a data patch and act as the fallback.
 *
 * A dictionary line is either a plain phrase (all words must appear, in any order) or a regex written as /.../i.
 */
class IntentRouter
{
    public const INTENT_GREETING = 'greeting';
    public const INTENT_THANKS = 'thanks';
    public const INTENT_ORDER_STATUS = 'order_status';
    public const INTENT_ORDER_TRACKING = 'order_tracking';
    public const INTENT_ORDER_INVOICE = 'order_invoice';
    public const INTENT_REQUEST_STATUS = 'request_status';
    public const INTENT_HUMAN = 'human';
    public const INTENT_SHIPPING = 'shipping';
    public const INTENT_RETURNS = 'returns';
    public const INTENT_PAYMENT = 'payment';
    public const INTENT_STORE_INFO = 'store_info';
    public const INTENT_PRODUCT_SEARCH = 'product_search';
    public const INTENT_PRODUCT_COMPARE = 'product_compare';
    public const INTENT_CART = 'cart';
    public const INTENT_CANCEL = 'cancel';
    public const INTENT_UNKNOWN = 'unknown';

    /**
     * Built-in defaults (English). Order matters only through priority; higher wins on ties.
     */
    public const DEFAULTS = [
        self::INTENT_CANCEL => ['priority' => 100, 'keywords' => "/^(cancel|stop|never mind|nevermind|back|menu|start over|restart)\\.?$/i"],
        self::INTENT_ORDER_TRACKING => ['priority' => 60, 'keywords' => "track\ntracking\nwhere is my parcel\nwhere is my package\nshipped yet\ndelivery status\ncourier\ntracking number\nconsignment"],
        self::INTENT_ORDER_INVOICE => ['priority' => 60, 'keywords' => "invoice\nreceipt\nbill for my order\ndownload invoice"],
        self::INTENT_REQUEST_STATUS => ['priority' => 58, 'keywords' => "/\\bSR-[A-Z0-9]{6}\\b/i\nmy ticket\nmy request\nsupport request status\ncomplaint status"],
        self::INTENT_ORDER_STATUS => ['priority' => 55, 'keywords' => "my order\norder status\nwhere is my order\ncheck order\norder number\nstatus of my order\nwhen will my order\nhas my order\norder update\n/\\b(order|purchase)\\b.*\\b(status|arrive|delivered|dispatch|update)\\b/i"],
        self::INTENT_HUMAN => ['priority' => 50, 'keywords' => "talk to a human\nspeak to someone\nreal person\ncustomer service\ncustomer support\ncontact support\ncontact you\nhelp me with a problem\ncomplaint\nagent\nrepresentative\nsupport request\nraise a ticket\nopen a ticket"],
        self::INTENT_CART => ['priority' => 45, 'keywords' => "my cart\nmy basket\nin my bag\nshopping bag\ncart total\nwhat's in my cart"],
        self::INTENT_PRODUCT_COMPARE => ['priority' => 42, 'keywords' => "compare\ndifference between\nversus\n/\\bvs\\.?\\b/i\nwhich is better"],
        self::INTENT_RETURNS => ['priority' => 40, 'keywords' => "return\nrefund\nexchange\nmoney back\nreturn policy\nsend it back\ndamaged\nwrong item\nwrong size"],
        self::INTENT_SHIPPING => ['priority' => 38, 'keywords' => "shipping\ndelivery\ndeliver\nship to\nhow long\ndelivery time\ndelivery charges\nshipping cost\nshipping fee\ninternational\nworldwide\ncash on delivery\n/\\bcod\\b/i"],
        self::INTENT_PAYMENT => ['priority' => 36, 'keywords' => "payment\npay\ncredit card\ndebit card\npaypal\nbank transfer\ninstallment\ninstalment\npayment method\npayment options"],
        self::INTENT_STORE_INFO => ['priority' => 34, 'keywords' => "opening hours\nstore hours\nyour address\nwhere are you located\nphone number\ncontact number\nemail address\nabout you\nabout the brand\nwho are you\nphysical store\nshowroom"],
        self::INTENT_PRODUCT_SEARCH => ['priority' => 30, 'keywords' => "looking for\nshow me\ndo you have\nrecommend\nsuggest\nsuggestion\nnecklace\nearring\nearrings\nring\nbracelet\nbangle\nchoker\nmaang tikka\ntikka\njhumka\njhumkas\nset\nbridal\npolki\nkundan\nmeenakari\npearl\nemerald\nruby\nsapphire\ngold\nsilver\nunder\nbudget\nprice range\nready to ship\nin stock\nnew arrivals\nbest seller\ngift"],
        self::INTENT_THANKS => ['priority' => 20, 'keywords' => "/^(thanks|thank you|thankyou|great|perfect|awesome|ok|okay|cool|got it)[.! ]*$/i"],
        self::INTENT_GREETING => ['priority' => 10, 'keywords' => "/^(hi|hello|hey|hii|salam|assalam|assalamualaikum|good (morning|afternoon|evening))\\b[^a-z]*$/i"],
    ];

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var array|null
     */
    private $dictionaries;

    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    /**
     * @return array{intent:string, score:int}
     */
    public function detect(string $message, string $language = 'en'): array
    {
        $normalized = Text::normalize($message);
        $tokens = Text::tokens($message);
        if ($normalized === '') {
            return ['intent' => self::INTENT_UNKNOWN, 'score' => 0];
        }
        $best = ['intent' => self::INTENT_UNKNOWN, 'score' => 0];
        foreach ($this->dictionaries($language) as $intent => $entry) {
            $hits = 0;
            foreach ($entry['patterns'] as $pattern) {
                if ($this->matches($pattern, $message, $normalized, $tokens)) {
                    $hits++;
                }
            }
            if ($hits === 0) {
                continue;
            }
            $score = (int)$entry['priority'] + $hits;
            if ($score > $best['score']) {
                $best = ['intent' => (string)$intent, 'score' => $score];
            }
        }
        return $best;
    }

    /**
     * Product intent also fires when the message contains words that match product/category names strongly;
     * the engine decides that using retrieval, so here we only expose the dictionary result.
     *
     * @return array<string, array{priority:int, patterns:string[]}>
     */
    private function dictionaries(string $language): array
    {
        if ($this->dictionaries === null) {
            $this->dictionaries = [];
            $connection = $this->resource->getConnection();
            $rows = $connection->fetchAll(
                $connection->select()
                    ->from($this->resource->getTableName('wb_aichatbot_intent'), ['intent_code', 'language', 'keywords', 'priority'])
                    ->where('is_enabled = 1')
            );
            foreach ($rows as $row) {
                $this->dictionaries[(string)$row['language']][(string)$row['intent_code']] = [
                    'priority' => (int)$row['priority'],
                    'patterns' => $this->lines((string)$row['keywords']),
                ];
            }
        }
        $result = [];
        foreach (self::DEFAULTS as $intent => $entry) {
            $result[$intent] = ['priority' => $entry['priority'], 'patterns' => $this->lines($entry['keywords'])];
        }
        // Language-specific dictionaries extend/override the defaults; "en" rows override the built-ins too.
        foreach (['en', $language] as $lang) {
            foreach ($this->dictionaries[$lang] ?? [] as $intent => $entry) {
                $result[$intent] = $entry;
            }
        }
        return $result;
    }

    /**
     * @return string[]
     */
    private function lines(string $text): array
    {
        $lines = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && $line[0] !== '#') {
                $lines[] = $line;
            }
        }
        return $lines;
    }

    /**
     * @param string[] $tokens
     */
    private function matches(string $pattern, string $raw, string $normalized, array $tokens): bool
    {
        if (strlen($pattern) > 2 && $pattern[0] === '/' && preg_match('#^/.*/[a-z]*$#s', $pattern)) {
            return @preg_match($pattern, trim($raw)) === 1;
        }
        $words = Text::tokens($pattern);
        if ($words === []) {
            return false;
        }
        if (count($words) === 1) {
            // single keyword: whole-word match, allow simple plural
            $word = $words[0];
            foreach ($tokens as $token) {
                if ($token === $word || $token === $word . 's' || $word === $token . 's') {
                    return true;
                }
            }
            return false;
        }
        // phrase: all words present in order (allowing other words between) or as a contiguous substring
        if (mb_strpos($normalized, implode(' ', $words)) !== false) {
            return true;
        }
        $position = 0;
        foreach ($words as $word) {
            $found = false;
            for ($i = $position; $i < count($tokens); $i++) {
                if ($tokens[$i] === $word) {
                    $position = $i + 1;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }
        return true;
    }
}
