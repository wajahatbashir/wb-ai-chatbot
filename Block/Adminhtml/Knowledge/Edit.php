<?php
declare(strict_types=1);

namespace WB\AiChatbot\Block\Adminhtml\Knowledge;

use Magento\Backend\Block\Widget\Context;
use Magento\Backend\Block\Widget\Form\Container;
use Magento\Framework\Registry;

class Edit extends Container
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
        $this->_objectId = 'document_id';
        $this->_blockGroup = 'WB_AiChatbot';
        $this->_controller = 'adminhtml_knowledge';
        parent::_construct();

        $model = $this->coreRegistry->registry('current_model');
        $isManual = !$model || !$model->getId() || $model->isManual();

        $this->buttonList->update('save', 'label', $isManual ? __('Save & Index Article') : __('Save'));
        $this->buttonList->update('delete', 'label', __('Delete Document'));
        if ($isManual) {
            $this->buttonList->add(
                'saveandcontinue',
                ['label' => __('Save and Continue Edit'), 'class' => 'save', 'onclick' => 'saveAndContinueEdit()'],
                -100
            );
        }
        if ($model && $model->getId()) {
            $this->buttonList->add('reindex', [
                'label' => $isManual ? __('Re-index') : __('Re-sync & Index Now'),
                'onclick' => "setLocation('" . $this->getUrl('*/*/reindex', ['document_id' => $model->getId(), 'back' => 1]) . "')",
                'class' => 'secondary',
            ], -90);
        }

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
        if ($model && $model->getId()) {
            return __("Knowledge Base Document '%1'", $this->escapeHtml($model->getTitle()));
        }
        return __('New Knowledge Base Article');
    }
}
