<?php
declare(strict_types=1);

namespace WB\AiChatbot\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use WB\AiChatbot\Model\Chat\IntentRouter;

/**
 * Copies the built-in English intent dictionaries into wb_aichatbot_intent so merchants can tune Assist mode in
 * admin. Rows already present (identified by intent_code + language) are left untouched.
 */
class SeedIntents implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    public function __construct(ModuleDataSetupInterface $moduleDataSetup)
    {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('wb_aichatbot_intent');
        $existing = $connection->fetchCol(
            $connection->select()->from($table, 'intent_code')->where('language = ?', 'en')
        );
        foreach (IntentRouter::DEFAULTS as $intent => $entry) {
            if (in_array($intent, $existing, true)) {
                continue;
            }
            $connection->insert($table, [
                'intent_code' => $intent,
                'language' => 'en',
                'keywords' => $entry['keywords'],
                'priority' => (int)$entry['priority'],
                'is_enabled' => 1,
            ]);
        }
        return $this;
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
