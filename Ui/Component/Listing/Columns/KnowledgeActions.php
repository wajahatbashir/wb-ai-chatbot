<?php
declare(strict_types=1);

namespace WB\AiChatbot\Ui\Component\Listing\Columns;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use WB\AiChatbot\Model\Kb\Document;

class KnowledgeActions extends Column
{
    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->urlBuilder = $urlBuilder;
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item['document_id'])) {
                continue;
            }
            $id = (int)$item['document_id'];
            $isManual = ($item['source_type'] ?? '') === Document::SOURCE_MANUAL;
            $actions = [
                'edit' => [
                    'href' => $this->urlBuilder->getUrl('wb_aichatbot/knowledge/edit', ['document_id' => $id]),
                    'label' => $isManual ? __('Edit') : __('View'),
                ],
                'reindex' => [
                    'href' => $this->urlBuilder->getUrl('wb_aichatbot/knowledge/reindex', ['document_id' => $id]),
                    'label' => $isManual ? __('Re-index') : __('Re-sync & index'),
                ],
                'delete' => [
                    'href' => $this->urlBuilder->getUrl('wb_aichatbot/knowledge/delete', ['document_id' => $id]),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete "%1"', $item['title'] ?? ''),
                        'message' => $isManual
                            ? __('Delete this article from the knowledge base?')
                            : __('Delete this synced document? It comes back on the next sync unless you disable it instead.'),
                    ],
                    'post' => true,
                ],
            ];
            $item[$this->getData('name')] = $actions;
        }
        unset($item);
        return $dataSource;
    }
}
