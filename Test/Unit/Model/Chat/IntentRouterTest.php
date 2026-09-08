<?php
declare(strict_types=1);

namespace WB\AiChatbot\Test\Unit\Model\Chat;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use PHPUnit\Framework\TestCase;
use WB\AiChatbot\Model\Chat\IntentRouter;

/**
 * Exercises the built-in dictionaries (the DB returns no overrides).
 */
class IntentRouterTest extends TestCase
{
    private function router(): IntentRouter
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchAll')->willReturn([]);
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        return new IntentRouter($resource);
    }

    /**
     * @dataProvider messages
     */
    public function testDetect(string $message, string $expected): void
    {
        $this->assertSame($expected, $this->router()->detect($message)['intent'], $message);
    }

    public function messages(): array
    {
        return [
            ['hi', IntentRouter::INTENT_GREETING],
            ['Thanks!', IntentRouter::INTENT_THANKS],
            ['cancel', IntentRouter::INTENT_CANCEL],
            ['where is my order', IntentRouter::INTENT_ORDER_STATUS],
            ['track my parcel please', IntentRouter::INTENT_ORDER_TRACKING],
            ['I need the invoice for my order', IntentRouter::INTENT_ORDER_INVOICE],
            ['what happened to SR-A1B2C3', IntentRouter::INTENT_REQUEST_STATUS],
            ['I want to talk to a human', IntentRouter::INTENT_HUMAN],
            ["what's in my cart", IntentRouter::INTENT_CART],
            ['compare GRACE and GAIA', IntentRouter::INTENT_PRODUCT_COMPARE],
            ['what is your return policy', IntentRouter::INTENT_RETURNS],
            ['do you ship to canada', IntentRouter::INTENT_SHIPPING],
            ['can I pay by paypal', IntentRouter::INTENT_PAYMENT],
            ['what are your opening hours', IntentRouter::INTENT_STORE_INFO],
            ['show me gold jhumkas under 15000', IntentRouter::INTENT_PRODUCT_SEARCH],
            ['what is the meaning of life', IntentRouter::INTENT_UNKNOWN],
        ];
    }
}
