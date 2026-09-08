<?php
declare(strict_types=1);

namespace WB\AiChatbot\Block\Adminhtml;

use Magento\Backend\Block\Widget\Context;
use Magento\Backend\Block\Widget\Form\Container;
use Magento\Framework\Registry;

/**
 * Generic edit-form container. Layout arguments: controller (e.g. "adminhtml_qa" -> Block\Adminhtml\Qa\Edit\Form),
 * id_field, item_title, delete_label, no_delete (bool).
 */
class FormContainer extends Container
{
    /**
     * @var Registry
     */
    protected $coreRegistry;

    public function __construct(Context $context, Registry $registry, array $data = [])
    {
        $this->coreRegistry = $registry;
        parent::__construct($context, $data);
    }

    protected function _construct(): void
    {
        $this->_objectId = (string)$this->getData('id_field');
        $this->_blockGroup = 'WB_AiChatbot';
        $this->_controller = (string)$this->getData('controller');
        parent::_construct();

        $model = $this->coreRegistry->registry('current_model');
        $this->buttonList->update('save', 'label', __((string)($this->getData('save_label') ?: 'Save')));
        if ($this->getData('no_delete') || !$model || !$model->getData($this->_objectId)) {
            $this->buttonList->remove('delete');
        } else {
            $this->buttonList->update('delete', 'label', __((string)($this->getData('delete_label') ?: 'Delete')));
        }
        $this->buttonList->add(
            'saveandcontinue',
            ['label' => __('Save and Continue Edit'), 'class' => 'save', 'onclick' => 'saveAndContinueEdit()'],
            -100
        );
        $this->_formScripts[] = "
            require(['jquery'], function (\$) {
                window.saveAndContinueEdit = function () {
                    var form = \$('#edit_form');
                    var action = form.attr('action');
                    form.attr('action', action + 'back/edit/');
                    form.trigger('submit');
                    form.attr('action', action);
                };
            });
        ";
    }

    public function getHeaderText(): \Magento\Framework\Phrase
    {
        $model = $this->coreRegistry->registry('current_model');
        $title = __((string)$this->getData('item_title'));
        if ($model && $model->getData($this->_objectId)) {
            return __('Edit %1', $title);
        }
        return __('New %1', $title);
    }
}
