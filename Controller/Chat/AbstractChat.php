<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Chat;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Store\Model\StoreManagerInterface;
use WB\AiChatbot\Model\Chat\ContextFactory;
use WB\AiChatbot\Model\Config;

/**
 * JSON endpoints under /aichatbot/chat/*. POST actions require the storefront form key (sent by the widget).
 */
abstract class AbstractChat implements ActionInterface, CsrfAwareActionInterface
{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var JsonFactory
     */
    protected $jsonFactory;

    /**
     * @var FormKeyValidator
     */
    protected $formKeyValidator;

    /**
     * @var ContextFactory
     */
    protected $contextFactory;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    public function __construct(
        RequestInterface $request,
        JsonFactory $jsonFactory,
        FormKeyValidator $formKeyValidator,
        ContextFactory $contextFactory,
        Config $config,
        StoreManagerInterface $storeManager
    ) {
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;
        $this->formKeyValidator = $formKeyValidator;
        $this->contextFactory = $contextFactory;
        $this->config = $config;
        $this->storeManager = $storeManager;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        $result = $this->jsonFactory->create()->setHttpResponseCode(403)->setData(['error' => 'invalid_form_key', 'message' => 'Your session expired. Please reload the page.']);
        return new InvalidRequestException($result);
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        if (!$this instanceof HttpPostActionInterface) {
            return true;
        }
        return $this->formKeyValidator->validate($request);
    }

    protected function json(array $data, int $code = 200): \Magento\Framework\Controller\Result\Json
    {
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', 'no-store', true);
        return $result->setHttpResponseCode($code)->setData($data);
    }

    protected function isEnabledForStore(): bool
    {
        return $this->config->isEnabled((int)$this->storeManager->getStore()->getId());
    }
}
