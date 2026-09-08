<?php
declare(strict_types=1);

namespace WB\AiChatbot\Api\Data;

/**
 * One AI provider credential: an encrypted API key plus the model choices and its place in the fallback chain.
 */
interface CredentialInterface
{
    public const CREDENTIAL_ID = 'credential_id';
    public const ALIAS = 'alias';
    public const PROVIDER_CODE = 'provider_code';
    public const API_KEY = 'api_key';
    public const MODEL = 'model';
    public const EMBEDDING_MODEL = 'embedding_model';
    public const SUPPORTS_VISION = 'supports_vision';
    public const SORT_ORDER = 'sort_order';
    public const IS_ENABLED = 'is_enabled';
    public const STATUS = 'status';
    public const STATUS_MESSAGE = 'status_message';
    public const LAST_CHECKED_AT = 'last_checked_at';
    public const LAST_ERROR_AT = 'last_error_at';
    public const RETRY_AFTER = 'retry_after';

    public const STATUS_OK = 'OK';
    public const STATUS_UNCHECKED = 'UNCHECKED';
    public const STATUS_EMPTY_KEY = 'EMPTY_KEY';
    public const STATUS_INVALID_KEY = 'INVALID_KEY';
    public const STATUS_INSUFFICIENT_QUOTA = 'INSUFFICIENT_QUOTA';
    public const STATUS_RATE_LIMITED = 'RATE_LIMITED';
    public const STATUS_MODEL_UNAVAILABLE = 'MODEL_UNAVAILABLE';
    public const STATUS_TRANSIENT_ERROR = 'TRANSIENT_ERROR';

    public function getCredentialId(): ?int;

    public function getAlias(): ?string;

    public function getProviderCode(): ?string;

    /**
     * Encrypted value as stored.
     */
    public function getApiKey(): ?string;

    /**
     * Decrypted key, ready to send to the provider.
     */
    public function getDecryptedApiKey(): string;

    public function getModel(): ?string;

    public function getEmbeddingModel(): ?string;

    public function getSupportsVision(): bool;

    public function getSortOrder(): int;

    public function getIsEnabled(): bool;

    public function getStatus(): string;

    public function getStatusMessage(): ?string;

    public function getLastCheckedAt(): ?string;

    public function getLastErrorAt(): ?string;

    public function getRetryAfter(): ?string;

    /**
     * @return $this
     */
    public function setAlias(string $alias): self;

    /**
     * @return $this
     */
    public function setProviderCode(string $code): self;

    /**
     * Stores an already-encrypted value.
     *
     * @return $this
     */
    public function setApiKey(?string $encryptedKey): self;

    /**
     * @return $this
     */
    public function setModel(?string $model): self;

    /**
     * @return $this
     */
    public function setEmbeddingModel(?string $model): self;

    /**
     * @return $this
     */
    public function setSupportsVision(bool $flag): self;

    /**
     * @return $this
     */
    public function setSortOrder(int $order): self;

    /**
     * @return $this
     */
    public function setIsEnabled(bool $flag): self;

    /**
     * @return $this
     */
    public function setStatus(string $status): self;

    /**
     * @return $this
     */
    public function setStatusMessage(?string $message): self;

    /**
     * @return $this
     */
    public function setLastCheckedAt(?string $datetime): self;

    /**
     * @return $this
     */
    public function setLastErrorAt(?string $datetime): self;

    /**
     * @return $this
     */
    public function setRetryAfter(?string $datetime): self;
}
