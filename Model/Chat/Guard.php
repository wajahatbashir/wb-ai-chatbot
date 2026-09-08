<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

/**
 * Keeps retrieved content and customer input from steering the model: strips prompt-injection phrasing from
 * knowledge base excerpts, caps sizes, and judges whether a reply admits it has no answer.
 */
class Guard
{
    private const INJECTION_PATTERNS = [
        '/\b(ignore|disregard|forget)\b.{0,40}\b(previous|above|prior|all|your)\b.{0,20}\b(instructions?|rules?|prompt)/i',
        '/\byou are now\b/i',
        '/\bsystem prompt\b/i',
        '/\bact as (an? )?(admin|developer|system)\b/i',
        '/\breveal (your|the) (instructions|prompt|rules)\b/i',
    ];

    private const NO_ANSWER_PATTERNS = [
        '/\b(i )?(don\'t|do not|cannot|can\'t) (know|find|see|have (that|this|the) information)\b/i',
        '/\bnot (sure|certain|able to find)\b/i',
        '/\bno information (about|on)\b/i',
        '/\bcould(n\'t| not) find\b/i',
        '/\bI (am )?unable to\b/i',
    ];

    public function sanitizeExcerpt(string $text, int $maxChars = 1800): string
    {
        $lines = [];
        foreach (explode("\n", $text) as $line) {
            $drop = false;
            foreach (self::INJECTION_PATTERNS as $pattern) {
                if (preg_match($pattern, $line)) {
                    $drop = true;
                    break;
                }
            }
            if (!$drop) {
                $lines[] = $line;
            }
        }
        return mb_substr(implode("\n", $lines), 0, $maxChars);
    }

    public function looksUnanswered(string $reply): bool
    {
        foreach (self::NO_ANSWER_PATTERNS as $pattern) {
            if (preg_match($pattern, $reply)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Masks emails, phone numbers and card-like digit runs in stored transcripts (Limits & Security > Redact PII).
     * The live request still sees the real values; only what is persisted is masked.
     */
    public function redact(string $text): string
    {
        $text = preg_replace('/[A-Z0-9._%+-]+@([A-Z0-9.-]+\.[A-Z]{2,})/i', '***@$1', $text) ?? $text;
        $text = preg_replace('/\b\d(?:[ -]?\d){12,18}\b/', '[card number]', $text) ?? $text;
        $text = preg_replace_callback('/(?<![\d-])(\+?\d[\d ()-]{8,}\d)(?![\d-])/', function ($m) {
            // keep order numbers (pure 6-12 digit runs) readable, mask everything that looks like a phone number
            return preg_match('/^\d{6,12}$/', $m[1]) ? $m[1] : '[phone]';
        }, $text) ?? $text;
        return $text;
    }

    /**
     * Customer text goes to the model as data; we only trim and cap it.
     */
    public function cleanUserMessage(string $message, int $maxChars): string
    {
        $message = trim(preg_replace("/[ \t]+/", ' ', $message) ?? $message);
        $message = preg_replace("/\n{3,}/", "\n\n", $message) ?? $message;
        return mb_substr($message, 0, $maxChars);
    }
}
