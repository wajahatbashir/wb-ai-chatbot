<?php
declare(strict_types=1);

namespace WB\AiChatbot\Block\Adminhtml;

use Magento\Backend\Block\Widget\Container;

/**
 * Header + buttons above a UI-component grid; configured from layout arguments (header, add_label, add_route,
 * extra_buttons[label => route]).
 */
class GridContainer extends Container
{
    protected function _construct(): void
    {
        $this->_headerText = __((string)$this->getData('header'));
        parent::_construct();
        if ($this->getData('add_route')) {
            $this->addButton('add', [
                'label' => __((string)($this->getData('add_label') ?: 'Add New')),
                'onclick' => "setLocation('" . $this->getUrl((string)$this->getData('add_route')) . "')",
                'class' => 'add primary',
            ]);
        }
        foreach ((array)$this->getData('extra_buttons') as $key => $button) {
            $this->addButton((string)$key, [
                'label' => __((string)$button['label']),
                'onclick' => "setLocation('" . $this->getUrl((string)$button['route']) . "')",
                'class' => 'secondary',
            ]);
        }
    }
}
