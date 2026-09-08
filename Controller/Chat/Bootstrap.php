<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Chat;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Store\Model\StoreManagerInterface;
use WB\AiChatbot\Model\Chat\ContextFactory;
use WB\AiChatbot\Model\Chat\ConversationManager;
use WB\AiChatbot\Model\Chat\Message;
use WB\AiChatbot\Model\Config;
use WB\AiChatbot\Model\Provider\Pool;

/**
 * Widget boot payload: settings + the visitor's open conversation (never cached: it is per visitor).
 */
class Bootstrap extends AbstractChat implements HttpGetActionInterface
{
    /**
     * @var ConversationManager
     */
    private $conversations;

    /**
     * @var Pool
     */
    private $pool;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        FormKeyValidator $formKeyValidator,
        ContextFactory $contextFactory,
        Config $config,
        StoreManagerInterface $storeManager,
        ConversationManager $conversations,
        Pool $pool
    ) {
        parent::__construct($request, $jsonFactory, $formKeyValidator, $contextFactory, $config, $storeManager);
        $this->conversations = $conversations;
        $this->pool = $pool;
    }

    public function execute(): ResultInterface
    {
        if (!$this->isEnabledForStore()) {
            return $this->json(['enabled' => false], 200);
        }
        $context = $this->contextFactory->createFromRequest($this->request);
        $storeId = $context->getStoreId();
        $groups = $this->config->getCustomerGroups($storeId);
        if ($groups !== [] && !in_array($context->getCustomerGroupId() ?? 0, $groups, true)) {
            return $this->json(['enabled' => false]);
        }

        $history = [];
        $conversation = $this->conversations->findOpen($context->getSessionHash());
        if ($conversation) {
            foreach ($this->conversations->getHistory($conversation, $this->config->getMaxMessages($storeId)) as $message) {
                $history[] = $this->serialize($message);
            }
        }
        $aiAvailable = $this->pool->isAiAvailable();
        return $this->json([
            'enabled' => true,
            'mode' => $aiAvailable ? 'ai' : 'assist',
            'customer' => [
                'logged_in' => $context->isLoggedIn(),
                'name' => $context->getCustomerName(),
            ],
            'conversation' => [
                'id' => $conversation ? (int)$conversation->getId() : null,
                'rating' => $conversation ? $conversation->getData('rating') : null,
                'messages' => $history,
            ],
            'settings' => [
                'assistant_name' => $this->config->getAssistantName($storeId),
                'welcome_message' => $this->config->getWelcomeMessage($storeId),
                'default_questions' => $this->config->getDefaultQuestions($storeId),
                'notice' => !$aiAvailable && $this->config->isAssistNoticeShown($storeId) && $this->config->getMode($storeId) !== Config::MODE_ASSIST
                    ? null : null,
                'allow_attachments' => $this->config->isAttachmentsAllowed($storeId) && $this->supportsVision(),
                'allow_human_handoff' => $this->config->isHumanHandoffAllowed($storeId),
                'max_message_length' => $this->config->getMaxMessageLength(),
            ],
        ]);
    }

    private function supportsVision(): bool
    {
        foreach ($this->pool->getChatCandidates() as $credential) {
            if ($credential->getSupportsVision() && $this->pool->getProvider((string)$credential->getProviderCode())->supportsVision()) {
                return true;
            }
        }
        return false;
    }

    private function serialize(Message $message): array
    {
        return [
            'id' => $message->getMessageId(),
            'role' => $message->getRole(),
            'text' => $message->getContent(),
            'cards' => $message->getCards(),
            'sources' => array_map(function ($s) {
                return ['title' => $s['title'] ?? '', 'url' => $s['url'] ?? null];
            }, $message->getSources()),
            'feedback' => $message->getData('feedback') !== null ? (int)$message->getData('feedback') : null,
            'created_at' => $message->getData('created_at'),
        ];
    }
}
