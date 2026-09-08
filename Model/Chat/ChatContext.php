<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

use Magento\Quote\Api\Data\CartInterface;

/**
 * Who is talking and from where. Built by the frontend controller (session, customer, quote) or by the admin
 * preview; the engine and tools only ever see this object.
 */
class ChatContext
{
    /**
     * @var string
     */
    private $sessionHash;

    /**
     * @var int
     */
    private $storeId;

    /**
     * @var int|null
     */
    private $customerId;

    /**
     * @var string|null
     */
    private $customerEmail;

    /**
     * @var string|null
     */
    private $customerName;

    /**
     * @var int|null
     */
    private $customerGroupId;

    /**
     * @var CartInterface|null
     */
    private $quote;

    /**
     * @var string|null
     */
    private $ipHash;

    /**
     * @var string|null
     */
    private $pageUrl;

    /**
     * @var string
     */
    private $language = 'en';

    /**
     * @var string|null
     */
    private $country;

    /**
     * @var bool
     */
    private $preview = false;

    /**
     * @var Conversation|null
     */
    private $conversation;

    public function __construct(string $sessionHash, int $storeId)
    {
        $this->sessionHash = $sessionHash;
        $this->storeId = $storeId;
    }

    public function getSessionHash(): string
    {
        return $this->sessionHash;
    }

    public function getStoreId(): int
    {
        return $this->storeId;
    }

    public function setCustomer(?int $id, ?string $email, ?string $name, ?int $groupId = null): self
    {
        $this->customerId = $id;
        $this->customerEmail = $email;
        $this->customerName = $name;
        $this->customerGroupId = $groupId;
        return $this;
    }

    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function getCustomerName(): ?string
    {
        return $this->customerName;
    }

    public function getCustomerGroupId(): ?int
    {
        return $this->customerGroupId;
    }

    public function isLoggedIn(): bool
    {
        return $this->customerId !== null;
    }

    public function setQuote(?CartInterface $quote): self
    {
        $this->quote = $quote;
        return $this;
    }

    public function getQuote(): ?CartInterface
    {
        return $this->quote;
    }

    public function setIpHash(?string $ipHash): self
    {
        $this->ipHash = $ipHash;
        return $this;
    }

    public function getIpHash(): ?string
    {
        return $this->ipHash;
    }

    public function setPageUrl(?string $url): self
    {
        $this->pageUrl = $url;
        return $this;
    }

    public function getPageUrl(): ?string
    {
        return $this->pageUrl;
    }

    public function setLanguage(string $language): self
    {
        $this->language = $language;
        return $this;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;
        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setPreview(bool $preview): self
    {
        $this->preview = $preview;
        return $this;
    }

    public function isPreview(): bool
    {
        return $this->preview;
    }

    public function setConversation(Conversation $conversation): self
    {
        $this->conversation = $conversation;
        return $this;
    }

    public function getConversation(): Conversation
    {
        if ($this->conversation === null) {
            throw new \LogicException('Conversation not attached to the chat context yet');
        }
        return $this->conversation;
    }
}
