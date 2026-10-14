<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Exception\AiConnectionException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

final readonly class ProviderTransport
{
    private ClientInterface $http;

    public function __construct(
        string $baseUri,
        #[\SensitiveParameter] private string $apiKey,
        private string $authHeader = 'Authorization',
        private string $authPrefix = 'Bearer ',
        private array $headers = [],
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client(['base_uri' => $baseUri, 'connect_timeout' => 10]);
    }

    public function request(string $method, string $path, ?array $payload = null, array $query = []): array
    {
        if (trim($this->apiKey) === '' || str_starts_with($this->apiKey, 'encrypted:')) {
            throw new AiConnectionException('missing_configuration');
        }
        try {
            $response = $this->http->request($method, $path, [
                'headers' => [...$this->headers, 'Accept' => 'application/json', $this->authHeader => $this->authPrefix.$this->apiKey],
                'allow_redirects' => false, 'timeout' => $method === 'GET' ? 30 : 120,
                'stream' => true, 'query' => $query,
                ...($payload !== null ? ['json' => $payload] : []),
            ]);
        } catch (RequestException $error) {
            throw new AiConnectionException($this->reason($error->getResponse()?->getStatusCode() ?? 0));
        } catch (GuzzleException) {
            throw new AiConnectionException('connection_failed');
        }
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new AiConnectionException($this->reason($response->getStatusCode()));
        }
        $stream = $response->getBody();
        try {
            $json = '';
            while (!$stream->eof() && strlen($json) <= 4194304) $json .= $stream->read(8192);
            if (strlen($json) > 4194304) throw new AiConnectionException('invalid_response');
            $body = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new AiConnectionException('invalid_response');
        } finally {
            $stream->close();
        }
        if (!is_array($body)) throw new AiConnectionException('invalid_response');
        return $body;
    }

    private function reason(int $status): string
    {
        return match ($status) {
            401, 403 => 'provider_auth',
            404 => 'model_unavailable',
            429 => 'provider_limit',
            default => 'connection_failed',
        };
    }
}
