<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

/**
 * USD per 1M tokens [input, output]. Override or extend the table in di.xml ("prices" argument).
 */
class CostCalculator
{
    private const DEFAULT_PRICES = [
        'gpt-4.1-mini' => [0.40, 1.60],
        'gpt-4.1' => [2.00, 8.00],
        'gpt-4o-mini' => [0.15, 0.60],
        'gpt-4o' => [2.50, 10.00],
        'gpt-5-mini' => [0.25, 2.00],
        'gpt-5' => [1.25, 10.00],
        'text-embedding-3-small' => [0.02, 0.0],
        'text-embedding-3-large' => [0.13, 0.0],
        'claude-sonnet-4-5' => [3.00, 15.00],
        'claude-haiku-4-5' => [1.00, 5.00],
        'claude-opus-4-1' => [15.00, 75.00],
        'gemini-2.5-flash' => [0.30, 2.50],
        'gemini-2.5-pro' => [1.25, 10.00],
        'gemini-embedding-001' => [0.15, 0.0],
        'grok-4' => [3.00, 15.00],
        'grok-3-mini' => [0.30, 0.50],
        'grok-3' => [3.00, 15.00],
        'mock-1' => [0.0, 0.0],
    ];

    /**
     * @var array<string, array{0: float, 1: float}>
     */
    private $prices;

    public function __construct(array $prices = [])
    {
        $this->prices = self::DEFAULT_PRICES;
        foreach ($prices as $model => $pair) {
            if (is_array($pair) && count($pair) >= 2) {
                $this->prices[(string)$model] = [(float)$pair[0], (float)$pair[1]];
            }
        }
    }

    public function cost(?string $model, int $promptTokens, int $completionTokens): float
    {
        $pair = $this->lookup((string)$model);
        if ($pair === null) {
            return 0.0;
        }
        return round(($promptTokens * $pair[0] + $completionTokens * $pair[1]) / 1000000, 6);
    }

    /**
     * Exact match first, then longest prefix (e.g. "gpt-4.1-mini-2025-04-14" -> gpt-4.1-mini).
     */
    private function lookup(string $model): ?array
    {
        if (isset($this->prices[$model])) {
            return $this->prices[$model];
        }
        $best = null;
        $bestLength = 0;
        foreach ($this->prices as $key => $pair) {
            if (strpos($model, $key) === 0 && strlen($key) > $bestLength) {
                $best = $pair;
                $bestLength = strlen($key);
            }
        }
        return $best;
    }
}
