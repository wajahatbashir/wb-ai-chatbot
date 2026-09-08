<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml\Crud;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

abstract class SaveAction extends AbstractCrud implements HttpPostActionInterface
{
    /**
     * Columns accepted from the form, in table terms.
     */
    public const FIELDS = [];

    public function execute(): ResultInterface
    {
        $post = $this->getRequest()->getPostValue();
        if (!$post) {
            return $this->redirectToGrid();
        }
        $id = (int)($post[static::ID_FIELD] ?? 0);
        try {
            $data = [];
            foreach (static::FIELDS as $field) {
                if (array_key_exists($field, $post)) {
                    $data[$field] = is_array($post[$field]) ? implode(',', $post[$field]) : trim((string)$post[$field]);
                }
            }
            $data = $this->prepare($data, $id);
            $id = $this->store->save(static::TABLE, static::ID_FIELD, $data, $id ?: null);
            $this->afterSave($id, $data);
            $this->messageManager->addSuccessMessage(__('The %1 was saved.', __(static::ITEM_TITLE)));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->resultRedirectFactory->create()->setPath(static::ROUTE . '/edit', [static::ID_FIELD => $id ?: null]);
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Could not save: %1', $e->getMessage()));
            return $this->resultRedirectFactory->create()->setPath(static::ROUTE . '/edit', [static::ID_FIELD => $id ?: null]);
        }
        if ($this->getRequest()->getParam('back')) {
            return $this->resultRedirectFactory->create()->setPath(static::ROUTE . '/edit', [static::ID_FIELD => $id]);
        }
        return $this->redirectToGrid();
    }

    /**
     * Validate / normalise before writing.
     *
     * @throws LocalizedException
     */
    protected function prepare(array $data, int $id): array
    {
        return $data;
    }

    protected function afterSave(int $id, array $data): void
    {
    }

    protected function require(array $data, array $fields): void
    {
        foreach ($fields as $field => $label) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                throw new LocalizedException(__('%1 is required.', $label));
            }
        }
    }
}
