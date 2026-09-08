<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb\Sync;

/**
 * What a sync provider yields: one document worth of plain text plus structured attributes.
 */
class SyncDocument
{
    /**
     * @var string
     */
    private $identifier;

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $body;

    /**
     * @var string|null
     */
    private $url;

    /**
     * @var array
     */
    private $attributes;

    public function __construct(string $identifier, string $title, string $body, ?string $url = null, array $attributes = [])
    {
        $this->identifier = $identifier;
        $this->title = $title;
        $this->body = $body;
        $this->url = $url;
        $this->attributes = $attributes;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getContentHash(): string
    {
        return hash('sha256', $this->title . "\n" . $this->body . "\n" . json_encode($this->attributes) . "\n" . (string)$this->url);
    }
}
