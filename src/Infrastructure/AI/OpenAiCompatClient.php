<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Dto\AI\AiReply;
use App\Exception\AiConnectionException;
use Closure;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

final readonly class OpenAiCompatClient implements MultimodalClientInterface, ToolCallingClientInterface, WebToolClientInterface
{
    public const MIN_MAX_TOKENS = 8192;
    private const MAX_RATE_LIMIT_RETRIES = 2;
    private const MAX_RETRY_AFTER_MS = 5000;
    private const DEFAULT_BACKOFF_MS = [1000, 2000];

    private ClientInterface $httpClient;
    private int $maxTokens;
    private Closure $sleeper;

    /** @param (Closure(int): void)|null $sleeper */
    public function __construct(
        private string $baseUri,
        private string $apiKey,
        private string $defaultModel,
        int $timeout = 300,
        ?ClientInterface $httpClient = null,
        int $maxTokens = self::MIN_MAX_TOKENS,
        ?Closure $sleeper = null,
    ) {
        $this->maxTokens = max(self::MIN_MAX_TOKENS, $maxTokens);
        $this->sleeper = $sleeper ?? static function (int $ms): void {
            usleep($ms * 1000);
        };
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => $this->getBaseUrl(),
            'timeout' => $timeout,
            'connect_timeout' => 10,
            'allow_redirects' => false,
        ]);
    }

    public function generate(array $messages, ?string $model = null): string
    {
        return $this->generateReply($messages, $model)->text;
    }

    public function generateReply(array $messages, ?string $model = null): AiReply
    {
        $response = $this->request('POST', 'chat/completions', [
            'model' => $model ?? $this->defaultModel,
            'messages' => $messages,
            'max_tokens' => $this->maxTokens,
        ]);
        $body = $this->decode($response);
        $content = $body['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            throw new AiConnectionException('invalid_response');
        }
        return new AiReply($content, usage: $this->extractUsage($body));
    }

    public function generateWithTools(array $messages, array $tools, ?string $model = null): array
    {
        $body = $this->requestToolsBody($messages, $tools, $model);
        return $this->extractAssistantMessage($body);
    }

    public function generateWebReply(array $messages, string $model): AiReply
    {
        $body = $this->requestToolsBody($messages, WebTools::compat(), $model);
        $message = $this->extractAssistantMessage($body);
        $usage = $this->extractUsage($body);
        $call = $message['tool_calls'][0]['function'] ?? null;
        if ($call !== null) {
            return new AiReply('', webRequest: WebTools::request($call['name'] ?? '', $call['arguments'] ?? null), usage: $usage);
        }
        $text = $message['content'] ?? null;
        if (!is_string($text) || trim($text) === '') {
            throw new AiConnectionException('invalid_response');
        }
        return new AiReply($text, usage: $usage);
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

    public function isConfigured(): bool
    {
        return trim($this->apiKey) !== '' && $this->apiKey !== 'no-token-set' && !str_starts_with($this->apiKey, 'encrypted:');
    }

    private function requestToolsBody(array $messages, array $tools, ?string $model = null): array
    {
        return $this->decode($this->request('POST', 'chat/completions', [
            'model' => $model ?? $this->defaultModel,
            'messages' => $messages,
            'max_tokens' => $this->maxTokens,
            'tools' => $tools,
            'tool_choice' => $tools === [] ? 'none' : 'auto',
        ]));
    }

    /** @return array<string, mixed> */
    private function extractAssistantMessage(array $body): array
    {
        $message = $body['choices'][0]['message'] ?? null;
        if (!is_array($message) || ($message['role'] ?? 'assistant') !== 'assistant') {
            throw new AiConnectionException('invalid_response');
        }
        return $message;
    }

    /** @return array<string, mixed>|null */
    private function extractUsage(array $body): ?array
    {
        return is_array($body['usage'] ?? null) ? $body['usage'] : null;
    }

    private function request(string $method, string $path, ?array $payload = null): ResponseInterface
    {
        if (!$this->isConfigured()) {
            throw new AiConnectionException('missing_configuration');
        }
        $options = [
            'headers' => ['Authorization' => 'Bearer '.$this->apiKey, 'Accept' => 'application/json'],
            'allow_redirects' => false,
            'timeout' => $path === 'models' ? 30 : $this->httpClient->getConfig('timeout') ?? 300,
            ...($payload !== null ? ['json' => $payload] : []),
        ];
        for ($attempt = 0; $attempt <= self::MAX_RATE_LIMIT_RETRIES; $attempt++) {
            try {
                $response = $this->httpClient->request($method, $path, $options);
            } catch (RequestException $error) {
                if (($error->getHandlerContext()['errno'] ?? null) === 28) {
                    throw new AiConnectionException('provider_timeout');
                }
                $response = $error->getResponse();
                $status = $response?->getStatusCode() ?? 0;
                if ($this->shouldRetryRateLimit($status, $attempt, $response)) {
                    ($this->sleeper)($this->retryDelayMs($response, $attempt));
                    continue;
                }
                throw new AiConnectionException($this->statusReason($status));
            } catch (\GuzzleHttp\Exception\ConnectException $error) {
                throw new AiConnectionException(($error->getHandlerContext()['errno'] ?? null) === 28 ? 'provider_timeout' : 'connection_failed');
            } catch (GuzzleException) {
                throw new AiConnectionException('connection_failed');
            }
            $status = $response->getStatusCode();
            if ($this->shouldRetryRateLimit($status, $attempt, $response)) {
                ($this->sleeper)($this->retryDelayMs($response, $attempt));
                continue;
            }
            if ($status < 200 || $status >= 300) {
                throw new AiConnectionException($this->statusReason($status));
            }
            return $response;
        }
        throw new AiConnectionException('provider_limit');
    }

    private function shouldRetryRateLimit(int $status, int $attempt, ?ResponseInterface $response): bool
    {
        if ($status !== 429 || $attempt >= self::MAX_RATE_LIMIT_RETRIES) {
            return false;
        }
        return !$this->isNonRetryableQuotaError($response);
    }

    private function isNonRetryableQuotaError(?ResponseInterface $response): bool
    {
        if ($response === null) {
            return false;
        }
        try {
            $decoded = json_decode((string)$response->getBody(), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }
        if (!is_array($decoded)) {
            return false;
        }
        $code = $decoded['error']['code'] ?? $decoded['code'] ?? '';
        // Z.ai 1113 means insufficient balance / missing resource package; retrying cannot succeed.
        return is_scalar($code) && (string)$code === '1113';
    }

    private function retryDelayMs(?ResponseInterface $response, int $attempt): int
    {
        $header = trim((string)($response?->getHeaderLine('Retry-After') ?? ''));
        if ($header !== '' && ctype_digit($header)) {
            $seconds = (int)$header;
            if ($seconds > 0) {
                return min($seconds * 1000, self::MAX_RETRY_AFTER_MS);
            }
        }
        return self::DEFAULT_BACKOFF_MS[$attempt] ?? 2000;
    }

    private function statusReason(int $status): string
    {
        return match ($status) {
            401, 403 => 'provider_auth',
            404 => 'model_unavailable',
            402, 429 => 'provider_limit',
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
        if (!is_array($body)) {
            throw new AiConnectionException('invalid_response');
        }
        return $body;
    }

    public function getBaseUrl(): string
    {
        return rtrim($this->baseUri, '/').'/';
    }
}
