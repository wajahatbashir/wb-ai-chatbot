<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Credential;

use Magento\Backend\App\Action;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use WB\AiChatbot\Api\CredentialRepositoryInterface;
use WB\AiChatbot\Controller\Adminhtml\Credential;

class Delete extends Credential implements HttpPostActionInterface
{
    /**
     * @var CredentialRepositoryInterface
     */
    private $credentialRepository;

    public function __construct(Action\Context $context, Registry $registry, CredentialRepositoryInterface $credentialRepository)
    {
        parent::__construct($context, $registry);
        $this->credentialRepository = $credentialRepository;
    }

    public function execute(): ResultInterface
    {
        /** @var Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $id = (int)$this->getRequest()->getParam('credential_id');

        if ($id) {
            try {
                $this->credentialRepository->deleteById($id);
                $this->messageManager->addSuccessMessage(__('The provider credential was deleted.'));
            } catch (\Exception $e) {
                $this->messageManager->addExceptionMessage($e, $e->getMessage());
            }
        } else {
            $this->messageManager->addErrorMessage(__('We can\'t find a provider credential to delete.'));
        }

        return $resultRedirect->setPath('*/*/');
    }
}
