<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Exception\AiConnectionException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
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

    public function request(string $method, string $path, ?array $payload = null, array $query = [], int $maxResponseBytes = 4194304): array
    {
        if (trim($this->apiKey) === '' || str_starts_with($this->apiKey, 'encrypted:')) {
            throw new AiConnectionException('missing_configuration');
        }
        try {
            $response = $this->http->request($method, $path, [
                'headers' => [...$this->headers, 'Accept' => 'application/json', $this->authHeader => $this->authPrefix.$this->apiKey],
                'allow_redirects' => false, 'timeout' => $method === 'GET' ? 30 : 300,
                'stream' => true, 'query' => $query,
                ...($payload !== null ? ['json' => $payload] : []),
            ]);
        } catch (RequestException $error) {
            throw new AiConnectionException($this->isTimeout($error->getHandlerContext()) ? 'provider_timeout' : $this->reason($error->getResponse()?->getStatusCode() ?? 0));
        } catch (ConnectException $error) {
            throw new AiConnectionException($this->isTimeout($error->getHandlerContext()) ? 'provider_timeout' : 'connection_failed');
        } catch (GuzzleException) {
            throw new AiConnectionException('connection_failed');
        }
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new AiConnectionException($this->reason($response->getStatusCode()));
        }
        $stream = $response->getBody();
        try {
            $json = '';
            while (!$stream->eof() && strlen($json) <= $maxResponseBytes) $json .= $stream->read(8192);
            if (strlen($json) > $maxResponseBytes) throw new AiConnectionException('invalid_response');
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
            402, 429 => 'provider_limit',
            408, 504 => 'provider_timeout',
            default => 'connection_failed',
        };
    }

    private function isTimeout(array $handlerContext): bool
    {
        // cURL error 28: operation timed out.
        return ($handlerContext['errno'] ?? null) === 28;
    }
}
