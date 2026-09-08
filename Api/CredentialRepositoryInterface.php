<?php
declare(strict_types=1);

namespace WB\AiChatbot\Api;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use WB\AiChatbot\Api\Data\CredentialInterface;

interface CredentialRepositoryInterface
{
    /**
     * @throws NoSuchEntityException
     */
    public function getById(int $credentialId): CredentialInterface;

    /**
     * @throws CouldNotSaveException
     */
    public function save(CredentialInterface $credential): CredentialInterface;

    /**
     * @throws CouldNotDeleteException
     */
    public function delete(CredentialInterface $credential): bool;

    /**
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $credentialId): bool;

    /**
     * Enabled credentials in fallback order (sort_order ASC, then id).
     *
     * @return CredentialInterface[]
     */
    public function getEnabledOrdered(): array;
}
