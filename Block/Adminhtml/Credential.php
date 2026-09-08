<?php
declare(strict_types=1);

namespace WB\AiChatbot\Block\Adminhtml;

use Magento\Backend\Block\Widget\Container;

/**
 * Toolbar for the AI Providers grid (the grid itself is the UI component listing).
 */
class Credential extends Container
{
    protected function _construct(): void
    {
        $this->_headerText = __('AI Providers');
        parent::_construct();

        $this->addButton('add', [
            'label' => __('Add Provider Credential'),
            'onclick' => "setLocation('" . $this->getUrl('*/*/new') . "')",
            'class' => 'add primary',
        ]);
        $this->addButton('check_all', [
            'label' => __('Check All Connections'),
            'onclick' => "setLocation('" . $this->getUrl('*/*/check') . "')",
            'class' => 'secondary',
        ]);
    }
}
