<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Preview;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use WB\AiChatbot\Model\Chat\ChatContext;
use WB\AiChatbot\Model\Chat\ConversationManager;
use WB\AiChatbot\Model\Chat\Engine;
use WB\AiChatbot\Model\Provider\Pool;

/**
 * Runs the real engine from the admin (conversations flagged is_preview, one per admin user and store view).
 */
class Send extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'WB_AiChatbot::preview';

    /**
     * @var Engine
     */
    private $engine;

    /**
     * @var ConversationManager
     */
    private $conversations;

    /**
     * @var Pool
     */
    private $pool;

    public function __construct(Action\Context $context, Engine $engine, ConversationManager $conversations, Pool $pool)
    {
        parent::__construct($context);
        $this->engine = $engine;
        $this->conversations = $conversations;
        $this->pool = $pool;
    }

    public function execute(): ResultInterface
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $storeId = (int)$this->getRequest()->getParam('store', 1);
        $adminId = (int)$this->_auth->getUser()->getId();
        $context = new ChatContext(hash('sha256', 'preview:' . $adminId . ':' . $storeId), $storeId);
        $context->setPreview(true)->setLanguage('en')->setIpHash(hash('sha256', 'admin-preview-' . $adminId));

        $action = (string)$this->getRequest()->getParam('action', 'send');
        if ($action === 'reset') {
            $conversation = $this->conversations->findOpen($context->getSessionHash());
            if ($conversation) {
                $this->conversations->close($conversation);
            }
            return $result->setData(['ok' => true]);
        }
        if ($action === 'history') {
            $conversation = $this->conversations->findOpen($context->getSessionHash());
            $messages = [];
            if ($conversation) {
                foreach ($this->conversations->getHistory($conversation, 60) as $message) {
                    $messages[] = [
                        'id' => $message->getMessageId(),
                        'role' => $message->getRole(),
                        'text' => $message->getContent(),
                        'cards' => $message->getCards(),
                        'mode' => $message->getData('mode'),
                        'model' => $message->getData('model'),
                        'tokens' => (int)$message->getData('prompt_tokens') + (int)$message->getData('completion_tokens'),
                        'latency_ms' => (int)$message->getData('latency_ms'),
                        'grounded' => (bool)$message->getData('was_grounded'),
                        'tool_calls' => $message->getToolCalls(),
                        'sources' => $message->getSources(),
                    ];
                }
            }
            return $result->setData(['ok' => true, 'messages' => $messages, 'ai_available' => $this->pool->isAiAvailable()]);
        }

        $message = (string)$this->getRequest()->getParam('message', '');
        $started = microtime(true);
        $reply = $this->engine->handle($context, $message);
        $meta = $reply->getMeta() ?? [];
        return $result->setData([
            'ok' => true,
            'reply' => $reply->toArray() + [
                'debug' => [
                    'mode' => $reply->getMode(),
                    'model' => $meta['model'] ?? null,
                    'credential_id' => $meta['credential_id'] ?? null,
                    'prompt_tokens' => $meta['prompt_tokens'] ?? 0,
                    'completion_tokens' => $meta['completion_tokens'] ?? 0,
                    'cost' => $meta['cost'] ?? 0,
                    'tool_calls' => $meta['tool_calls'] ?? [],
                    'sources' => $reply->getSources(),
                    'grounded' => $reply->isGrounded(),
                    'unanswered' => $reply->isUnanswered(),
                    'latency_ms' => (int)round((microtime(true) - $started) * 1000),
                ],
            ],
        ]);
    }
}
