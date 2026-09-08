<?php
declare(strict_types=1);

namespace WB\AiChatbot\Block\Adminhtml\Credential;

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
        $this->_objectId = 'credential_id';
        $this->_blockGroup = 'WB_AiChatbot';
        // Single lowercase word, so Container::_buildFormClassName() resolves to Block\Adminhtml\Credential\Edit\Form.
        $this->_controller = 'adminhtml_credential';

        parent::_construct();

        $this->buttonList->update('save', 'label', __('Save & Check Connection'));
        $this->buttonList->update('delete', 'label', __('Delete Credential'));
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
        if ($model && $model->getId()) {
            return __("Edit Provider Credential '%1'", $this->escapeHtml($model->getAlias()));
        }
        return __('New Provider Credential');
    }
}
