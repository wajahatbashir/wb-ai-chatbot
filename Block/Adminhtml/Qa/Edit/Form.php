<?php
declare(strict_types=1);

namespace WB\AiChatbot\Block\Adminhtml\Qa\Edit;

use Magento\Framework\Data\Form\Element\Fieldset;
use Magento\Framework\DataObject;
use WB\AiChatbot\Block\Adminhtml\Form\AbstractForm;

class Form extends AbstractForm
{
    public const ID_FIELD = 'qa_id';

    protected function legend(): \Magento\Framework\Phrase
    {
        return __('Q&A Pair');
    }

    protected function addFields(Fieldset $fieldset, DataObject $model): void
    {
        $fieldset->addField('question', 'text', [
            'name' => 'question', 'label' => __('Question'), 'required' => true,
            'note' => __('The way a customer would ask it, e.g. "Do you offer cash on delivery?"'),
        ]);
        $fieldset->addField('alternatives', 'textarea', [
            'name' => 'alternatives', 'label' => __('Alternative phrasings'), 'style' => 'height:110px',
            'note' => __('One per line. A customer message matching any phrasing (roughly 55% of the words) gets this answer before any AI call - in both modes.'),
        ]);
        $fieldset->addField('answer', 'textarea', [
            'name' => 'answer', 'label' => __('Answer'), 'required' => true, 'style' => 'height:180px',
            'note' => __('Plain text; **bold**, lists ("- ") and links are rendered in the chat.'),
        ]);
        $fieldset->addField('url', 'text', ['name' => 'url', 'label' => __('Read more URL'), 'note' => __('Optional link shown as a card under the answer.')]);
        $fieldset->addField('store_ids', 'multiselect', [
            'name' => 'store_ids', 'label' => __('Store Views'), 'values' => $this->storeValues(true),
            'note' => __('Leave "All Store Views" selected to use everywhere.'),
        ]);
        $fieldset->addField('is_enabled', 'select', ['name' => 'is_enabled', 'label' => __('Enabled'), 'values' => $this->yesNo()]);
        if ($model->getData('hits') !== null) {
            $fieldset->addField('hits_note', 'note', ['label' => __('Times used'), 'text' => (int)$model->getData('hits')]);
        }
        if ($this->getRequest()->getParam('unanswered_id')) {
            $fieldset->addField('unanswered_id', 'hidden', ['name' => 'unanswered_id', 'value' => (int)$this->getRequest()->getParam('unanswered_id')]);
        }
    }

    protected function values(DataObject $model): array
    {
        $values = $model->getData();
        $values['store_ids'] = isset($values['store_ids']) && $values['store_ids'] !== '' && $values['store_ids'] !== null ? explode(',', (string)$values['store_ids']) : ['0'];
        if (!isset($values['is_enabled'])) {
            $values['is_enabled'] = 1;
        }
        if ($this->getRequest()->getParam('unanswered_id')) {
            $values['unanswered_id'] = (int)$this->getRequest()->getParam('unanswered_id');
        }
        return $values;
    }
}
