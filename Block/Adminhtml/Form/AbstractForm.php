<?php
declare(strict_types=1);

namespace WB\AiChatbot\Block\Adminhtml\Form;

use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Form\Generic;
use Magento\Framework\Data\Form\Element\Fieldset;
use Magento\Framework\Data\FormFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use Magento\Store\Model\System\Store as SystemStore;

/**
 * Legacy-form base: subclasses add fields in addFields() and set ID_FIELD.
 */
abstract class AbstractForm extends Generic
{
    public const ID_FIELD = '';

    /**
     * @var SystemStore
     */
    protected $systemStore;

    public function __construct(Context $context, Registry $registry, FormFactory $formFactory, SystemStore $systemStore, array $data = [])
    {
        $this->systemStore = $systemStore;
        parent::__construct($context, $registry, $formFactory, $data);
    }

    protected function _prepareForm(): self
    {
        /** @var DataObject $model */
        $model = $this->_coreRegistry->registry('current_model') ?: new DataObject();
        $form = $this->_formFactory->create([
            'data' => ['id' => 'edit_form', 'action' => $this->getUrl('*/*/save', ['_current' => true]), 'method' => 'post'],
        ]);
        $form->setUseContainer(true);
        $fieldset = $form->addFieldset('base_fieldset', ['legend' => $this->legend()]);
        if ($model->getData(static::ID_FIELD)) {
            $fieldset->addField(static::ID_FIELD, 'hidden', ['name' => static::ID_FIELD]);
        }
        $this->addFields($fieldset, $model);
        $form->setValues($this->values($model));
        $this->setForm($form);
        return parent::_prepareForm();
    }

    abstract protected function addFields(Fieldset $fieldset, DataObject $model): void;

    abstract protected function legend(): \Magento\Framework\Phrase;

    protected function values(DataObject $model): array
    {
        return $model->getData();
    }

    protected function yesNo(): array
    {
        return [['value' => 1, 'label' => __('Yes')], ['value' => 0, 'label' => __('No')]];
    }

    protected function storeValues(bool $withAll = true): array
    {
        $values = $this->systemStore->getStoreValuesForForm(false, false);
        return $withAll ? array_merge([['value' => 0, 'label' => __('All Store Views')]], $values) : $values;
    }
}
