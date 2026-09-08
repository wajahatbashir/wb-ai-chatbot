<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Provider;

use Magento\Framework\HTTP\Client\CurlFactory;
use Magento\Framework\Serialize\Serializer\Json;
use WB\AiChatbot\Api\Data\CredentialInterface;
use WB\AiChatbot\Api\ProviderInterface;
use WB\AiChatbot\Model\Logger;

/**
 * Shared HTTP plumbing for the real vendors: JSON request/response, error normalisation, image helpers.
 */
abstract class AbstractHttpProvider implements ProviderInterface
{
    protected const TIMEOUT_CHAT = 90;
    protected const TIMEOUT_EMBED = 60;
    protected const TIMEOUT_HEALTH = 15;

    /**
     * @var CurlFactory
     */
    protected $curlFactory;

    /**
     * @var Json
     */
    protected $json;

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct(CurlFactory $curlFactory, Json $json, Logger $logger)
    {
        $this->curlFactory = $curlFactory;
        $this->json = $json;
        $this->logger = $logger;
    }

    public function supportsVision(): bool
    {
        return true;
    }

    /**
     * Sends a JSON request and returns the decoded body. Any non-2xx response becomes a ProviderException with a
     * normalised status so the pool can decide whether to fail over.
     *
     * @throws ProviderException
     */
    protected function request(string $method, string $url, array $headers, ?array $body, int $timeout): array
    {
        $curl = $this->curlFactory->create();
        $curl->setTimeout($timeout);
        $curl->setOption(CURLOPT_CONNECTTIMEOUT, 10);
        foreach ($headers as $name => $value) {
            $curl->addHeader($name, $value);
        }

        $this->logger->debug(sprintf('[%s] %s %s', $this->getCode(), $method, $url), ['body' => $body]);

        try {
            if ($method === 'GET') {
                $curl->get($url);
            } else {
                $curl->addHeader('Content-Type', 'application/json');
                $curl->post($url, $this->json->serialize($body ?? []));
            }
        } catch (\Throwable $e) {
            throw new ProviderException(
                CredentialInterface::STATUS_TRANSIENT_ERROR,
                sprintf('Connection error: %s', $e->getMessage()),
                null,
                $e
            );
        }

        $status = (int)$curl->getStatus();
        $raw = (string)$curl->getBody();
        $this->logger->debug(sprintf('[%s] HTTP %d', $this->getCode(), $status), ['response' => substr($raw, 0, 4000)]);

        if ($status < 200 || $status >= 300) {
            throw ProviderException::fromHttp($status, $raw);
        }

        try {
            $decoded = $this->json->unserialize($raw);
        } catch (\Throwable $e) {
            throw new ProviderException(CredentialInterface::STATUS_TRANSIENT_ERROR, 'Provider returned invalid JSON', $status, $e);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @throws ProviderException
     */
    protected function requireKey(CredentialInterface $credential): string
    {
        $key = trim($credential->getDecryptedApiKey());
        if ($key === '') {
            throw new ProviderException(CredentialInterface::STATUS_EMPTY_KEY, 'No API key configured');
        }
        return $key;
    }

    protected function modelOrDefault(CredentialInterface $credential): string
    {
        $model = trim((string)$credential->getModel());
        if ($model !== '') {
            return $model;
        }
        $defaults = array_keys($this->getChatModels());
        return (string)($defaults[0] ?? '');
    }

    protected function embeddingModelOrDefault(CredentialInterface $credential): string
    {
        $model = trim((string)$credential->getEmbeddingModel());
        if ($model !== '') {
            return $model;
        }
        $defaults = array_keys($this->getEmbeddingModels());
        return (string)($defaults[0] ?? '');
    }

    /**
     * Content is either a plain string or a list of parts: ['type' => 'text', 'text' => ...] or
     * ['type' => 'image', 'data' => base64, 'mime' => 'image/jpeg'].
     *
     * @param string|array $content
     */
    protected function contentToText($content): string
    {
        if (is_string($content)) {
            return $content;
        }
        $texts = [];
        foreach ((array)$content as $part) {
            if (is_array($part) && ($part['type'] ?? '') === 'text') {
                $texts[] = (string)($part['text'] ?? '');
            }
        }
        return implode("\n", $texts);
    }

    /**
     * @param string|array $content
     * @return array list of parts, always in the normalised shape
     */
    protected function contentToParts($content): array
    {
        if (is_string($content)) {
            return [['type' => 'text', 'text' => $content]];
        }
        return array_values(array_filter((array)$content, 'is_array'));
    }

    protected function decodeArguments($arguments): array
    {
        if (is_array($arguments)) {
            return $arguments;
        }
        if (is_string($arguments) && $arguments !== '') {
            try {
                $decoded = $this->json->unserialize($arguments);
                return is_array($decoded) ? $decoded : [];
            } catch (\Throwable $e) {
                return [];
            }
        }
        return [];
    }
}
