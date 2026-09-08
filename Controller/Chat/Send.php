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
use WB\AiChatbot\Model\Chat\Engine;
use WB\AiChatbot\Model\Config;

class Send extends AbstractChat implements HttpPostActionInterface
{
    private const MAX_ATTACHMENTS = 3;
    private const MAX_ATTACHMENT_BYTES = 10485760; // 10 MB decoded

    /**
     * @var Engine
     */
    private $engine;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        FormKeyValidator $formKeyValidator,
        ContextFactory $contextFactory,
        Config $config,
        StoreManagerInterface $storeManager,
        Engine $engine
    ) {
        parent::__construct($request, $jsonFactory, $formKeyValidator, $contextFactory, $config, $storeManager);
        $this->engine = $engine;
    }

    public function execute(): ResultInterface
    {
        if (!$this->isEnabledForStore()) {
            return $this->json(['error' => 'disabled', 'message' => 'The assistant is not available.'], 404);
        }
        $context = $this->contextFactory->createFromRequest($this->request);
        $storeId = $context->getStoreId();
        $groups = $this->config->getCustomerGroups($storeId);
        if ($groups !== [] && !in_array($context->getCustomerGroupId() ?? 0, $groups, true)) {
            return $this->json(['error' => 'not_allowed', 'message' => 'The assistant is not available for your account.'], 403);
        }
        $message = (string)$this->request->getParam('message', '');
        $attachments = $this->config->isAttachmentsAllowed($storeId) ? $this->attachments() : [];
        $reply = $this->engine->handle($context, $message, $attachments);
        return $this->json(['ok' => true, 'reply' => $reply->toArray()]);
    }

    /**
     * @return array<int, array{name: string, mime: string, data: string}>
     */
    private function attachments(): array
    {
        $raw = $this->request->getParam('attachments');
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return [];
        }
        $attachments = [];
        foreach (array_slice($raw, 0, self::MAX_ATTACHMENTS) as $item) {
            $mime = (string)($item['mime'] ?? '');
            $data = (string)($item['data'] ?? '');
            if (strpos($data, 'base64,') !== false) {
                $data = substr($data, strpos($data, 'base64,') + 7);
            }
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) || $data === '') {
                continue;
            }
            if (strlen($data) * 3 / 4 > self::MAX_ATTACHMENT_BYTES || base64_decode($data, true) === false) {
                continue;
            }
            $attachments[] = ['name' => mb_substr((string)($item['name'] ?? 'image'), 0, 100), 'mime' => $mime, 'data' => $data];
        }
        return $attachments;
    }
}
