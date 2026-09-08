<?php
declare(strict_types=1);

namespace WB\AiChatbot\Model\Kb;

use WB\AiChatbot\Api\Data\CredentialInterface;
use WB\AiChatbot\Api\ProviderInterface;
use WB\AiChatbot\Model\Provider\NoProviderException;
use WB\AiChatbot\Model\Provider\Pool;
use WB\AiChatbot\Model\Provider\ProviderException;

/**
 * Embeds texts through the provider pool and remembers which model produced the vectors, so retrieval can
 * compare a query only against chunks embedded by the same model.
 */
class Embedder
{
    public const BATCH_SIZE = 64;

    /**
     * @var Pool
     */
    private $pool;

    /**
     * @var string|null
     */
    private $lastModel;

    public function __construct(Pool $pool)
    {
        $this->pool = $pool;
    }

    public function isAvailable(): bool
    {
        return $this->pool->getEmbeddingCandidates() !== [];
    }

    /**
     * Model name the first usable embedding credential would use, or null when embeddings are unavailable.
     */
    public function getActiveModel(): ?string
    {
        $candidates = $this->pool->getEmbeddingCandidates();
        if ($candidates === []) {
            return null;
        }
        return $this->resolveModel($this->pool->getProvider((string)$candidates[0]->getProviderCode()), $candidates[0]);
    }

    /**
     * @param string[] $texts
     * @return float[][] one vector per input text, in order
     * @throws NoProviderException|ProviderException
     */
    public function embed(array $texts): array
    {
        $texts = array_values($texts);
        if ($texts === []) {
            return [];
        }
        $vectors = [];
        foreach (array_chunk($texts, self::BATCH_SIZE) as $batch) {
            $result = $this->pool->execute(function (ProviderInterface $provider, CredentialInterface $credential) use ($batch) {
                $this->lastModel = $this->resolveModel($provider, $credential);
                return $provider->embed($credential, $batch);
            }, true);
            foreach ($result as $vector) {
                $vectors[] = $vector;
            }
        }
        return $vectors;
    }

    /**
     * Model used by the most recent embed() call.
     */
    public function getLastModel(): ?string
    {
        return $this->lastModel;
    }

    private function resolveModel(ProviderInterface $provider, CredentialInterface $credential): string
    {
        $model = trim((string)$credential->getEmbeddingModel());
        if ($model !== '') {
            return $model;
        }
        $defaults = array_keys($provider->getEmbeddingModels());
        return $defaults !== [] ? (string)$defaults[0] : $provider->getCode();
    }
}
