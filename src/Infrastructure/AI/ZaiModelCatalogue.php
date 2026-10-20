<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Exception\AiConnectionException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Throwable;

final readonly class ZaiModelCatalogue implements ModelCatalogueInterface
{
    private ClientInterface $httpClient;

    public function __construct(?ClientInterface $httpClient = null)
    {
        // Public specification: no API key, chat history or application configuration is sent.
        $this->httpClient = $httpClient ?? new Client();
    }

    public function listModels(): array
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
}
