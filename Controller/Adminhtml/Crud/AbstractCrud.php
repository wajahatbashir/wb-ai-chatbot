<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Crud;

use Magento\Backend\App\Action;
use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use WB\AiChatbot\Model\Admin\EntityStore;

/**
 * Shared base for the flat-table admin screens. Concrete controllers only set the constants.
 */
abstract class AbstractCrud extends Action
{
    public const ADMIN_RESOURCE = 'WB_AiChatbot::menu';
    public const TABLE = '';
    public const ID_FIELD = '';
    public const ROUTE = '';          // e.g. wb_aichatbot/qa
    public const MENU = '';           // e.g. WB_AiChatbot::qa
    public const TITLE = '';          // grid title
    public const ITEM_TITLE = '';     // form title
    public const STATUS_FIELD = 'is_enabled';

    /**
     * @var Registry
     */
    protected $coreRegistry;

    /**
     * @var EntityStore
     */
    protected $store;

    public function __construct(Action\Context $context, Registry $registry, EntityStore $store)
    {
        parent::__construct($context);
        $this->coreRegistry = $registry;
        $this->store = $store;
    }

    protected function redirectToGrid(): \Magento\Framework\Controller\ResultInterface
    {
        return $this->resultRedirectFactory->create()->setPath(static::ROUTE . '/index');
    }

    /**
     * @param string|\Magento\Framework\Phrase $title
     */
    protected function page($title, string $activeMenu): \Magento\Backend\Model\View\Result\Page
    {
        /** @var \Magento\Backend\Model\View\Result\Page $page */
        $page = $this->resultFactory->create(\Magento\Framework\Controller\ResultFactory::TYPE_PAGE);
        $page->setActiveMenu($activeMenu);
        $page->getConfig()->getTitle()->prepend($title);
        return $page;
    }

    protected function registerModel(array $data): DataObject
    {
        $model = new DataObject($data);
        $this->coreRegistry->register('current_model', $model, true);
        return $model;
    }
}
