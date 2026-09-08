<?php
declare(strict_types=1);

namespace WB\AiChatbot\Ui\Component\Listing\Columns;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class CredentialActions extends Column
{
    private const URL_PATH_EDIT = 'wb_aichatbot/credential/edit';
    private const URL_PATH_CHECK = 'wb_aichatbot/credential/check';
    private const URL_PATH_DELETE = 'wb_aichatbot/credential/delete';

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
            if (!isset($item['credential_id'])) {
                continue;
            }
            $id = $item['credential_id'];
            $item[$this->getData('name')] = [
                'edit' => [
                    'href' => $this->urlBuilder->getUrl(self::URL_PATH_EDIT, ['credential_id' => $id]),
                    'label' => __('Edit'),
                ],
                'check' => [
                    'href' => $this->urlBuilder->getUrl(self::URL_PATH_CHECK, ['credential_id' => $id]),
                    'label' => __('Check connection'),
                ],
                'delete' => [
                    'href' => $this->urlBuilder->getUrl(self::URL_PATH_DELETE, ['credential_id' => $id]),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete "%1"', $item['alias'] ?? ''),
                        'message' => __('Are you sure you want to delete this provider credential?'),
                    ],
                    'post' => true,
                ],
            ];
        }
        unset($item);

        return $dataSource;
    }
}
