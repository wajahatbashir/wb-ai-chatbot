<?php
declare(strict_types=1);

namespace WB\AiChatbot\Test\Unit\Model\Chat;

use PHPUnit\Framework\TestCase;
use WB\AiChatbot\Model\Chat\Text;

class TextTest extends TestCase
{
    public function testTokensAndNormalize(): void
    {
        $this->assertSame(['gold', 'polki', 'earrings', '15000'], Text::tokens('Gold  POLKI, earrings - 15000!'));
        $this->assertSame('gold polki earrings', Text::normalize('Gold POLKI earrings'));
    }

    public function testSimilarity(): void
    {
        $this->assertSame(1.0, Text::similarity('do you offer cash on delivery', 'Do you offer cash on delivery?'));
        $this->assertGreaterThan(0.5, Text::similarity('is cash on delivery available', 'cash on delivery available'));
        $this->assertSame(0.0, Text::similarity('hello', ''));
    }

    public function testExtractors(): void
    {
        $this->assertSame('a.b@example.com', Text::looksLikeEmail('my email is A.B@Example.com thanks'));
        $this->assertNull(Text::looksLikeEmail('no email here'));
        $this->assertSame('000000034', Text::looksLikeOrderNumber('order #000000034 please'));
        $this->assertNull(Text::looksLikeOrderNumber('under 15000'));
        $this->assertSame('123456', Text::looksLikeCode('123456'));
        $this->assertSame('654321', Text::looksLikeCode('the code is 654321'));
        $this->assertNull(Text::looksLikeCode('000000034'));
    }

    public function testTruncateKeepsSentence(): void
    {
        $text = 'First sentence here. Second sentence is longer than the limit allows for sure.';
        $result = Text::truncate($text, 40);
        $this->assertSame('First sentence here. …', $result);
        $this->assertSame('short', Text::truncate('short', 40));
    }
}
