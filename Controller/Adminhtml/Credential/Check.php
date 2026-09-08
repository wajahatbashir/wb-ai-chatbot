<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Credential;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use WB\AiChatbot\Api\CredentialRepositoryInterface;
use WB\AiChatbot\Controller\Adminhtml\Credential;
use WB\AiChatbot\Model\Provider\HealthChecker;

/**
 * "Check connection" row action: re-validates one credential (or every credential when no id is given).
 */
class Check extends Credential implements HttpGetActionInterface, HttpPostActionInterface
{
    /**
     * @var CredentialRepositoryInterface
     */
    private $credentialRepository;

    /**
     * @var HealthChecker
     */
    private $healthChecker;

    public function __construct(
        Action\Context $context,
        Registry $registry,
        CredentialRepositoryInterface $credentialRepository,
        HealthChecker $healthChecker
    ) {
        parent::__construct($context, $registry);
        $this->credentialRepository = $credentialRepository;
        $this->healthChecker = $healthChecker;
    }

    public function execute(): ResultInterface
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $id = (int)$this->getRequest()->getParam('credential_id');

        try {
            $credentials = $id ? [$this->credentialRepository->getById($id)] : $this->credentialRepository->getEnabledOrdered();
            if ($credentials === []) {
                $this->messageManager->addNoticeMessage(__('There are no enabled provider credentials to check.'));
            }
            foreach ($credentials as $credential) {
                $result = $this->healthChecker->check($credential);
                $message = __('%1: %2 - %3', $credential->getAlias(), $result->getStatus(), $result->getMessage());
                if ($result->isHealthy()) {
                    $this->messageManager->addSuccessMessage($message);
                } else {
                    $this->messageManager->addWarningMessage($message);
                }
            }
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, $e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }
}
