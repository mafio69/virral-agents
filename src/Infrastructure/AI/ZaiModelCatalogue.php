<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Exception\AiConnectionException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Throwable;

final class ZaiModelCatalogue implements ModelCatalogueInterface
{
    private const DEFAULT_TTL_SECONDS = 3600;

    private readonly ClientInterface $httpClient;
    /** @var list<array{id: string, supports_images: bool}>|null */
    private ?array $models = null;

    public function __construct(
        ?ClientInterface $httpClient = null,
        private readonly ?string $cachePath = null,
        private readonly int $cacheTtlSeconds = self::DEFAULT_TTL_SECONDS,
    ) {
        // Public specification: no API key, chat history or application configuration is sent.
        $this->httpClient = $httpClient ?? new Client();
    }

    public function listModels(): array
    {
        if ($this->models !== null) {
            return $this->models;
        }
        $freshCache = $this->readCache(allowStale: false);
        if ($freshCache !== null) {
            return $this->models = $freshCache;
        }
        try {
            $fetched = $this->fetchRemote();
            $this->writeCache($fetched);
            return $this->models = $fetched;
        } catch (AiConnectionException $error) {
            $staleCache = $this->readCache(allowStale: true);
            if ($staleCache !== null) {
                return $this->models = $staleCache;
            }
            throw $error;
        }
    }

    /** @return list<array{id: string, supports_images: bool}> */
    private function fetchRemote(): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://docs.z.ai/openapi.json', [
                'headers' => ['Accept' => 'application/json'],
                'allow_redirects' => false,
                'timeout' => 20,
                'connect_timeout' => 5,
                'stream' => true,
            ]);
            if ($response->getStatusCode() !== 200) {
                throw new AiConnectionException('catalogue_unavailable');
            }
            $stream = $response->getBody();
            try {
                $json = '';
                while (!$stream->eof() && strlen($json) <= 2097152) {
                    $json .= $stream->read(8192);
                }
                if (strlen($json) > 2097152) {
                    throw new AiConnectionException('invalid_response');
                }
            } finally {
                $stream->close();
            }
            $spec = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
            $schemas = $spec['components']['schemas'] ?? [];
            $models = [];
            foreach (['ChatCompletionTextRequest' => false, 'ChatCompletionVisionRequest' => true] as $schema => $vision) {
                $ids = $schemas[$schema]['properties']['model']['enum'] ?? null;
                if (!is_array($ids) || !array_is_list($ids) || count($ids) > 200) {
                    throw new AiConnectionException('invalid_response');
                }
                foreach ($ids as $id) {
                    if (!is_string($id) || !preg_match('/^glm-[a-z0-9.-]{1,100}$/D', $id)) {
                        throw new AiConnectionException('invalid_response');
                    }
                    $models[$id] = ['id' => $id, 'supports_images' => $vision];
                }
            }
            if ($models === []) {
                throw new AiConnectionException('invalid_response');
            }
            return array_values($models);
        } catch (AiConnectionException $error) {
            throw $error;
        } catch (Throwable) {
            // No vendor response body, URL parameters or original exception escapes.
            throw new AiConnectionException('catalogue_unavailable');
        }
    }

    /** @return list<array{id: string, supports_images: bool}>|null */
    private function readCache(bool $allowStale): ?array
    {
        if ($this->cachePath === null || !is_file($this->cachePath)) {
            return null;
        }
        $raw = @file_get_contents($this->cachePath);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        try {
            $decoded = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($decoded) || !isset($decoded['cached_at'], $decoded['models']) || !is_int($decoded['cached_at']) || !is_array($decoded['models']) || !array_is_list($decoded['models'])) {
            return null;
        }
        $age = time() - $decoded['cached_at'];
        if (!$allowStale && ($age < 0 || $age > $this->cacheTtlSeconds)) {
            return null;
        }
        $validated = [];
        foreach ($decoded['models'] as $entry) {
            if (!is_array($entry) || !is_string($entry['id'] ?? null) || !is_bool($entry['supports_images'] ?? null)) {
                return null;
            }
            if (!preg_match('/^glm-[a-z0-9.-]{1,100}$/D', $entry['id'])) {
                return null;
            }
            $validated[] = ['id' => $entry['id'], 'supports_images' => $entry['supports_images']];
        }
        return $validated !== [] ? $validated : null;
    }

    /** @param list<array{id: string, supports_images: bool}> $models */
    private function writeCache(array $models): void
    {
        if ($this->cachePath === null) {
            return;
        }
        $dir = dirname($this->cachePath);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }
        $payload = json_encode(['cached_at' => time(), 'models' => $models], JSON_UNESCAPED_SLASHES);
        if (!is_string($payload)) {
            return;
        }
        $tmp = $this->cachePath.'.tmp.'.getmypid();
        if (@file_put_contents($tmp, $payload, LOCK_EX) !== false) {
            @rename($tmp, $this->cachePath);
        }
    }
}
