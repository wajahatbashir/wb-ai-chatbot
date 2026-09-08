<?php
declare(strict_types=1);

namespace WB\AiChatbot\Block\Adminhtml\Guidance\Edit;

use Magento\Framework\Data\Form\Element\Fieldset;
use Magento\Framework\DataObject;
use WB\AiChatbot\Block\Adminhtml\Form\AbstractForm;
use WB\AiChatbot\Model\Source\Options;

class Form extends AbstractForm
{
    public const ID_FIELD = 'guidance_id';

    protected function legend(): \Magento\Framework\Phrase
    {
        return __('Guidance Rule (AI mode)');
    }

    protected function addFields(Fieldset $fieldset, DataObject $model): void
    {
        $statuses = [];
        foreach (Options::LISTS['guidance_status'] as $code => $label) {
            $statuses[] = ['value' => $code, 'label' => __($label)];
        }
        $fieldset->addField('name', 'text', ['name' => 'name', 'label' => __('Name'), 'required' => true, 'note' => __('Internal label, e.g. "Bridal consultations".')]);
        $fieldset->addField('trigger_description', 'textarea', [
            'name' => 'trigger_description', 'label' => __('When (trigger)'), 'required' => true, 'style' => 'height:70px',
            'note' => __('Describe the situation in plain language, e.g. "the customer asks about custom or bridal orders".'),
        ]);
        $fieldset->addField('instructions', 'textarea', [
            'name' => 'instructions', 'label' => __('Instructions'), 'required' => true, 'style' => 'height:160px',
            'note' => __('What the assistant should do or say, e.g. "explain that custom orders take 6-9 weeks and offer to create a support request with the customer\'s design ideas".'),
        ]);
        $fieldset->addField('status', 'select', [
            'name' => 'status', 'label' => __('Status'), 'values' => $statuses,
            'note' => __('"Testing" rules are applied only in the admin Preview Chat.'),
        ]);
        $fieldset->addField('sort_order', 'text', ['name' => 'sort_order', 'label' => __('Sort Order'), 'class' => 'validate-number']);
    }

    protected function values(DataObject $model): array
    {
        return $model->getData() + ['status' => 'enabled', 'sort_order' => 10];
    }
}
