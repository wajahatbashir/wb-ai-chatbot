<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use WB\AiChatbot\Api\CredentialRepositoryInterface;
use WB\AiChatbot\Api\Data\CredentialInterface;
use WB\AiChatbot\Model\ResourceModel\Credential\CollectionFactory;

class CredentialRepository implements CredentialRepositoryInterface
{
    /**
     * @var CredentialFactory
     */
    private $credentialFactory;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    public function __construct(CredentialFactory $credentialFactory, CollectionFactory $collectionFactory)
    {
        $this->credentialFactory = $credentialFactory;
        $this->collectionFactory = $collectionFactory;
    }

    public function getById(int $credentialId): CredentialInterface
    {
        $credential = $this->credentialFactory->create();
        $credential->load($credentialId);
        if (!$credential->getId()) {
            throw new NoSuchEntityException(__('Provider credential with id "%1" does not exist.', $credentialId));
        }
        return $credential;
    }

    public function save(CredentialInterface $credential): CredentialInterface
    {
        try {
            $credential->save();
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not save the provider credential: %1', $e->getMessage()), $e);
        }
        return $credential;
    }

    public function delete(CredentialInterface $credential): bool
    {
        try {
            $credential->delete();
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete the provider credential: %1', $e->getMessage()), $e);
        }
        return true;
    }

    public function deleteById(int $credentialId): bool
    {
        return $this->delete($this->getById($credentialId));
    }

    public function getEnabledOrdered(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(CredentialInterface::IS_ENABLED, 1)
            ->setOrder(CredentialInterface::SORT_ORDER, 'ASC')
            ->setOrder(CredentialInterface::CREDENTIAL_ID, 'ASC');
        return array_values($collection->getItems());
    }
}
