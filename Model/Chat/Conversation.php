<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

use Magento\Framework\Model\AbstractModel;

class Conversation extends AbstractModel
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_ESCALATED = 'escalated';

    public const MODE_AI = 'ai';
    public const MODE_ASSIST = 'assist';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\Conversation::class);
    }

    public function getConversationId(): ?int
    {
        $id = $this->getData('conversation_id');
        return $id === null ? null : (int)$id;
    }

    public function getStoreId(): int
    {
        return (int)$this->getData('store_id');
    }

    public function getCustomerId(): ?int
    {
        $id = $this->getData('customer_id');
        return $id ? (int)$id : null;
    }

    public function getCustomerEmail(): ?string
    {
        $email = $this->getData('customer_email');
        return $email ? (string)$email : null;
    }

    public function getVerifiedEmails(): array
    {
        return $this->jsonField('verified_emails');
    }

    public function isEmailVerified(string $email): bool
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return false;
        }
        if ($this->getCustomerEmail() && mb_strtolower($this->getCustomerEmail()) === $email) {
            return true; // logged-in customer's own address
        }
        return in_array($email, array_map('mb_strtolower', $this->getVerifiedEmails()), true);
    }

    public function addVerifiedEmail(string $email): void
    {
        $emails = $this->getVerifiedEmails();
        $emails[] = mb_strtolower(trim($email));
        $this->setData('verified_emails', json_encode(array_values(array_unique($emails))));
    }

    public function getFlowState(): array
    {
        return $this->jsonField('flow_state');
    }

    public function setFlowState(?array $state): void
    {
        $this->setData('flow_state', $state === null || $state === [] ? null : json_encode($state, JSON_UNESCAPED_UNICODE));
    }

    public function isPreview(): bool
    {
        return (bool)$this->getData('is_preview');
    }

    private function jsonField(string $key): array
    {
        $raw = $this->getData($key);
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
