<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Chat;

use Magento\Framework\Model\AbstractModel;

class Message extends AbstractModel
{
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_SYSTEM = 'system';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\Message::class);
    }

    public function getMessageId(): ?int
    {
        $id = $this->getData('message_id');
        return $id === null ? null : (int)$id;
    }

    public function getRole(): string
    {
        return (string)$this->getData('role');
    }

    public function getContent(): string
    {
        return (string)$this->getData('content');
    }

    public function getCards(): array
    {
        return $this->jsonField('cards');
    }

    public function getSources(): array
    {
        return $this->jsonField('sources');
    }

    public function getToolCalls(): array
    {
        return $this->jsonField('tool_calls');
    }

    public function getAttachments(): array
    {
        return $this->jsonField('attachments');
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
