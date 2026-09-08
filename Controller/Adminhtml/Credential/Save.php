<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Credential;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Registry;
use WB\AiChatbot\Api\CredentialRepositoryInterface;
use WB\AiChatbot\Api\Data\CredentialInterface;
use WB\AiChatbot\Controller\Adminhtml\Credential;
use WB\AiChatbot\Model\CredentialFactory;
use WB\AiChatbot\Model\Provider\HealthChecker;

class Save extends Credential implements HttpPostActionInterface
{
    /**
     * @var CredentialRepositoryInterface
     */
    private $credentialRepository;

    /**
     * @var CredentialFactory
     */
    private $credentialFactory;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var HealthChecker
     */
    private $healthChecker;

    public function __construct(
        Action\Context $context,
        Registry $registry,
        CredentialRepositoryInterface $credentialRepository,
        CredentialFactory $credentialFactory,
        EncryptorInterface $encryptor,
        HealthChecker $healthChecker
    ) {
        parent::__construct($context, $registry);
        $this->credentialRepository = $credentialRepository;
        $this->credentialFactory = $credentialFactory;
        $this->encryptor = $encryptor;
        $this->healthChecker = $healthChecker;
    }

    public function execute(): ResultInterface
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $data = $this->getRequest()->getPostValue();
        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $id = !empty($data['credential_id']) ? (int)$data['credential_id'] : null;

        try {
            $model = $id ? $this->credentialRepository->getById($id) : $this->credentialFactory->create();

            $model->setAlias(trim((string)($data['alias'] ?? '')));
            $model->setProviderCode((string)($data['provider_code'] ?? ''));
            $model->setModel(trim((string)($data['model'] ?? '')) ?: null);
            $model->setEmbeddingModel(trim((string)($data['embedding_model'] ?? '')) ?: null);
            $model->setSupportsVision(!empty($data['supports_vision']));
            $model->setSortOrder((int)($data['sort_order'] ?? 0));
            $model->setIsEnabled(!empty($data['is_enabled']));

            // An empty password field means "keep the stored key"; anything typed replaces it.
            $newKey = trim((string)($data['api_key'] ?? ''));
            if ($newKey !== '') {
                $model->setApiKey($this->encryptor->encrypt($newKey));
            }

            // Any change is worth re-checking - the status shown in the grid must describe the saved values.
            $model->setStatus(CredentialInterface::STATUS_UNCHECKED);
            $model->setStatusMessage(null);
            $model->setRetryAfter(null);
            $this->credentialRepository->save($model);

            $health = $this->healthChecker->check($model);
            if ($health->isHealthy()) {
                $this->messageManager->addSuccessMessage(__('Saved "%1" - connection check passed: %2', $model->getAlias(), $health->getMessage()));
            } else {
                $this->messageManager->addWarningMessage(__(
                    'Saved "%1", but the connection check failed (%2): %3. The chatbot will use Assist mode until a working key is configured.',
                    $model->getAlias(),
                    $health->getStatus(),
                    $health->getMessage()
                ));
            }

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath('*/*/edit', ['credential_id' => $model->getCredentialId()]);
            }
            return $resultRedirect->setPath('*/*/');
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
            return $resultRedirect->setPath('*/*/edit', ['credential_id' => $id]);
        }
    }
}
