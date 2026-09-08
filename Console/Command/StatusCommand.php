<?php
declare(strict_types=1);

namespace WB\AiChatbot\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WB\AiChatbot\Api\CredentialRepositoryInterface;
use WB\AiChatbot\Model\Config;
use WB\AiChatbot\Model\Provider\HealthChecker;
use WB\AiChatbot\Model\Provider\Pool;

/**
 * bin/magento wb:aichatbot:status [--check]
 */
class StatusCommand extends Command
{
    /**
     * @var CredentialRepositoryInterface
     */
    private $credentialRepository;

    /**
     * @var HealthChecker
     */
    private $healthChecker;

    /**
     * @var Pool
     */
    private $pool;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var State
     */
    private $appState;

    public function __construct(
        CredentialRepositoryInterface $credentialRepository,
        HealthChecker $healthChecker,
        Pool $pool,
        Config $config,
        State $appState
    ) {
        $this->credentialRepository = $credentialRepository;
        $this->healthChecker = $healthChecker;
        $this->pool = $pool;
        $this->config = $config;
        $this->appState = $appState;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('wb:aichatbot:status')
            ->setDescription('Shows the AI Chatbot engine mode and every provider credential with its health status')
            ->addOption('check', null, InputOption::VALUE_NONE, 'Re-run the connection check on every enabled credential first');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // area already set
        }

        $credentials = $this->credentialRepository->getEnabledOrdered();
        if ($input->getOption('check')) {
            foreach ($credentials as $credential) {
                $this->healthChecker->check($credential);
            }
        }

        $table = new Table($output);
        $table->setHeaders(['Order', 'Alias', 'Provider', 'Model', 'Status', 'Message', 'Last checked']);
        foreach ($credentials as $credential) {
            $table->addRow([
                $credential->getSortOrder(),
                $credential->getAlias(),
                $credential->getProviderCode(),
                $credential->getModel() ?: '(default)',
                $credential->getStatus(),
                mb_substr((string)$credential->getStatusMessage(), 0, 60),
                (string)($credential->getLastCheckedAt() ?: 'never'),
            ]);
        }
        $table->render();

        $output->writeln(sprintf('Configured engine mode: <info>%s</info>', $this->config->getMode()));
        $output->writeln(sprintf(
            'AI currently available: <info>%s</info> (%d usable credential(s); otherwise the chatbot runs in Assist mode)',
            $this->pool->isAiAvailable() ? 'yes' : 'no',
            count($this->pool->getChatCandidates())
        ));

        return Command::SUCCESS;
    }
}
