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
 * End-of-chat star rating (1-5) with optional comment.
 */
class Rate extends AbstractChat implements HttpPostActionInterface
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
        $rating = (int)$this->request->getParam('rating');
        if (!$conversation || $rating < 1 || $rating > 5) {
            return $this->json(['ok' => false], 400);
        }
        $conversation->setData('rating', $rating);
        $conversation->setData('rating_comment', mb_substr(trim((string)$this->request->getParam('comment', '')), 0, 1000) ?: null);
        $this->conversations->save($conversation);
        return $this->json(['ok' => true]);
    }
}
