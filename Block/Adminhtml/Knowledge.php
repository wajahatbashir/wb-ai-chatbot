<?php
declare(strict_types=1);

namespace WB\AiChatbot\Block\Adminhtml;

use Magento\Backend\Block\Widget\Container;
use Magento\Backend\Block\Widget\Context;
use WB\AiChatbot\Model\Kb\Sync\ProviderPool;

/**
 * Toolbar for the Knowledge Base grid.
 */
class Knowledge extends Container
{
    /**
     * @var ProviderPool
     */
    private $providerPool;

    public function __construct(Context $context, ProviderPool $providerPool, array $data = [])
    {
        $this->providerPool = $providerPool;
        parent::__construct($context, $data);
    }

    protected function _construct(): void
    {
        $this->_headerText = __('Knowledge Base');
        parent::_construct();

        $this->addButton('add', [
            'label' => __('Add Article'),
            'onclick' => "setLocation('" . $this->getUrl('*/*/new') . "')",
            'class' => 'add primary',
        ]);
        $this->addButton('tester', [
            'label' => __('Retrieval Tester'),
            'onclick' => "setLocation('" . $this->getUrl('*/*/tester') . "')",
            'class' => 'secondary',
        ]);

        $options = [
            'all' => [
                'label' => __('Everything (background)'),
                'onclick' => "setLocation('" . $this->getUrl('*/*/sync') . "')",
            ],
        ];
        foreach ($this->providerPool->getAll() as $code => $provider) {
            $options[$code] = [
                'label' => __($provider->getTitle()),
                'onclick' => "setLocation('" . $this->getUrl('*/*/sync', ['type' => $code]) . "')",
            ];
        }
        $this->addButton('sync', [
            'label' => __('Sync Now'),
            'class_name' => \Magento\Backend\Block\Widget\Button\SplitButton::class,
            'class' => 'secondary',
            'button_class' => '',
            'options' => $options,
        ]);
    }
}
