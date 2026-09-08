<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Unanswered;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use WB\AiChatbot\Controller\Adminhtml\Crud\AbstractCrud;

/**
 * "Create Q&A from this question": opens the Q&A form pre-filled; saving it marks the question answered.
 */
class ToQa extends AbstractCrud implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'WB_AiChatbot::unanswered';
    public const TABLE = 'wb_aichatbot_unanswered';
    public const ID_FIELD = 'unanswered_id';
    public const ROUTE = 'wb_aichatbot/unanswered';

    public function execute(): ResultInterface
    {
        $id = (int)$this->getRequest()->getParam(self::ID_FIELD);
        $row = $id ? $this->store->load(self::TABLE, self::ID_FIELD, $id) : null;
        if (!$row) {
            return $this->redirectToGrid();
        }
        return $this->resultRedirectFactory->create()->setPath('wb_aichatbot/qa/new', [
            'unanswered_id' => $id,
            '_query' => ['prefill' => ['question' => mb_substr((string)$row['question'], 0, 500)]],
        ]);
    }
}
