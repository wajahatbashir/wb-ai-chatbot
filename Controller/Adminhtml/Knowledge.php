<?php
declare(strict_types=1);

namespace WB\AiChatbot\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Framework\Registry;

abstract class Knowledge extends Action
{
    public const ADMIN_RESOURCE = 'WB_AiChatbot::knowledge';

    /**
     * @var Registry
     */
    protected $coreRegistry;

    public function __construct(Action\Context $context, Registry $registry)
    {
        $this->coreRegistry = $registry;
        parent::__construct($context);
    }
}
