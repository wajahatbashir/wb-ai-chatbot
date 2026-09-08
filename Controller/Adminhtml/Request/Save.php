<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Request;

use Magento\Backend\App\Action;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use WB\AiChatbot\Controller\Adminhtml\Crud\SaveAction;
use WB\AiChatbot\Model\Admin\EntityStore;
use WB\AiChatbot\Model\Chat\Mailer;
use WB\AiChatbot\Model\Chat\RequestManager;

/**
 * Status change + reply. A non-empty reply that differs from the stored one is emailed to the customer.
 */
class Save extends SaveAction
{
    public const ADMIN_RESOURCE = 'WB_AiChatbot::requests';
    public const TABLE = 'wb_aichatbot_request';
    public const ID_FIELD = 'request_id';
    public const ROUTE = 'wb_aichatbot/request';
    public const MENU = 'WB_AiChatbot::requests';
    public const TITLE = 'Support Requests';
    public const ITEM_TITLE = 'support request';
    public const FIELDS = ['status', 'admin_reply'];

    /**
     * @var Mailer
     */
    private $mailer;

    /**
     * @var DateTime
     */
    private $dateTime;

    /**
     * @var array|null
     */
    private $previous;

    /**
     * @var \WB\AiChatbot\Model\Config
     */
    private $config;

    public function __construct(Action\Context $context, Registry $registry, EntityStore $store, Mailer $mailer, DateTime $dateTime, \WB\AiChatbot\Model\Config $config)
    {
        parent::__construct($context, $registry, $store);
        $this->mailer = $mailer;
        $this->dateTime = $dateTime;
        $this->config = $config;
    }

    protected function prepare(array $data, int $id): array
    {
        if (!$id) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Support requests are created by customers in the chat.'));
        }
        $this->previous = $this->store->load(self::TABLE, self::ID_FIELD, $id);
        if (!$this->previous) {
            throw new \Magento\Framework\Exception\LocalizedException(__('This request no longer exists.'));
        }
        if (!isset(RequestManager::STATUS_LABELS[$data['status'] ?? ''])) {
            $data['status'] = $this->previous['status'];
        }
        $data['admin_reply'] = trim((string)($data['admin_reply'] ?? ''));
        if ($data['admin_reply'] !== '' && $data['admin_reply'] !== (string)$this->previous['admin_reply']) {
            $data['replied_at'] = $this->dateTime->gmtDate('Y-m-d H:i:s');
            if ($data['status'] === RequestManager::STATUS_NEW) {
                $data['status'] = RequestManager::STATUS_IN_PROGRESS;
            }
        }
        return $data;
    }

    protected function afterSave(int $id, array $data): void
    {
        if (empty($data['replied_at']) || !$this->previous || !$this->getRequest()->getParam('send_email')) {
            return;
        }
        $storeId = (int)$this->previous['store_id'];
        $sent = $this->mailer->send($this->config->getEmailTemplate('request_reply', $storeId), $storeId, (string)$this->previous['email'], (string)$this->previous['name'], [
            'code' => $this->previous['code'],
            'subject' => $this->previous['subject'],
            'message' => $this->previous['message'],
            'reply' => $data['admin_reply'],
            'customer_name' => $this->previous['name'] ?: $this->previous['email'],
        ]);
        if ($sent) {
            $this->messageManager->addSuccessMessage(__('Your reply was emailed to %1.', $this->previous['email']));
        } else {
            $this->messageManager->addWarningMessage(__('The reply was saved but the email could not be sent (see var/log/wb_aichatbot.log).'));
        }
    }
}
