<?php
declare(strict_types=1);

namespace WB\AiChatbot\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use WB\AiChatbot\Api\CredentialRepositoryInterface;
use WB\AiChatbot\Api\Data\CredentialInterface;
use WB\AiChatbot\Model\CredentialFactory;
use WB\AiChatbot\Model\Provider\Mock;

/**
 * Seeds the key-less Mock credential at the end of the fallback chain. It is inert unless
 * Developer > Allow Mock Provider is turned on, so shipping it enabled is safe on production.
 */
class SeedMockCredential implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var CredentialFactory
     */
    private $credentialFactory;

    /**
     * @var CredentialRepositoryInterface
     */
    private $credentialRepository;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        CredentialFactory $credentialFactory,
        CredentialRepositoryInterface $credentialRepository
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->credentialFactory = $credentialFactory;
        $this->credentialRepository = $credentialRepository;
    }

    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $credential = $this->credentialFactory->create();
        $credential->setAlias('Mock (development only)');
        $credential->setProviderCode(Mock::CODE);
        $credential->setModel('mock-1');
        $credential->setEmbeddingModel('mock-embed-256');
        $credential->setSupportsVision(false);
        $credential->setSortOrder(1000);
        $credential->setIsEnabled(true);
        $credential->setStatus(CredentialInterface::STATUS_OK);
        $credential->setStatusMessage('Mock provider - always available, no API calls');
        $this->credentialRepository->save($credential);

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
