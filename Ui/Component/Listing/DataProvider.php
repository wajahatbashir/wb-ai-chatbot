<?php
declare(strict_types=1);

namespace WB\AiChatbot\Ui\Component\Listing;

use Magento\Ui\DataProvider\AbstractDataProvider;
use WB\AiChatbot\Model\ResourceModel\Credential\CollectionFactory;

/**
 * Real concrete class (not a virtualType) - AbstractDataProvider is abstract and cannot be instantiated by DI.
 */
class DataProvider extends AbstractDataProvider
{
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }
}
