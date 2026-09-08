<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Provider;

use WB\AiChatbot\Api\Data\CredentialInterface;

/**
 * A provider call failed. Carries the normalised health status so the pool knows whether to fail over
 * (INVALID_KEY, INSUFFICIENT_QUOTA, RATE_LIMITED, MODEL_UNAVAILABLE, TRANSIENT_ERROR) and how long to back off.
 */
class ProviderException extends \RuntimeException
{
    /**
     * @var string
     */
    private $status;

    /**
     * @var int|null
     */
    private $httpStatus;

    public function __construct(string $status, string $message, ?int $httpStatus = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, $httpStatus ?? 0, $previous);
        $this->status = $status;
        $this->httpStatus = $httpStatus;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /**
     * A credential problem stays broken until the admin changes the key; a transient error is worth retrying soon.
     */
    public function isPermanent(): bool
    {
        return in_array($this->status, [
            CredentialInterface::STATUS_INVALID_KEY,
            CredentialInterface::STATUS_EMPTY_KEY,
            CredentialInterface::STATUS_MODEL_UNAVAILABLE,
        ], true);
    }

    /**
     * Maps an HTTP status + response body to a normalised status. Shared by every HTTP-based provider.
     */
    public static function fromHttp(int $httpStatus, string $body): self
    {
        $decoded = json_decode($body, true);
        $apiMessage = '';
        if (is_array($decoded)) {
            $apiMessage = (string)($decoded['error']['message'] ?? $decoded['message'] ?? $decoded['error'] ?? '');
        }
        $apiMessage = $apiMessage !== '' ? $apiMessage : substr(strip_tags($body), 0, 300);
        $lower = strtolower($apiMessage);

        $mentionsKey = strpos($lower, 'api key') !== false || strpos($lower, 'api_key') !== false
            || strpos($lower, 'invalid key') !== false || strpos($lower, 'unauthorized') !== false;

        if ($httpStatus === 401 || $httpStatus === 403) {
            $status = CredentialInterface::STATUS_INVALID_KEY;
        } elseif ($httpStatus === 400 && $mentionsKey) {
            // Gemini answers a bad key with HTTP 400 "API key not valid" rather than 401.
            $status = CredentialInterface::STATUS_INVALID_KEY;
        } elseif ($httpStatus === 429) {
            $status = (strpos($lower, 'quota') !== false || strpos($lower, 'billing') !== false || strpos($lower, 'credit') !== false)
                ? CredentialInterface::STATUS_INSUFFICIENT_QUOTA
                : CredentialInterface::STATUS_RATE_LIMITED;
        } elseif ($httpStatus === 402) {
            $status = CredentialInterface::STATUS_INSUFFICIENT_QUOTA;
        } elseif ($httpStatus === 404 || ($httpStatus === 400 && strpos($lower, 'model') !== false)) {
            $status = CredentialInterface::STATUS_MODEL_UNAVAILABLE;
        } else {
            $status = CredentialInterface::STATUS_TRANSIENT_ERROR;
        }

        return new self($status, sprintf('HTTP %d: %s', $httpStatus, $apiMessage), $httpStatus);
    }
}
