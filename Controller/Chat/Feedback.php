<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Chat;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Store\Model\StoreManagerInterface;
use WB\AiChatbot\Model\Chat\ContextFactory;
use WB\AiChatbot\Model\Chat\ConversationManager;
use WB\AiChatbot\Model\Config;

/**
 * Thumbs up/down on an assistant message (value 1, -1 or 0 to clear).
 */
class Feedback extends AbstractChat implements HttpPostActionInterface
{
    /**
     * @var ConversationManager
     */
    private $conversations;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        FormKeyValidator $formKeyValidator,
        ContextFactory $contextFactory,
        Config $config,
        StoreManagerInterface $storeManager,
        ConversationManager $conversations
    ) {
        parent::__construct($request, $jsonFactory, $formKeyValidator, $contextFactory, $config, $storeManager);
        $this->conversations = $conversations;
    }

    public function execute(): ResultInterface
    {
        $context = $this->contextFactory->createFromRequest($this->request);
        $conversation = $this->conversations->findOpen($context->getSessionHash());
        $messageId = (int)$this->request->getParam('message_id');
        $value = (int)$this->request->getParam('value');
        if (!$conversation || !$messageId || !in_array($value, [-1, 0, 1], true)) {
            return $this->json(['ok' => false], 400);
        }
        $ok = $this->conversations->setFeedback($messageId, $conversation, $value === 0 ? null : $value);
        return $this->json(['ok' => $ok]);
    }
}
