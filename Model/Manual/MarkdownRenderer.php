<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Manual;

/**
 * Small, dependency-free Markdown -> HTML renderer purpose-built for USER_MANUAL.md, so the manual can be read
 * inside the admin panel without adding a Composer package just to render one document. Covers exactly the
 * constructs the manual uses: headings (h1-h3, auto-slugged the same way GitHub does, so the manual's own
 * hand-written `#anchor` links keep working), paragraphs, bold, italic, inline code, links, fenced code blocks,
 * ordered and unordered lists (one level of nesting), GFM pipe tables, and horizontal rules. Not a general CommonMark
 * implementation - if the manual grows constructs beyond this list, extend it rather than reaching for a library.
 */
class MarkdownRenderer
{
    /**
     * @return array{html: string, headings: array<int, array{level:int, text:string, slug:string}>}
     */
    public function render(string $markdown): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];
        $html = [];
        $headings = [];
        $slugCounts = [];

        $paragraph = [];
        $listStack = []; // stack of ['type' => ul|ol, 'indent' => int]
        $tableRows = [];
        $inFence = false;
        $fenceLang = '';
        $fenceLines = [];

        $flushParagraph = function () use (&$paragraph, &$html) {
            if ($paragraph !== []) {
                $html[] = '<p>' . $this->inline(implode(' ', $paragraph)) . '</p>';
                $paragraph = [];
            }
        };
        $closeLists = function (int $downToIndent = -1) use (&$listStack, &$html) {
            while ($listStack !== [] && end($listStack)['indent'] > $downToIndent) {
                $html[] = '</li></' . array_pop($listStack)['type'] . '>';
            }
        };
        $flushTable = function () use (&$tableRows, &$html) {
            if ($tableRows === []) {
                return;
            }
            $header = array_shift($tableRows);
            array_shift($tableRows); // separator row (---|---)
            $html[] = '<div class="wb-manual__tablewrap"><table>';
            $html[] = '<thead><tr>' . implode('', array_map(function ($cell) {
                return '<th>' . $this->inline($cell) . '</th>';
            }, $header)) . '</tr></thead>';
            $html[] = '<tbody>';
            foreach ($tableRows as $row) {
                $html[] = '<tr>' . implode('', array_map(function ($cell) {
                    return '<td>' . $this->inline($cell) . '</td>';
                }, $row)) . '</tr>';
            }
            $html[] = '</tbody></table></div>';
            $tableRows = [];
        };

        foreach ($lines as $line) {
            // Fenced code blocks: everything inside is literal, no block-level parsing.
            if (preg_match('/^```\s*([a-z0-9]*)\s*$/i', $line, $m)) {
                if (!$inFence) {
                    $flushParagraph();
                    $closeLists();
                    $flushTable();
                    $inFence = true;
                    $fenceLang = strtolower($m[1]);
                    $fenceLines = [];
                } else {
                    $class = $fenceLang !== '' ? ' class="language-' . $this->escape($fenceLang) . '"' : '';
                    $html[] = '<pre class="wb-manual__code"><code' . $class . '>' . $this->escape(implode("\n", $fenceLines)) . '</code></pre>';
                    $inFence = false;
                }
                continue;
            }
            if ($inFence) {
                $fenceLines[] = $line;
                continue;
            }

            $trimmed = rtrim($line);

            if (trim($trimmed) === '') {
                $flushParagraph();
                $closeLists();
                $flushTable();
                continue;
            }

            if (preg_match('/^(#{1,3})\s+(.+)$/', $trimmed, $m)) {
                $flushParagraph();
                $closeLists();
                $flushTable();
                $level = strlen($m[1]);
                $text = trim($m[2]);
                $slug = $this->slug($text);
                if (isset($slugCounts[$slug])) {
                    $slugCounts[$slug]++;
                    $slug .= '-' . $slugCounts[$slug];
                } else {
                    $slugCounts[$slug] = 0;
                }
                $html[] = '<h' . $level . ' id="' . $this->escape($slug) . '">' . $this->inline($text) . '</h' . $level . '>';
                if ($level <= 2) {
                    $headings[] = ['level' => $level, 'text' => $text, 'slug' => $slug];
                }
                continue;
            }

            if (trim($trimmed) === '---') {
                $flushParagraph();
                $closeLists();
                $flushTable();
                $html[] = '<hr/>';
                continue;
            }

            if (strpos(ltrim($trimmed), '|') === 0) {
                $flushParagraph();
                $closeLists();
                $tableRows[] = $this->tableCells($trimmed);
                continue;
            }
            $flushTable();

            if (preg_match('/^(\s*)([-*])\s+(.+)$/', $line, $m)) {
                $flushParagraph();
                $indent = (int)floor(strlen($m[1]) / 2);
                $this->openListLevel($listStack, $html, 'ul', $indent);
                // Left open deliberately - closed either by the next sibling <li> or by closeLists()/openListLevel().
                $html[] = '<li>' . $this->inline($m[3]);
                continue;
            }

            if (preg_match('/^(\s*)\d+\.\s+(.+)$/', $line, $m)) {
                $flushParagraph();
                $indent = (int)floor(strlen($m[1]) / 2);
                $this->openListLevel($listStack, $html, 'ol', $indent);
                $html[] = '<li>' . $this->inline($m[2]);
                continue;
            }

            // Lazy continuation: a plain text line right after an open list item is Markdown's soft-wrap of that
            // same item's text (e.g. a bullet's sentence wrapping onto a second source line), not a new paragraph -
            // append it to the still-open <li> instead of closing the list.
            if ($listStack !== [] && $html !== [] && substr(end($html), 0, 4) === '<li>') {
                $html[count($html) - 1] .= ' ' . $this->inline(trim($trimmed));
                continue;
            }

            $closeLists();
            $paragraph[] = trim($trimmed);
        }

        $flushParagraph();
        $closeLists();
        $flushTable();
        if ($inFence && $fenceLines !== []) {
            $html[] = '<pre class="wb-manual__code"><code>' . $this->escape(implode("\n", $fenceLines)) . '</code></pre>';
        }

        return ['html' => implode("\n", $html), 'headings' => $headings];
    }

    /**
     * @param array<int, array{type:string, indent:int}> $listStack
     * @param string[] $html
     */
    private function openListLevel(array &$listStack, array &$html, string $type, int $indent): void
    {
        // Close deeper/mismatched levels, then close the previous <li> at this level (siblings), then open as needed.
        while ($listStack !== [] && end($listStack)['indent'] > $indent) {
            $html[] = '</li></' . array_pop($listStack)['type'] . '>';
        }
        if ($listStack !== [] && end($listStack)['indent'] === $indent) {
            if (end($listStack)['type'] === $type) {
                $html[] = '</li>';
                return;
            }
            $html[] = '</li></' . array_pop($listStack)['type'] . '>';
        }
        $html[] = '<' . $type . '>';
        $listStack[] = ['type' => $type, 'indent' => $indent];
    }

    /**
     * @return string[]
     */
    private function tableCells(string $line): array
    {
        $line = trim($line);
        $line = preg_replace('/^\|/', '', $line) ?? $line;
        $line = preg_replace('/\|$/', '', $line) ?? $line;
        return array_map('trim', preg_split('/(?<!\\\\)\|/', $line) ?: []);
    }

    /**
     * Escapes HTML first, then applies inline formatting on top of the escaped text (code spans are protected
     * with placeholders so `**`/`[...]` inside `` `code` `` is never touched).
     */
    private function inline(string $text): string
    {
        $text = $this->escape($text);

        $codeSpans = [];
        $text = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$codeSpans) {
            $token = "\x00CODE" . count($codeSpans) . "\x00";
            $codeSpans[] = '<code>' . $m[1] . '</code>';
            return $token;
        }, $text) ?? $text;

        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)]+)\)/', function ($m) {
            $href = $m[2];
            $external = strpos($href, 'http') === 0;
            return '<a href="' . $href . '"' . ($external ? ' target="_blank" rel="noopener"' : '') . '>' . $m[1] . '</a>';
        }, $text) ?? $text;

        $text = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $text) ?? $text;
        // Single asterisks, after bold is already consumed above so no stray ** pairs are left to confuse this.
        $text = preg_replace('/\*([^*\n]+)\*/', '<em>$1</em>', $text) ?? $text;

        if ($codeSpans !== []) {
            $text = str_replace(array_map(function ($i) {
                return "\x00CODE" . $i . "\x00";
            }, array_keys($codeSpans)), $codeSpans, $text);
        }

        return $text;
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * GitHub-style heading slug: lowercase, strip everything but letters/digits/spaces/hyphens, spaces -> hyphens.
     */
    private function slug(string $text): string
    {
        $slug = mb_strtolower($text);
        $slug = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $slug) ?? $slug;
        $slug = trim(preg_replace('/\s+/', '-', trim($slug)) ?? '', '-');
        return $slug !== '' ? $slug : 'section';
    }
}
