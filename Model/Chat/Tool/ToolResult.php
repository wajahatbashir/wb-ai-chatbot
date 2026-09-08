<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat\Tool;

/**
 * data  - structured result handed back to the model (JSON) / used by the flows
 * cards - UI cards to show the customer alongside the reply
 * text  - ready-made customer-facing text (Assist mode uses it verbatim; AI mode may ignore it)
 */
class ToolResult
{
    /**
     * @var array
     */
    private $data;

    /**
     * @var array
     */
    private $cards;

    /**
     * @var string|null
     */
    private $text;

    /**
     * @var array
     */
    private $chips;

    public function __construct(array $data, array $cards = [], ?string $text = null, array $chips = [])
    {
        $this->data = $data;
        $this->cards = $cards;
        $this->text = $text;
        $this->chips = $chips;
    }

    public static function error(string $code, string $message): self
    {
        return new self(['error' => $code, 'message' => $message], [], $message);
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getCards(): array
    {
        return $this->cards;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function getChips(): array
    {
        return $this->chips;
    }

    public function isError(): bool
    {
        return isset($this->data['error']);
    }

    public function getErrorCode(): ?string
    {
        return isset($this->data['error']) ? (string)$this->data['error'] : null;
    }
}
