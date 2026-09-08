<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb;

/**
 * Splits document text into overlapping chunks sized for embedding and prompt context.
 * Splits on paragraph boundaries first, then sentences, so a chunk rarely cuts mid-thought.
 */
class Chunker
{
    /**
     * @var int target size in characters (~4 characters per token => ~400 tokens)
     */
    private $targetChars;

    /**
     * @var int
     */
    private $overlapChars;

    public function __construct(int $targetChars = 1600, int $overlapChars = 200)
    {
        $this->targetChars = max(200, $targetChars);
        $this->overlapChars = max(0, min($overlapChars, (int)($this->targetChars / 2)));
    }

    /**
     * @return array [['text' => string, 'token_count' => int], ...]
     */
    public function chunk(string $title, string $body): array
    {
        $text = trim(preg_replace("/[ \t]+/", ' ', $body) ?? '');
        if ($text === '') {
            return $title !== '' ? [$this->make($title)] : [];
        }

        $units = [];
        foreach (preg_split("/\n{2,}/", $text) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            if (mb_strlen($paragraph) <= $this->targetChars) {
                $units[] = $paragraph;
                continue;
            }
            foreach (preg_split('/(?<=[.!?])\s+/u', $paragraph) ?: [] as $sentence) {
                $sentence = trim($sentence);
                if ($sentence === '') {
                    continue;
                }
                while (mb_strlen($sentence) > $this->targetChars) {
                    $units[] = mb_substr($sentence, 0, $this->targetChars);
                    $sentence = mb_substr($sentence, $this->targetChars);
                }
                $units[] = $sentence;
            }
        }

        $chunks = [];
        $current = '';
        foreach ($units as $unit) {
            $candidate = $current === '' ? $unit : $current . "\n" . $unit;
            if (mb_strlen($candidate) > $this->targetChars && $current !== '') {
                $chunks[] = $this->make($this->withTitle($title, $current));
                $tail = $this->overlapChars > 0 ? mb_substr($current, -$this->overlapChars) : '';
                $current = trim($tail === '' ? $unit : $tail . "\n" . $unit);
            } else {
                $current = $candidate;
            }
        }
        if (trim($current) !== '') {
            $chunks[] = $this->make($this->withTitle($title, $current));
        }
        return $chunks;
    }

    public static function estimateTokens(string $text): int
    {
        return (int)ceil(mb_strlen($text) / 4);
    }

    private function withTitle(string $title, string $text): string
    {
        // Every chunk carries the document title so a retrieved fragment is self-describing in the prompt.
        return $title !== '' && mb_strpos($text, $title) !== 0 ? $title . "\n" . $text : $text;
    }

    private function make(string $text): array
    {
        return ['text' => $text, 'token_count' => self::estimateTokens($text)];
    }
}
