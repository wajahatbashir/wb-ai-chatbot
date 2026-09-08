<?php
declare(strict_types=1);

namespace WB\AiChatbot\Block\Adminhtml\Intent\Edit;

use Magento\Framework\Data\Form\Element\Fieldset;
use Magento\Framework\DataObject;
use WB\AiChatbot\Block\Adminhtml\Form\AbstractForm;
use WB\AiChatbot\Model\Source\Options;

class Form extends AbstractForm
{
    public const ID_FIELD = 'intent_id';

    protected function legend(): \Magento\Framework\Phrase
    {
        return __('Intent Dictionary (Assist mode)');
    }

    protected function addFields(Fieldset $fieldset, DataObject $model): void
    {
        $intents = [];
        foreach (Options::LISTS['intent_code'] as $code => $label) {
            $intents[] = ['value' => $code, 'label' => __($label) . ' (' . $code . ')'];
        }
        $languages = [];
        foreach (Options::LISTS['language'] as $code => $label) {
            $languages[] = ['value' => $code, 'label' => __($label) . ' (' . $code . ')'];
        }
        $fieldset->addField('intent_code', 'select', ['name' => 'intent_code', 'label' => __('Intent'), 'values' => $intents, 'required' => true]);
        $fieldset->addField('language', 'select', [
            'name' => 'language', 'label' => __('Language'), 'values' => $languages, 'required' => true,
            'note' => __('"en" rows replace the built-in English defaults for that intent; other languages extend them.'),
        ]);
        $fieldset->addField('keywords', 'textarea', [
            'name' => 'keywords', 'label' => __('Trigger words / patterns'), 'required' => true, 'style' => 'height:220px;font-family:monospace',
            'note' => __('One per line. A single word matches as a whole word (plurals allowed); several words must all appear; a line written as /regex/i is a regular expression. Lines starting with # are comments.'),
        ]);
        $fieldset->addField('priority', 'text', [
            'name' => 'priority', 'label' => __('Priority'), 'class' => 'validate-number',
            'note' => __('When several intents match, the highest priority wins (order lookups ~55-60, policies ~35-40, product search 30).'),
        ]);
        $fieldset->addField('is_enabled', 'select', ['name' => 'is_enabled', 'label' => __('Enabled'), 'values' => $this->yesNo()]);
    }

    protected function values(DataObject $model): array
    {
        $values = $model->getData();
        $values += ['language' => 'en', 'priority' => 30, 'is_enabled' => 1];
        return $values;
    }
}
