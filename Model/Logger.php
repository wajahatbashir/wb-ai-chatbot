<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model;

use Psr\Log\LoggerInterface;

/**
 * Module logger writing to var/log/wb_aichatbot.log. debug() only writes when Developer > Debug Logging is on,
 * so prompts and provider payloads never land in the log by accident on production.
 */
class Logger
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Config
     */
    private $config;

    public function __construct(LoggerInterface $logger, Config $config)
    {
        $this->logger = $logger;
        $this->config = $config;
    }

    public function debug(string $message, array $context = []): void
    {
        if ($this->config->isDebugLog()) {
            $this->logger->debug($message, $context);
        }
    }

    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }
}
