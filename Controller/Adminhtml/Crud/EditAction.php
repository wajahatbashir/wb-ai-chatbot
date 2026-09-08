<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Crud;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;

abstract class EditAction extends AbstractCrud implements HttpGetActionInterface
{
    public function execute(): ResultInterface
    {
        $id = (int)$this->getRequest()->getParam(static::ID_FIELD);
        $data = [];
        if ($id) {
            $data = $this->store->load(static::TABLE, static::ID_FIELD, $id);
            if (!$data) {
                $this->messageManager->addErrorMessage(__('This record no longer exists.'));
                return $this->redirectToGrid();
            }
        } else {
            $data = $this->defaults();
        }
        // Prefill from the URL (e.g. "Create Q&A from unanswered question").
        foreach ((array)$this->getRequest()->getParam('prefill', []) as $key => $value) {
            if (!$id && is_string($value)) {
                $data[$key] = $value;
            }
        }
        $model = $this->registerModel($data);
        $page = $this->page(__(static::TITLE), static::MENU);
        $page->getConfig()->getTitle()->prepend($id ? $this->pageTitle($data) : __('New %1', __(static::ITEM_TITLE)));
        return $page;
    }

    protected function defaults(): array
    {
        return [];
    }

    protected function pageTitle(array $data): string
    {
        return (string)($data['title'] ?? $data['name'] ?? $data['question'] ?? $data['code'] ?? __(static::ITEM_TITLE));
    }
}
