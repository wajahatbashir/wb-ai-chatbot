<?php
declare(strict_types=1);

namespace WB\AiChatbot\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use WB\AiChatbot\Model\Manual\MarkdownRenderer;

/**
 * Renders USER_MANUAL.md inside the admin panel, so an admin never has to leave Magento to read it.
 */
class Manual extends Template
{
    /**
     * @var string
     */
    protected $_template = 'WB_AiChatbot::manual.phtml';

    /**
     * @var ModuleDirReader
     */
    private $moduleDirReader;

    /**
     * @var MarkdownRenderer
     */
    private $renderer;

    /**
     * @var array{html: string, headings: array}|null
     */
    private $rendered;

    public function __construct(Context $context, ModuleDirReader $moduleDirReader, MarkdownRenderer $renderer, array $data = [])
    {
        $this->moduleDirReader = $moduleDirReader;
        $this->renderer = $renderer;
        parent::__construct($context, $data);
    }

    public function getManualHtml(): string
    {
        return $this->result()['html'] ?? '';
    }

    /**
     * @return array<int, array{level:int, text:string, slug:string}>
     */
    public function getHeadings(): array
    {
        return $this->result()['headings'] ?? [];
    }

    public function isAvailable(): bool
    {
        return $this->manualPath() !== null;
    }

    /**
     * @return array{html: string, headings: array}
     */
    private function result(): array
    {
        if ($this->rendered === null) {
            $path = $this->manualPath();
            $markdown = $path !== null ? (string)file_get_contents($path) : '';
            $this->rendered = $markdown !== '' ? $this->renderer->render($markdown) : ['html' => '', 'headings' => []];
        }
        return $this->rendered;
    }

    private function manualPath(): ?string
    {
        // Module\Dir\Reader resolves the module's real base directory regardless of app/code vs a Composer vendor
        // install, so this keeps working if the module is later distributed and required via Composer instead.
        $dir = $this->moduleDirReader->getModuleDir('', 'WB_AiChatbot');
        $path = $dir . '/USER_MANUAL.md';
        return is_readable($path) ? $path : null;
    }
}
