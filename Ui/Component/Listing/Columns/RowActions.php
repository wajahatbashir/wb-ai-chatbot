<?php
declare(strict_types=1);

namespace WB\AiChatbot\Ui\Component\Listing\Columns;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Generic Edit/Delete row actions. Listing config: <item name="route">wb_aichatbot/qa</item>,
 * <item name="id_field">qa_id</item>, optional <item name="edit_label">View</item>, <item name="extra">...</item>.
 */
class RowActions extends Column
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
        $route = (string)$this->getData('config/route');
        $idField = (string)$this->getData('config/id_field');
        $editLabel = (string)($this->getData('config/edit_label') ?: 'Edit');
        $editAction = (string)($this->getData('config/edit_action') ?: 'edit');
        $noDelete = (bool)$this->getData('config/no_delete');
        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item[$idField])) {
                continue;
            }
            $id = $item[$idField];
            $actions = [
                'edit' => [
                    'href' => $this->urlBuilder->getUrl($route . '/' . $editAction, [$idField => $id]),
                    'label' => __($editLabel),
                ],
            ];
            foreach ((array)$this->getData('config/extra') as $key => $extra) {
                $actions[$key] = [
                    'href' => $this->urlBuilder->getUrl((string)$extra['route'], [$idField => $id]),
                    'label' => __((string)$extra['label']),
                    'post' => !empty($extra['post']),
                ];
            }
            if (!$noDelete) {
                $actions['delete'] = [
                    'href' => $this->urlBuilder->getUrl($route . '/delete', [$idField => $id]),
                    'label' => __('Delete'),
                    'confirm' => ['title' => __('Delete'), 'message' => __('Are you sure you want to delete this record?')],
                    'post' => true,
                ];
            }
            $item[$this->getData('name')] = $actions;
        }
        unset($item);
        return $dataSource;
    }
}
