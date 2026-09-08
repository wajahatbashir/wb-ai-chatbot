<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use WB\AiChatbot\Api\Data\CredentialInterface;

class Credential extends AbstractModel implements CredentialInterface
{
    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    public function __construct(
        Context $context,
        Registry $registry,
        EncryptorInterface $encryptor,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->encryptor = $encryptor;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    protected function _construct(): void
    {
        $this->_init(ResourceModel\Credential::class);
    }

    public function getCredentialId(): ?int
    {
        $id = $this->getData(self::CREDENTIAL_ID);
        return $id === null ? null : (int)$id;
    }

    public function getAlias(): ?string
    {
        return $this->getData(self::ALIAS);
    }

    public function getProviderCode(): ?string
    {
        return $this->getData(self::PROVIDER_CODE);
    }

    public function getApiKey(): ?string
    {
        return $this->getData(self::API_KEY);
    }

    public function getDecryptedApiKey(): string
    {
        $stored = (string)$this->getData(self::API_KEY);
        return $stored === '' ? '' : (string)$this->encryptor->decrypt($stored);
    }

    public function getModel(): ?string
    {
        return $this->getData(self::MODEL);
    }

    public function getEmbeddingModel(): ?string
    {
        return $this->getData(self::EMBEDDING_MODEL);
    }

    public function getSupportsVision(): bool
    {
        return (bool)$this->getData(self::SUPPORTS_VISION);
    }

    public function getSortOrder(): int
    {
        return (int)$this->getData(self::SORT_ORDER);
    }

    public function getIsEnabled(): bool
    {
        return (bool)$this->getData(self::IS_ENABLED);
    }

    public function getStatus(): string
    {
        return (string)($this->getData(self::STATUS) ?: self::STATUS_UNCHECKED);
    }

    public function getStatusMessage(): ?string
    {
        return $this->getData(self::STATUS_MESSAGE);
    }

    public function getLastCheckedAt(): ?string
    {
        return $this->getData(self::LAST_CHECKED_AT);
    }

    public function getLastErrorAt(): ?string
    {
        return $this->getData(self::LAST_ERROR_AT);
    }

    public function getRetryAfter(): ?string
    {
        return $this->getData(self::RETRY_AFTER);
    }

    public function setAlias(string $alias): CredentialInterface
    {
        return $this->setData(self::ALIAS, $alias);
    }

    public function setProviderCode(string $code): CredentialInterface
    {
        return $this->setData(self::PROVIDER_CODE, $code);
    }

    public function setApiKey(?string $encryptedKey): CredentialInterface
    {
        return $this->setData(self::API_KEY, $encryptedKey);
    }

    public function setModel(?string $model): CredentialInterface
    {
        return $this->setData(self::MODEL, $model);
    }

    public function setEmbeddingModel(?string $model): CredentialInterface
    {
        return $this->setData(self::EMBEDDING_MODEL, $model);
    }

    public function setSupportsVision(bool $flag): CredentialInterface
    {
        return $this->setData(self::SUPPORTS_VISION, $flag ? 1 : 0);
    }

    public function setSortOrder(int $order): CredentialInterface
    {
        return $this->setData(self::SORT_ORDER, $order);
    }

    public function setIsEnabled(bool $flag): CredentialInterface
    {
        return $this->setData(self::IS_ENABLED, $flag ? 1 : 0);
    }

    public function setStatus(string $status): CredentialInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    public function setStatusMessage(?string $message): CredentialInterface
    {
        return $this->setData(self::STATUS_MESSAGE, $message);
    }

    public function setLastCheckedAt(?string $datetime): CredentialInterface
    {
        return $this->setData(self::LAST_CHECKED_AT, $datetime);
    }

    public function setLastErrorAt(?string $datetime): CredentialInterface
    {
        return $this->setData(self::LAST_ERROR_AT, $datetime);
    }

    public function setRetryAfter(?string $datetime): CredentialInterface
    {
        return $this->setData(self::RETRY_AFTER, $datetime);
    }
}
