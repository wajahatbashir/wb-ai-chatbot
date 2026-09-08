<?php
declare(strict_types=1);

namespace WB\AiChatbot\Ui\Component\Listing;

use Magento\Ui\DataProvider\AbstractDataProvider;
use WB\AiChatbot\Model\Kb\ResourceModel\Document\CollectionFactory;

class KnowledgeDataProvider extends AbstractDataProvider
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
        $collection = $collectionFactory->create();
        $chunkTable = $collection->getTable('wb_aichatbot_kb_chunk');
        $collection->getSelect()->joinLeft(
            ['ch' => new \Zend_Db_Expr(
                '(SELECT document_id, COUNT(*) AS chunks, SUM(embedding IS NOT NULL) AS embedded FROM ' . $chunkTable . ' GROUP BY document_id)'
            )],
            'ch.document_id = main_table.document_id',
            ['chunks' => new \Zend_Db_Expr('IFNULL(ch.chunks, 0)'), 'embedded' => new \Zend_Db_Expr('IFNULL(ch.embedded, 0)')]
        );
        $collection->addFilterToMap('chunks', 'ch.chunks');
        $collection->addFilterToMap('embedded', 'ch.embedded');
        $collection->addFilterToMap('document_id', 'main_table.document_id');
        $collection->addFilterToMap('store_id', 'main_table.store_id');
        $this->collection = $collection;
    }

    public function getData(): array
    {
        $data = parent::getData();
        foreach ($data['items'] as &$item) {
            // The grid must never ship the full body; the view page shows it.
            $item['body_excerpt'] = mb_substr(trim((string)($item['body'] ?? '')), 0, 160);
            unset($item['body'], $item['attributes']);
        }
        unset($item);
        return $data;
    }
}
