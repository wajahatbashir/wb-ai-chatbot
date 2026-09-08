<?php
declare(strict_types=1);

namespace WB\AiChatbot\Test\Unit\Model\Chat;

use PHPUnit\Framework\TestCase;
use WB\AiChatbot\Model\Chat\CostCalculator;

class CostCalculatorTest extends TestCase
{
    public function testKnownModel(): void
    {
        $calc = new CostCalculator();
        // gpt-4.1-mini: $0.40 / $1.60 per 1M
        $this->assertSame(0.002, $calc->cost('gpt-4.1-mini', 1000, 1000));
    }

    public function testPrefixMatchAndUnknown(): void
    {
        $calc = new CostCalculator();
        $this->assertSame(0.002, $calc->cost('gpt-4.1-mini-2025-04-14', 1000, 1000));
        $this->assertSame(0.0, $calc->cost('some-unknown-model', 1000, 1000));
        $this->assertSame(0.0, $calc->cost(null, 10, 10));
    }

    public function testOverride(): void
    {
        $calc = new CostCalculator(['gpt-4.1-mini' => [1.0, 1.0]]);
        $this->assertSame(0.002, $calc->cost('gpt-4.1-mini', 1000, 1000));
    }
}
