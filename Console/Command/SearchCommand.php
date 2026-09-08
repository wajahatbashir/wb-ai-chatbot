<?php
declare(strict_types=1);

namespace WB\AiChatbot\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WB\AiChatbot\Model\Kb\Retriever;

/**
 * bin/magento wb:aichatbot:search "red kurta under 5000" [--store=1] [--limit=6] [--type=product] [--keyword-only]
 */
class SearchCommand extends Command
{
    /**
     * @var State
     */
    private $appState;

    /**
     * @var Retriever
     */
    private $retriever;

    public function __construct(State $appState, Retriever $retriever)
    {
        $this->appState = $appState;
        $this->retriever = $retriever;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('wb:aichatbot:search')
            ->setDescription('Test knowledge base retrieval for a question')
            ->addArgument('query', InputArgument::REQUIRED, 'The question or search phrase')
            ->addOption('store', 's', InputOption::VALUE_OPTIONAL, 'Store view id', 1)
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Results', 6)
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Comma-separated source types')
            ->addOption('keyword-only', null, InputOption::VALUE_NONE, 'Skip embeddings even when available');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // already set
        }
        $types = array_filter(array_map('trim', explode(',', (string)$input->getOption('type'))));
        $started = microtime(true);
        $results = $this->retriever->search(
            (string)$input->getArgument('query'),
            (int)$input->getOption('store'),
            (int)$input->getOption('limit'),
            $types,
            !$input->getOption('keyword-only')
        );
        $output->writeln(sprintf('<info>%d result(s) in %.0f ms</info>', count($results), (microtime(true) - $started) * 1000));
        foreach ($results as $i => $row) {
            $output->writeln(sprintf(
                "\n%d. <comment>%s</comment> [%s #%d] score=%.3f keyword=%.3f vector=%s",
                $i + 1,
                $row['title'],
                $row['source_type'],
                $row['document_id'],
                $row['score'],
                $row['keyword_score'],
                $row['vector_score'] === null ? '-' : sprintf('%.3f', $row['vector_score'])
            ));
            if ($row['url']) {
                $output->writeln('   ' . $row['url']);
            }
            $output->writeln('   ' . str_replace("\n", "\n   ", mb_substr($row['text'], 0, 400)) . (mb_strlen($row['text']) > 400 ? '...' : ''));
        }
        return Command::SUCCESS;
    }
}
