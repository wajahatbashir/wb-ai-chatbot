<?php
declare(strict_types=1);

namespace WB\AiChatbot\Block\Adminhtml\Conversation;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Registry;
use Magento\Store\Model\StoreManagerInterface;
use WB\AiChatbot\Model\Source\Options;

class View extends Template
{
    /**
     * @var string
     */
    protected $_template = 'WB_AiChatbot::conversation/view.phtml';

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(Context $context, Registry $registry, ResourceConnection $resource, StoreManagerInterface $storeManager, array $data = [])
    {
        $this->registry = $registry;
        $this->resource = $resource;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    public function getConversation(): array
    {
        $model = $this->registry->registry('current_model');
        return $model ? $model->getData() : [];
    }

    public function getMessages(): array
    {
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()->from($this->resource->getTableName('wb_aichatbot_message'))
                ->where('conversation_id = ?', (int)($this->getConversation()['conversation_id'] ?? 0))
                ->order('message_id ASC')
        );
        foreach ($rows as &$row) {
            foreach (['cards', 'sources', 'tool_calls', 'attachments'] as $json) {
                $row[$json] = $row[$json] ? (json_decode((string)$row[$json], true) ?: []) : [];
            }
        }
        return $rows;
    }

    public function getRequests(): array
    {
        $connection = $this->resource->getConnection();
        return $connection->fetchAll(
            $connection->select()->from($this->resource->getTableName('wb_aichatbot_request'), ['request_id', 'code', 'status', 'subject'])
                ->where('conversation_id = ?', (int)($this->getConversation()['conversation_id'] ?? 0))
        );
    }

    public function getStoreName(int $storeId): string
    {
        try {
            return (string)$this->storeManager->getStore($storeId)->getName();
        } catch (\Throwable $e) {
            return '#' . $storeId;
        }
    }

    public function label(string $list, $value): string
    {
        return (string)__(Options::label($list, $value));
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('wb_aichatbot/conversation/index');
    }

    public function getDeleteUrl(): string
    {
        return $this->getUrl('wb_aichatbot/conversation/delete', ['conversation_id' => (int)($this->getConversation()['conversation_id'] ?? 0)]);
    }

    public function getRequestUrl(int $id): string
    {
        return $this->getUrl('wb_aichatbot/request/edit', ['request_id' => $id]);
    }
}
