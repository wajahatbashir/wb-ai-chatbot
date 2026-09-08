<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use WB\AiChatbot\Model\Chat\Conversation;
use WB\AiChatbot\Model\Chat\IntentRouter;
use WB\AiChatbot\Model\Chat\RequestManager;

/**
 * One class, several option lists (configured through the "list" constructor argument in di.xml virtual types).
 */
class Options implements OptionSourceInterface
{
    public const LISTS = [
        'request_status' => [
            RequestManager::STATUS_NEW => 'New',
            RequestManager::STATUS_IN_PROGRESS => 'In progress',
            RequestManager::STATUS_RESOLVED => 'Resolved',
            RequestManager::STATUS_CLOSED => 'Closed',
        ],
        'guidance_status' => ['enabled' => 'Enabled', 'disabled' => 'Disabled', 'testing' => 'Testing (preview only)'],
        'unanswered_status' => ['new' => 'New', 'answered' => 'Answered', 'ignored' => 'Ignored'],
        'conversation_status' => [
            Conversation::STATUS_OPEN => 'Open',
            Conversation::STATUS_CLOSED => 'Closed',
            Conversation::STATUS_ESCALATED => 'Escalated (support request)',
        ],
        'mode' => [Conversation::MODE_AI => 'AI', Conversation::MODE_ASSIST => 'Assist'],
        'intent_code' => [
            IntentRouter::INTENT_ORDER_STATUS => 'Order status',
            IntentRouter::INTENT_ORDER_TRACKING => 'Order tracking',
            IntentRouter::INTENT_ORDER_INVOICE => 'Order invoice',
            IntentRouter::INTENT_REQUEST_STATUS => 'Support request status',
            IntentRouter::INTENT_HUMAN => 'Talk to a human',
            IntentRouter::INTENT_CART => 'Cart',
            IntentRouter::INTENT_PRODUCT_SEARCH => 'Product search',
            IntentRouter::INTENT_PRODUCT_COMPARE => 'Product comparison',
            IntentRouter::INTENT_SHIPPING => 'Shipping & delivery',
            IntentRouter::INTENT_RETURNS => 'Returns & exchanges',
            IntentRouter::INTENT_PAYMENT => 'Payment',
            IntentRouter::INTENT_STORE_INFO => 'Store information',
            IntentRouter::INTENT_GREETING => 'Greeting',
            IntentRouter::INTENT_THANKS => 'Thanks',
            IntentRouter::INTENT_CANCEL => 'Cancel / start over',
        ],
        'language' => ['en' => 'English', 'ur' => 'Urdu', 'ar' => 'Arabic', 'fr' => 'French', 'de' => 'German', 'es' => 'Spanish', 'other' => 'Other'],
        'rating' => [1 => '1 star', 2 => '2 stars', 3 => '3 stars', 4 => '4 stars', 5 => '5 stars'],
    ];

    /**
     * @var string
     */
    private $list;

    public function __construct(string $list)
    {
        $this->list = $list;
    }

    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::LISTS[$this->list] ?? [] as $value => $label) {
            $options[] = ['value' => $value, 'label' => __($label)];
        }
        return $options;
    }

    public static function label(string $list, $value): string
    {
        return (string)(self::LISTS[$list][$value] ?? $value);
    }
}
