<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Exception\AiConnectionException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

final readonly class OpenAiCompatClient implements ToolCallingClientInterface, WebToolClientInterface
{
    private ClientInterface $httpClient;

    public function __construct(
        private string $baseUri,
        private string $apiKey,
        private string $defaultModel,
        int $timeout = 300,
        ?ClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => $this->getBaseUrl(),
            'timeout' => $timeout,
            'connect_timeout' => 10,
            'allow_redirects' => false,
        ]);
    }

    public function generate(array $messages, ?string $model = null): string
    {
        $response = $this->request('POST', 'chat/completions', [
            'model' => $model ?? $this->defaultModel,
            'messages' => $messages,
        ]);
        $body = $this->decode($response);
        $content = $body['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new AiConnectionException('invalid_response');
        }
        return $content;
    }

    public function generateWithTools(array $messages, array $tools, ?string $model = null): array
    {
        $body = $this->decode($this->request('POST', 'chat/completions', [
            'model' => $model ?? $this->defaultModel, 'messages' => $messages,
            'tools' => $tools, 'tool_choice' => $tools === [] ? 'none' : 'auto',
        ]));
        $message = $body['choices'][0]['message'] ?? null;
        if (!is_array($message) || ($message['role'] ?? 'assistant') !== 'assistant') throw new AiConnectionException('invalid_response');
        return $message;
    }

    public function generateWebReply(array $messages, string $model): \App\Dto\AI\AiReply
    {
        $message = $this->generateWithTools($messages, WebTools::compat(), $model);
        $call = $message['tool_calls'][0]['function'] ?? null;
        if ($call !== null) return new \App\Dto\AI\AiReply('', webRequest: WebTools::request($call['name'] ?? '', $call['arguments'] ?? null));
        $text = $message['content'] ?? null;
        if (!is_string($text) || trim($text) === '') throw new AiConnectionException('invalid_response');
        return new \App\Dto\AI\AiReply($text);
    }

    /** @return list<array<string, mixed>> */
    public function listModels(): array
    {
        $body = $this->decode($this->request('GET', 'models'));
        if (!isset($body['data']) || !is_array($body['data']) || !array_is_list($body['data'])) {
            throw new AiConnectionException('invalid_response');
        }
        foreach ($body['data'] as $entry) {
            if (!is_array($entry) || !isset($entry['id']) || !is_string($entry['id']) || trim($entry['id']) === '') {
                throw new AiConnectionException('invalid_response');
            }
        }
        return $body['data'];
    }

    private function request(string $method, string $path, ?array $payload = null): ResponseInterface
    {
        if (trim($this->apiKey) === '' || $this->apiKey === 'no-token-set' || str_starts_with($this->apiKey, 'encrypted:')) {
            throw new AiConnectionException('missing_configuration');
        }
        try {
            $response = $this->httpClient->request($method, $path, [
                'headers' => ['Authorization' => 'Bearer '.$this->apiKey, 'Accept' => 'application/json'],
                'allow_redirects' => false,
                'timeout' => $path === 'models' ? 30 : $this->httpClient->getConfig('timeout') ?? 300,
                ...($payload !== null ? ['json' => $payload] : []),
            ]);
        } catch (RequestException $error) {
            throw new AiConnectionException(($error->getHandlerContext()['errno'] ?? null) === 28 ? 'provider_timeout' : $this->statusReason($error->getResponse()?->getStatusCode() ?? 0));
        } catch (\GuzzleHttp\Exception\ConnectException $error) {
            throw new AiConnectionException(($error->getHandlerContext()['errno'] ?? null) === 28 ? 'provider_timeout' : 'connection_failed');
        } catch (GuzzleException) {
            throw new AiConnectionException('connection_failed');
        }
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new AiConnectionException($this->statusReason($response->getStatusCode()));
        }
        return $response;
    }

    private function statusReason(int $status): string
    {
        return match ($status) {
            401, 403 => 'provider_auth',
            404 => 'model_unavailable',
            429 => 'provider_limit',
            408, 504 => 'provider_timeout',
            default => 'connection_failed',
        };
    }

    private function decode(ResponseInterface $response): array
    {
        try {
            $body = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new AiConnectionException('invalid_response');
        }
        if (!is_array($body)) throw new AiConnectionException('invalid_response');
        return $body;
    }

    public function getBaseUrl(): string
    {
        return rtrim($this->baseUri, '/').'/';
    }
}
