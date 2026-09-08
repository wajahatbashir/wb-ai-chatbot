<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Credential;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Registry;
use WB\AiChatbot\Api\CredentialRepositoryInterface;
use WB\AiChatbot\Controller\Adminhtml\Credential;
use WB\AiChatbot\Model\CredentialFactory;

class Edit extends Credential implements HttpGetActionInterface
{
    /**
     * @var CredentialRepositoryInterface
     */
    private $credentialRepository;

    /**
     * @var CredentialFactory
     */
    private $credentialFactory;

    public function __construct(
        Action\Context $context,
        Registry $registry,
        CredentialRepositoryInterface $credentialRepository,
        CredentialFactory $credentialFactory
    ) {
        parent::__construct($context, $registry);
        $this->credentialRepository = $credentialRepository;
        $this->credentialFactory = $credentialFactory;
    }

    public function execute(): ResultInterface
    {
        $id = (int)$this->getRequest()->getParam('credential_id');

        if ($id) {
            try {
                $model = $this->credentialRepository->getById($id);
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage(__('This provider credential no longer exists.'));
                $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
                return $resultRedirect->setPath('*/*/');
            }
        } else {
            $model = $this->credentialFactory->create();
        }

        $this->coreRegistry->register('current_model', $model);

        $resultPage = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $resultPage->setActiveMenu('WB_AiChatbot::credentials');
        $resultPage->getConfig()->getTitle()->prepend(__('AI Providers'));
        $resultPage->getConfig()->getTitle()->prepend($id ? $model->getAlias() : __('New Provider Credential'));

        return $resultPage;
    }
}
