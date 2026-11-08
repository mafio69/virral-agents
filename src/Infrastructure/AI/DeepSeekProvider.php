<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Dto\AI\AiReply;
use App\Exception\AiConnectionException;

/** OpenAI-compatible Chat Completions served directly by DeepSeek (api.deepseek.com). */
final class DeepSeekProvider implements ChatProviderInterface, WebToolClientInterface
{
    public const BASE_URI = 'https://api.deepseek.com/';

    /** DeepSeek publishes no capability metadata per model; the catalogue is fixed to the documented models. */
    private const MODELS = [
        ['id' => 'deepseek-flash', 'name' => 'DeepSeek Flash', 'supports_images' => false, 'supports_tools' => true],
        ['id' => 'deepseek-v4-pro', 'name' => 'DeepSeek v4 Pro', 'supports_images' => false, 'supports_tools' => true],
    ];

    public function __construct(private readonly ProviderTransport $transport)
    {
    }

    public function id(): string
    {
        return 'deepseek';
    }

    public function name(): string
    {
        return 'DeepSeek';
    }

    public function isConfigured(): bool
    {
        return $this->transport->isConfigured();
    }

    public function listModels(): array
    {
        return array_values(self::MODELS);
    }

    public function generate(array $messages, ?string $model = null): string
    {
        return $this->reply($messages, $model, false)->text;
    }

    /** Plain reply with token usage, e.g. the answer after research sources were collected. */
    public function generateReply(array $messages, ?string $model = null): AiReply
    {
        return $this->reply($messages, $model, false);
    }

    public function generateWebReply(array $messages, string $model): AiReply
    {
        return $this->reply($messages, $model, $this->supportsTools($model));
    }

    private function supportsTools(string $model): bool
    {
        foreach (self::MODELS as $entry) {
            if ($entry['id'] === $model) {
                return $entry['supports_tools'];
            }
        }
        return false;
    }

    private function reply(array $messages, ?string $model, bool $web): AiReply
    {
        $body = $this->transport->request('POST', 'chat/completions', [
            'model' => $model ?? throw new AiConnectionException('model_unavailable'),
            'messages' => $messages, 'max_tokens' => 4096,
            ...($web ? ['tools' => WebTools::compat(), 'parallel_tool_calls' => false] : []),
        ]);
        if (isset($body['error'])) {
            throw new AiConnectionException('invalid_response');
        }
        $message = $body['choices'][0]['message'] ?? null;
        if (!is_array($message)) {
            throw new AiConnectionException('invalid_response');
        }
        $usage = is_array($body['usage'] ?? null) ? $body['usage'] : null;
        $call = $message['tool_calls'][0]['function'] ?? null;
        if ($web && is_array($call)) {
            return new AiReply('', webRequest: WebTools::request($call['name'] ?? '', $call['arguments'] ?? null), usage: $usage);
        }
        $text = $message['content'] ?? null;
        if (!is_string($text) || trim($text) === '') {
            throw EmptyReply::fromChatCompletion($body);
        }
        return new AiReply($text, usage: $usage);
    }
}
