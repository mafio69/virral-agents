<?php

declare(strict_types=1);

namespace Tests\Observability;

use App\Observability\LangfuseTrace;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class LangfuseTraceTest extends TestCase
{
    public function testDisabledTraceDoesNotMakeHttpRequests(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->never())->method('request');

        $trace = new LangfuseTrace(
            http: $http,
            host: 'https://cloud.langfuse.com',
            publicKey: 'pk-test',
            secretKey: 'sk-test',
            enabled: false,
        );

        $trace->span('chat-1', 1, 'model-test', [], ['prompt_tokens' => 10, 'completion_tokens' => 20], 100, 200);
    }

    public function testMissingCredentialsDoesNotMakeHttpRequests(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->never())->method('request');

        $trace = new LangfuseTrace(
            http: $http,
            host: 'https://cloud.langfuse.com',
            publicKey: '',
            secretKey: '',
            enabled: true,
        );

        $trace->span('chat-1', 1, 'model-test', [], ['prompt_tokens' => 10, 'completion_tokens' => 20], 100, 200);
    }

    public function testSuccessfulSpanPayloadAndHeaders(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $capturedUrl = null;
        $capturedOptions = null;

        $http->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (string $method, string $uri, array $options) use (&$capturedUrl, &$capturedOptions) {
                $capturedUrl = $uri;
                $capturedOptions = $options;
                return new Response(200, [], json_encode(['status' => 'ok']));
            });

        $trace = new LangfuseTrace(
            http: $http,
            host: 'https://cloud.langfuse.com/',
            publicKey: 'pk-test',
            secretKey: 'sk-test',
            enabled: true,
        );

        $messages = [
            ['role' => 'user', 'content' => 'Pytanie testowe'],
        ];

        $trace->span(
            sessionId: '42',
            turn: 2,
            model: 'openrouter/qwen',
            messages: $messages,
            usage: ['prompt_tokens' => 15, 'completion_tokens' => 45],
            startNs: 1_000_000_000,
            endNs: 2_000_000_000,
            extra: ['system_chars' => 120, 'tool_result_chars' => 300],
        );

        $this->assertSame('https://cloud.langfuse.com/api/public/otel/v1/traces', $capturedUrl);
        $this->assertIsArray($capturedOptions);
        $this->assertSame(3, $capturedOptions['timeout']);
        $this->assertSame(2, $capturedOptions['connect_timeout']);
        $this->assertSame('Basic '.base64_encode('pk-test:sk-test'), $capturedOptions['headers']['Authorization']);
        $this->assertSame('4', $capturedOptions['headers']['x-langfuse-ingestion-version']);

        $payload = json_decode((string) $capturedOptions['body'], true);
        $this->assertIsArray($payload);
        $span = $payload['resourceSpans'][0]['scopeSpans'][0]['spans'][0];

        $this->assertSame('chat-turn-2', $span['name']);
        $this->assertSame('1000000000', $span['startTimeUnixNano']);
        $this->assertSame('2000000000', $span['endTimeUnixNano']);

        $attrMap = [];
        foreach ($span['attributes'] as $attr) {
            $attrMap[$attr['key']] = $attr['value']['intValue'] ?? $attr['value']['stringValue'];
        }

        $this->assertSame('42', $attrMap['session.id']);
        $this->assertSame('openrouter/qwen', $attrMap['gen_ai.request.model']);
        $this->assertSame('15', $attrMap['gen_ai.usage.input_tokens']);
        $this->assertSame('45', $attrMap['gen_ai.usage.output_tokens']);
        $this->assertSame('2', $attrMap['chat.turn']);
        $this->assertSame('1', $attrMap['chat.history_messages']);
        $this->assertSame('120', $attrMap['chat.system_chars']);
        $this->assertSame('300', $attrMap['chat.tool_result_chars']);
    }

    public function testAlternativeTokenKeysFromAnthropicOrOpenAiResponses(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $capturedOptions = null;

        $http->expects($this->once())
            ->method('request')
            ->willReturnCallback(function (string $method, string $uri, array $options) use (&$capturedOptions) {
                $capturedOptions = $options;
                return new Response(200);
            });

        $trace = new LangfuseTrace(
            http: $http,
            host: 'https://cloud.langfuse.com',
            publicKey: 'pk-test',
            secretKey: 'sk-test',
            enabled: true,
        );

        $trace->span('1', 1, 'claude', [], ['input_tokens' => 100, 'output_tokens' => 200], 1, 2);

        $payload = json_decode((string) $capturedOptions['body'], true);
        $span = $payload['resourceSpans'][0]['scopeSpans'][0]['spans'][0];

        $attrMap = [];
        foreach ($span['attributes'] as $attr) {
            $attrMap[$attr['key']] = $attr['value']['intValue'] ?? $attr['value']['stringValue'];
        }

        $this->assertSame('100', $attrMap['gen_ai.usage.input_tokens']);
        $this->assertSame('200', $attrMap['gen_ai.usage.output_tokens']);
    }

    public function testConnectionFailureDoesNotThrowExceptionAndLogsWarning(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $http->expects($this->once())
            ->method('request')
            ->willThrowException(new ConnectException('Network down', new Request('POST', 'http://test')));

        $logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Langfuse error: Network down'));

        $trace = new LangfuseTrace(
            http: $http,
            logger: $logger,
            host: 'https://cloud.langfuse.com',
            publicKey: 'pk-test',
            secretKey: 'sk-test',
            enabled: true,
        );

        // Nie powinno rzucić wyjątku
        $trace->span('1', 1, 'model', [], null, 1, 2);
    }
}
