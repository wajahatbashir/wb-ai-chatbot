<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb\Sync;

/**
 * HTML -> readable plain text for the knowledge base (keeps list/paragraph structure as line breaks).
 */
class TextCleaner
{
    public function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }
        $text = preg_replace('#<(script|style|noscript|svg)[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $text = preg_replace('#<br\s*/?>#i', "\n", $text) ?? $text;
        $text = preg_replace('#</(p|div|li|h[1-6]|tr|section|article|blockquote|table|ul|ol)>#i', "\n", $text) ?? $text;
        $text = preg_replace('#<li[^>]*>#i', '- ', $text) ?? $text;
        $text = preg_replace('#</t[dh]>#i', ' | ', $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xC2\xA0", ' ', $text);
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/ *\n */", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }
}
