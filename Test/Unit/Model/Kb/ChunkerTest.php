<?php
declare(strict_types=1);

namespace WB\AiChatbot\Test\Unit\Model\Kb;

use PHPUnit\Framework\TestCase;
use WB\AiChatbot\Model\Kb\Chunker;
use WB\AiChatbot\Model\Kb\ChunkStorage;

class ChunkerTest extends TestCase
{
    public function testShortBodyIsOneChunkWithTitle(): void
    {
        $chunker = new Chunker(400, 50);
        $chunks = $chunker->chunk('Shipping Policy', 'We ship worldwide.');
        $this->assertCount(1, $chunks);
        $this->assertSame("Shipping Policy\nWe ship worldwide.", $chunks[0]['text']);
        $this->assertGreaterThan(0, $chunks[0]['token_count']);
    }

    public function testLongBodySplitsWithOverlap(): void
    {
        $chunker = new Chunker(200, 40);
        $paragraphs = [];
        for ($i = 1; $i <= 8; $i++) {
            $paragraphs[] = "Paragraph number $i talks about delivery times and packaging details at length.";
        }
        $chunks = $chunker->chunk('FAQ', implode("\n\n", $paragraphs));
        $this->assertGreaterThan(2, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertStringStartsWith('FAQ', $chunk['text']);
            $this->assertLessThanOrEqual(200 + 40 + 5, mb_strlen($chunk['text']));
        }
        // consecutive chunks share text (overlap)
        $this->assertStringContainsString('Paragraph number 2', $chunks[0]['text'] . $chunks[1]['text']);
    }

    public function testEmptyBodyFallsBackToTitle(): void
    {
        $chunker = new Chunker();
        $this->assertSame([], $chunker->chunk('', '   '));
        $this->assertSame('Only title', $chunker->chunk('Only title', '')[0]['text']);
    }

    public function testPackUnpackAndCosine(): void
    {
        $vector = [0.5, -1.25, 3.0];
        $this->assertSame($vector, ChunkStorage::unpack(ChunkStorage::pack($vector)));
        $this->assertEqualsWithDelta(1.0, ChunkStorage::cosine([1, 2, 3], [2, 4, 6]), 0.000001);
        $this->assertEqualsWithDelta(0.0, ChunkStorage::cosine([1, 0], [0, 1]), 0.000001);
        $this->assertSame(0.0, ChunkStorage::cosine([], [1]));
    }
}
