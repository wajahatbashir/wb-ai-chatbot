<?php
declare(strict_types=1);

namespace WB\AiChatbot\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WB\AiChatbot\Model\Kb\Indexer;
use WB\AiChatbot\Model\Kb\Sync\ProviderPool;
use WB\AiChatbot\Model\Kb\Sync\SyncDocument;
use WB\AiChatbot\Model\Kb\Synchronizer;

/**
 * bin/magento wb:aichatbot:sync [--type=product,cms_page] [--store=1] [--dry-run] [--limit=N] [--no-index] [--reembed]
 */
class SyncCommand extends Command
{
    /**
     * @var State
     */
    private $appState;

    /**
     * @var Synchronizer
     */
    private $synchronizer;

    /**
     * @var Indexer
     */
    private $indexer;

    /**
     * @var ProviderPool
     */
    private $providerPool;

    public function __construct(State $appState, Synchronizer $synchronizer, Indexer $indexer, ProviderPool $providerPool)
    {
        $this->appState = $appState;
        $this->synchronizer = $synchronizer;
        $this->indexer = $indexer;
        $this->providerPool = $providerPool;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('wb:aichatbot:sync')
            ->setDescription('Sync the AI chatbot knowledge base from the catalog, CMS pages and store information')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Comma-separated source types (default: all): ' . implode(', ', array_keys($this->providerPool->getAll())))
            ->addOption('store', 's', InputOption::VALUE_OPTIONAL, 'Comma-separated store view ids (default: all active)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would change without writing anything')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Stop after N documents per source/store (implies no stale cleanup)', 0)
            ->addOption('no-index', null, InputOption::VALUE_NONE, 'Only sync documents; leave chunking/embedding to the queue and cron')
            ->addOption('reembed', null, InputOption::VALUE_NONE, 'Re-chunk and re-embed every document, not only pending ones')
            ->addOption('verbose-docs', null, InputOption::VALUE_NONE, 'Print each document as it is processed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // already set
        }

        $types = array_filter(array_map('trim', explode(',', (string)$input->getOption('type'))));
        $stores = array_filter(array_map('intval', explode(',', (string)$input->getOption('store'))));
        $dryRun = (bool)$input->getOption('dry-run');
        $limit = (int)$input->getOption('limit');
        $verbose = (bool)$input->getOption('verbose-docs');

        $output->writeln(sprintf('<info>Knowledge base sync%s</info>', $dryRun ? ' (dry run)' : ''));
        $started = microtime(true);
        $progress = function (string $type, int $storeId, SyncDocument $document, string $action) use ($output, $verbose, $dryRun) {
            if ($verbose || ($dryRun && $action !== 'unchanged')) {
                $output->writeln(sprintf('  [%s] store %d %-9s %s - %s', $type, $storeId, $action, $document->getIdentifier(), mb_substr($document->getTitle(), 0, 60)));
                if ($dryRun && $verbose) {
                    $output->writeln('    ' . str_replace("\n", "\n    ", mb_substr($document->getBody(), 0, 1200)));
                }
            }
        };

        try {
            $stats = $this->synchronizer->sync($types, $stores, $dryRun, $limit, $progress);
        } catch (\Throwable $e) {
            $output->writeln('<error>Sync failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln(sprintf('%-12s %-6s %8s %8s %10s %8s', 'Source', 'Store', 'Created', 'Updated', 'Unchanged', 'Deleted'));
        foreach ($stats as $type => $byStore) {
            foreach ($byStore as $storeId => $s) {
                $output->writeln(sprintf('%-12s %-6d %8d %8d %10d %8d', $type, $storeId, $s['created'], $s['updated'], $s['unchanged'], $s['deleted']));
            }
        }

        if (!$dryRun && !$input->getOption('no-index')) {
            $output->writeln('');
            $output->writeln('<info>Indexing' . ($input->getOption('reembed') ? ' (re-embedding everything)' : ' pending documents') . '...</info>');
            $last = 0;
            $index = $this->indexer->indexPending(0, (bool)$input->getOption('reembed'), function (int $done, int $total) use ($output, &$last) {
                if ($done - $last >= 250 || $done === $total) {
                    $output->writeln(sprintf('  %d / %d', $done, $total));
                    $last = $done;
                }
            });
            $output->writeln(sprintf(
                'Indexed %d document(s), %d with embeddings, %d failed.',
                $index['indexed'],
                $index['embedded'],
                $index['failed']
            ));
        }

        $status = $this->indexer->getStatus();
        $output->writeln('');
        $output->writeln(sprintf(
            'Knowledge base: %d documents (%d indexed, %d pending, %d failed), %d chunks, %d embedded, embedding model: %s',
            $status['documents'],
            $status['indexed'],
            $status['pending'],
            $status['failed'],
            $status['chunks'],
            $status['embedded'],
            $status['embedding_model'] ?? 'none (keyword retrieval only)'
        ));
        $output->writeln(sprintf('Done in %.1fs', microtime(true) - $started));
        return Command::SUCCESS;
    }
}
