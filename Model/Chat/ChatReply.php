<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

/**
 * What the engine hands back to the widget / admin preview. Serialised as-is to JSON.
 *
 * Cards: ['type' => 'product'|'order'|'link'|'file'|'table', ...]. Chips: ['label' => .., 'send' => ..].
 */
class ChatReply
{
    /**
     * @var string
     */
    private $text;

    /**
     * @var array
     */
    private $cards = [];

    /**
     * @var array
     */
    private $chips = [];

    /**
     * @var array
     */
    private $sources = [];

    /**
     * @var string
     */
    private $mode = Conversation::MODE_ASSIST;

    /**
     * @var string|null
     */
    private $notice;

    /**
     * @var int|null
     */
    private $messageId;

    /**
     * @var bool
     */
    private $grounded = false;

    /**
     * @var bool
     */
    private $unanswered = false;

    /**
     * @var array|null
     */
    private $meta;

    public function __construct(string $text)
    {
        $this->text = $text;
    }

    public static function create(string $text): self
    {
        return new self($text);
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;
        return $this;
    }

    public function addCard(array $card): self
    {
        $this->cards[] = $card;
        return $this;
    }

    public function addCards(array $cards): self
    {
        foreach ($cards as $card) {
            $this->cards[] = $card;
        }
        return $this;
    }

    public function getCards(): array
    {
        return $this->cards;
    }

    public function addChip(string $label, ?string $send = null): self
    {
        $this->chips[] = ['label' => $label, 'send' => $send ?? $label];
        return $this;
    }

    public function setChips(array $chips): self
    {
        $this->chips = [];
        foreach ($chips as $chip) {
            if (is_string($chip)) {
                $this->addChip($chip);
            } elseif (isset($chip['label'])) {
                $this->addChip((string)$chip['label'], isset($chip['send']) ? (string)$chip['send'] : null);
            }
        }
        return $this;
    }

    public function getChips(): array
    {
        return $this->chips;
    }

    public function setSources(array $sources): self
    {
        $this->sources = $sources;
        return $this;
    }

    public function getSources(): array
    {
        return $this->sources;
    }

    public function setMode(string $mode): self
    {
        $this->mode = $mode;
        return $this;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function setNotice(?string $notice): self
    {
        $this->notice = $notice;
        return $this;
    }

    public function getNotice(): ?string
    {
        return $this->notice;
    }

    public function setMessageId(?int $id): self
    {
        $this->messageId = $id;
        return $this;
    }

    public function getMessageId(): ?int
    {
        return $this->messageId;
    }

    public function setGrounded(bool $grounded): self
    {
        $this->grounded = $grounded;
        return $this;
    }

    public function isGrounded(): bool
    {
        return $this->grounded;
    }

    public function setUnanswered(bool $unanswered): self
    {
        $this->unanswered = $unanswered;
        return $this;
    }

    public function isUnanswered(): bool
    {
        return $this->unanswered;
    }

    /**
     * Provider/model/token accounting for the stored message (AI mode).
     */
    public function setMeta(?array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }

    public function getMeta(): ?array
    {
        return $this->meta;
    }

    public function toArray(): array
    {
        return [
            'message_id' => $this->messageId,
            'text' => $this->text,
            'cards' => $this->cards,
            'chips' => $this->chips,
            'sources' => array_map(function ($source) {
                return ['title' => $source['title'] ?? '', 'url' => $source['url'] ?? null];
            }, $this->sources),
            'mode' => $this->mode,
            'notice' => $this->notice,
        ];
    }
}
