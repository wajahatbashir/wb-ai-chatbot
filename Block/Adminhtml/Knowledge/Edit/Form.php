<?php
declare(strict_types=1);

namespace WB\AiChatbot\Block\Adminhtml\Knowledge\Edit;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\Registry;
use Magento\Store\Model\System\Store as SystemStore;
use WB\AiChatbot\Model\Kb\Document;
use WB\AiChatbot\Model\Source\KbSourceType;
use WB\AiChatbot\Model\Source\KbSyncStatus;

class Form extends Generic
{
    /**
     * @var SystemStore
     */
    private $systemStore;

    /**
     * @var KbSourceType
     */
    private $sourceType;

    /**
     * @var KbSyncStatus
     */
    private $syncStatus;

    /**
     * @var ResourceConnection
     */
    private $resource;

    public function __construct(
        Context $context,
        Registry $registry,
        FormFactory $formFactory,
        SystemStore $systemStore,
        KbSourceType $sourceType,
        KbSyncStatus $syncStatus,
        ResourceConnection $resource,
        array $data = []
    ) {
        $this->systemStore = $systemStore;
        $this->sourceType = $sourceType;
        $this->syncStatus = $syncStatus;
        $this->resource = $resource;
        parent::__construct($context, $registry, $formFactory, $data);
    }

    protected function _construct(): void
    {
        parent::_construct();
        $this->setId('knowledge_edit_form');
    }

    protected function _prepareForm(): self
    {
        /** @var Document $model */
        $model = $this->_coreRegistry->registry('current_model');
        $isManual = !$model->getId() || $model->isManual();

        $form = $this->_formFactory->create([
            'data' => ['id' => 'edit_form', 'action' => $this->getUrl('*/*/save', ['_current' => true]), 'method' => 'post'],
        ]);
        $form->setUseContainer(true);

        $fieldset = $form->addFieldset('base_fieldset', ['legend' => $isManual ? __('Article') : __('Synced Document')]);
        if ($model->getId()) {
            $fieldset->addField('document_id', 'hidden', ['name' => 'document_id']);
        }

        if (!$isManual) {
            $fieldset->addField('source_note', 'note', [
                'label' => __('Source'),
                'text' => $this->escapeHtml($this->label($this->sourceType->toOptionArray(), $model->getSourceType()))
                    . ' &middot; ' . $this->escapeHtml($model->getIdentifier())
                    . ' &middot; ' . __('Index status') . ': ' . $this->escapeHtml($this->label($this->syncStatus->toOptionArray(), $model->getSyncStatus()))
                    . '<br/><span class="note">' . __('Content is generated from the catalog / CMS and refreshes automatically; edit the source entity to change it.') . '</span>',
            ]);
        }

        $fieldset->addField('title', 'text', [
            'name' => 'title',
            'label' => __('Title'),
            'required' => true,
            'disabled' => !$isManual,
        ]);

        $fieldset->addField('store_id', 'select', [
            'name' => 'store_id',
            'label' => __('Store View'),
            'values' => array_merge(
                [['value' => 0, 'label' => __('All Store Views')]],
                $this->systemStore->getStoreValuesForForm(false, false)
            ),
            'disabled' => !$isManual,
            'note' => __('Manual articles for "All Store Views" are used in every store.'),
        ]);

        $fieldset->addField('url', 'text', [
            'name' => 'url',
            'label' => __('Read More URL'),
            'note' => __('Optional link the assistant offers with the answer.'),
            'disabled' => !$isManual,
        ]);

        $fieldset->addField('is_enabled', 'select', [
            'name' => 'is_enabled',
            'label' => __('Enabled'),
            'values' => [['value' => 1, 'label' => __('Yes')], ['value' => 0, 'label' => __('No')]],
            'note' => __('Disabled documents are never used in answers (synced documents stay disabled across syncs).'),
        ]);

        $fieldset->addField('body', 'textarea', [
            'name' => 'body',
            'label' => __('Content'),
            'required' => $isManual,
            'disabled' => !$isManual,
            'style' => 'height: 420px; font-family: monospace; font-size: 13px;',
            'note' => $isManual
                ? __('Plain text. Write it the way you would explain it to a customer; use blank lines between topics. Long articles are split into overlapping chunks automatically.')
                : '',
        ]);

        if ($model->getId()) {
            $attributes = $model->getAttributes();
            if ($attributes !== []) {
                $fieldset->addField('attributes_note', 'note', [
                    'label' => __('Structured Attributes'),
                    'text' => '<pre style="max-height:260px;overflow:auto;background:#f5f5f5;padding:8px;font-size:12px">'
                        . $this->escapeHtml(json_encode($attributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                        . '</pre>',
                ]);
            }
            $fieldset->addField('chunks_note', 'note', [
                'label' => __('Index Chunks'),
                'text' => $this->renderChunks((int)$model->getId()),
            ]);
        }

        $form->setValues($model->getData());
        $this->setForm($form);
        return parent::_prepareForm();
    }

    private function renderChunks(int $documentId): string
    {
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->resource->getTableName('wb_aichatbot_kb_chunk'), [
                    'chunk_index', 'token_count', 'embedding_model', 'text',
                    'has_embedding' => new \Zend_Db_Expr('embedding IS NOT NULL'),
                ])
                ->where('document_id = ?', $documentId)
                ->order('chunk_index ASC')
        );
        if ($rows === []) {
            return '<span class="note">' . __('Not indexed yet.') . '</span>';
        }
        $html = '';
        foreach ($rows as $row) {
            $html .= '<div style="margin:0 0 10px;padding:8px;border:1px solid #e3e3e3;background:#fafafa">'
                . '<strong>#' . (int)$row['chunk_index'] . '</strong> &middot; ~' . (int)$row['token_count'] . ' ' . __('tokens')
                . ' &middot; ' . ($row['has_embedding'] ? __('embedding: %1', $this->escapeHtml((string)$row['embedding_model'])) : __('no embedding'))
                . '<pre style="white-space:pre-wrap;margin:6px 0 0;font-size:12px;max-height:160px;overflow:auto">'
                . $this->escapeHtml((string)$row['text']) . '</pre></div>';
        }
        return $html;
    }

    private function label(array $options, string $value): string
    {
        foreach ($options as $option) {
            if ((string)$option['value'] === $value) {
                return (string)$option['label'];
            }
        }
        return $value;
    }
}
