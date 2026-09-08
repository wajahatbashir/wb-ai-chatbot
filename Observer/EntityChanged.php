<?php
declare(strict_types=1);

namespace WB\AiChatbot\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use WB\AiChatbot\Model\Config;
use WB\AiChatbot\Model\Kb\Queue\Publisher;
use WB\AiChatbot\Model\Kb\ResourceModel\Document as DocumentResource;

/**
 * Keeps the knowledge base current: a saved/deleted product, category or CMS page marks its documents dirty and
 * queues a re-sync. Configured per event in events.xml via the "source_type" and "data_key" arguments.
 */
class EntityChanged implements ObserverInterface
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Publisher
     */
    private $publisher;

    /**
     * @var DocumentResource
     */
    private $documentResource;

    /**
     * @var string
     */
    private $sourceType;

    /**
     * @var string
     */
    private $dataKey;

    public function __construct(
        Config $config,
        Publisher $publisher,
        DocumentResource $documentResource,
        string $sourceType,
        string $dataKey
    ) {
        $this->config = $config;
        $this->publisher = $publisher;
        $this->documentResource = $documentResource;
        $this->sourceType = $sourceType;
        $this->dataKey = $dataKey;
    }

    public function execute(Observer $observer): void
    {
        // Runs even while the chatbot is disabled, so the knowledge base is current the moment it is switched on.
        $entity = $observer->getEvent()->getData($this->dataKey);
        if (!$entity || !method_exists($entity, 'getId') || !$entity->getId()) {
            return;
        }
        $identifier = $this->sourceType . '.' . (int)$entity->getId();
        try {
            $this->documentResource->markStatusByIdentifier($identifier, \WB\AiChatbot\Model\Kb\Document::STATUS_DIRTY);
            $this->publisher->publishResync($this->sourceType, $identifier);
        } catch (\Throwable $e) {
            // Never break a product/category/page save because of the chatbot index.
        }
    }
}
