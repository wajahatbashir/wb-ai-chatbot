<?php
declare(strict_types=1);

namespace WB\AiChatbot\Block\Adminhtml\Request\Edit;

use Magento\Framework\Data\Form\Element\Fieldset;
use Magento\Framework\DataObject;
use WB\AiChatbot\Block\Adminhtml\Form\AbstractForm;
use WB\AiChatbot\Model\Source\Options;

class Form extends AbstractForm
{
    public const ID_FIELD = 'request_id';

    protected function legend(): \Magento\Framework\Phrase
    {
        return __('Support Request');
    }

    protected function addFields(Fieldset $fieldset, DataObject $model): void
    {
        $esc = function ($v) {
            return $this->escapeHtml((string)$v);
        };
        $fieldset->addField('code_note', 'note', [
            'label' => __('Request'),
            'text' => '<strong>' . $esc($model->getData('code')) . '</strong> &middot; ' . $esc($model->getData('created_at'))
                . ' &middot; ' . __('Store view %1', (int)$model->getData('store_id')),
        ]);
        $fieldset->addField('customer_note', 'note', [
            'label' => __('Customer'),
            'text' => $esc($model->getData('name') ?: '-') . ' &lt;<a href="mailto:' . $esc($model->getData('email')) . '">' . $esc($model->getData('email')) . '</a>&gt;'
                . ($model->getData('order_increment_id') ? '<br/>' . __('Order') . ' #' . $esc($model->getData('order_increment_id')) : ''),
        ]);
        if ($model->getData('conversation_id')) {
            $fieldset->addField('conversation_note', 'note', [
                'label' => __('Conversation'),
                'text' => '<a href="' . $this->escapeUrl($this->getUrl('wb_aichatbot/conversation/view', ['conversation_id' => (int)$model->getData('conversation_id')])) . '">'
                    . __('Open transcript #%1', (int)$model->getData('conversation_id')) . '</a>',
            ]);
        }
        $fieldset->addField('subject_note', 'note', ['label' => __('Subject'), 'text' => $esc($model->getData('subject'))]);
        $fieldset->addField('message_note', 'note', [
            'label' => __('Message'),
            'text' => '<div style="white-space:pre-wrap;background:#f7f7f7;border:1px solid #e3e3e3;padding:10px;border-radius:4px">' . $esc($model->getData('message')) . '</div>',
        ]);
        $statuses = [];
        foreach (Options::LISTS['request_status'] as $code => $label) {
            $statuses[] = ['value' => $code, 'label' => __($label)];
        }
        $fieldset->addField('status', 'select', ['name' => 'status', 'label' => __('Status'), 'values' => $statuses]);
        $fieldset->addField('admin_reply', 'textarea', [
            'name' => 'admin_reply', 'label' => __('Reply'), 'style' => 'height:180px',
            'note' => $model->getData('replied_at') ? __('Last reply sent %1.', $model->getData('replied_at')) : __('The customer can also read this reply in the chat by asking for the request status.'),
        ]);
        $fieldset->addField('send_email', 'checkbox', [
            'name' => 'send_email', 'label' => __('Email the reply to the customer'), 'value' => 1, 'checked' => true,
        ]);
    }

    protected function values(DataObject $model): array
    {
        // Form::setValues() nulls elements missing from the data, which would blank the checkbox value.
        return $model->getData() + ['send_email' => 1];
    }
}
