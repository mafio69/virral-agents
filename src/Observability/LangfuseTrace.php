<?php

declare(strict_types=1);

namespace App\Observability;

use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

final class LangfuseTrace
{
    public function __construct(
        private ?ClientInterface $http = null,
        private ?LoggerInterface $logger = null,
        private ?string $host = null,
        private ?string $publicKey = null,
        private ?string $secretKey = null,
        private bool $enabled = false,
    ) {
    }

    /**
     * @param list<array{role: string, content: mixed}> $messages
     * @param array<string, mixed>|null $usage
     * @param array<string, mixed> $extra
     */
    public function span(
        string $sessionId,
        int $turn,
        string $model,
        array $messages,
        ?array $usage,
        int $startNs,
        int $endNs,
        array $extra = []
    ): void {
        if (!$this->enabled) {
            return;
        }

        $host = rtrim((string) $this->host, '/');
        $pk = (string) $this->publicKey;
        $sk = (string) $this->secretKey;

        if ($host === '' || $pk === '' || $sk === '') {
            return;
        }

        try {
            $inputTokens = (int) ($usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0);
            $outputTokens = (int) ($usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0);

            $attr = static fn (string $k, mixed $v): array => [
                'key' => $k,
                'value' => is_int($v) ? ['intValue' => (string) $v] : ['stringValue' => (string) $v],
            ];

            $attrs = [
                $attr('session.id', $sessionId),
                $attr('gen_ai.request.model', $model),
                $attr('gen_ai.usage.input_tokens', $inputTokens),
                $attr('gen_ai.usage.output_tokens', $outputTokens),
                $attr('chat.turn', $turn),
                $attr('chat.history_messages', count($messages)),
                $attr('chat.history_chars', strlen((string) json_encode($messages, JSON_UNESCAPED_UNICODE))),
            ];

            foreach ($extra as $k => $v) {
                $attrs[] = $attr('chat.'.$k, is_int($v) ? $v : (string) $v);
            }

            $payload = [
                'resourceSpans' => [[
                    'resource' => [
                        'attributes' => [$attr('service.name', 'virral-agents')],
                    ],
                    'scopeSpans' => [[
                        'spans' => [[
                            'traceId' => bin2hex(random_bytes(16)),
                            'spanId' => bin2hex(random_bytes(8)),
                            'name' => 'chat-turn-'.$turn,
                            'kind' => 3,
                            'startTimeUnixNano' => (string) $startNs,
                            'endTimeUnixNano' => (string) $endNs,
                            'attributes' => $attrs,
                        ]],
                    ]],
                ]],
            ];

            $client = $this->http ?? new \GuzzleHttp\Client();
            $response = $client->request('POST', $host.'/api/public/otel/v1/traces', [
                'timeout' => 3,
                'connect_timeout' => 2,
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode($pk.':'.$sk),
                    'Content-Type' => 'application/json',
                    'x-langfuse-ingestion-version' => '4',
                ],
                'body' => json_encode($payload, JSON_THROW_ON_ERROR),
            ]);

            $code = $response->getStatusCode();
            if ($code >= 300) {
                $this->logger?->warning("Langfuse HTTP $code", [
                    'response' => substr((string) $response->getBody(), 0, 200),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger?->warning('Langfuse error: '.$e->getMessage());
        }
    }
}
