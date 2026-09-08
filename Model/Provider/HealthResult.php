<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Provider;

use WB\AiChatbot\Api\Data\CredentialInterface;

class HealthResult
{
    /**
     * @var string one of CredentialInterface::STATUS_*
     */
    private $status;

    /**
     * @var string
     */
    private $message;

    public function __construct(string $status, string $message = '')
    {
        $this->status = $status;
        $this->message = $message;
    }

    public static function ok(string $message = 'OK'): self
    {
        return new self(CredentialInterface::STATUS_OK, $message);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function isHealthy(): bool
    {
        return $this->status === CredentialInterface::STATUS_OK;
    }
}
