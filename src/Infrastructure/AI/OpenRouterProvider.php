<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Dto\AI\AiReply;
use App\Exception\AiConnectionException;

/** OpenAI-compatible Chat Completions routed by OpenRouter to upstream model providers. */
final class OpenRouterProvider implements ChatProviderInterface, WebToolClientInterface
{
    public const BASE_URI = 'https://openrouter.ai/api/v1/';

    private ?array $models = null;

    /** @param list<string> $allowedModels Empty list exposes the whole text catalogue. */
    public function __construct(private readonly ProviderTransport $transport, private readonly array $allowedModels = [])
    {
    }

    public static function allowedModels(string $list): array
    {
        return array_values(array_unique(array_filter(array_map('trim', explode(',', $list)), static fn (string $id): bool => $id !== '')));
    }

    public function id(): string
    {
        return 'openrouter';
    }

    public function name(): string
    {
        return 'OpenRouter';
    }

    public function isConfigured(): bool
    {
        return $this->transport->isConfigured();
    }

    public function listModels(): array
    {
        if ($this->models !== null) {
            return $this->models;
        }
        $data = $this->transport->request('GET', 'models')['data'] ?? null;
        if (!is_array($data) || !array_is_list($data)) {
            throw new AiConnectionException('invalid_response');
        }
        $models = [];
        foreach ($data as $entry) {
            $id = $entry['id'] ?? null;
            // The public catalogue is third-party data: skip unusable entries instead of failing the whole list.
            if (!is_string($id) || !preg_match('/^[a-z0-9][a-z0-9._-]{0,63}\/[a-zA-Z0-9._:-]{1,150}$/D', $id)) {
                continue;
            }
            if ($this->allowedModels !== [] && !in_array($id, $this->allowedModels, true)) {
                continue;
            }
            $architecture = is_array($entry['architecture'] ?? null) ? $entry['architecture'] : [];
            $input = is_array($architecture['input_modalities'] ?? null) ? $architecture['input_modalities'] : ['text'];
            $output = is_array($architecture['output_modalities'] ?? null) ? $architecture['output_modalities'] : ['text'];
            if (!in_array('text', $output, true)) {
                continue;
            }
            $name = is_string($entry['name'] ?? null) && trim($entry['name']) !== '' ? mb_substr(trim($entry['name']), 0, 150) : $id;
            $parameters = is_array($entry['supported_parameters'] ?? null) ? $entry['supported_parameters'] : [];
            $models[$id] = ['id' => $id, 'name' => $name, 'supports_images' => in_array('image', $input, true), 'supports_tools' => in_array('tools', $parameters, true)];
        }
        ksort($models);
        return $this->models = array_values($models);
    }

    public function generate(array $messages, ?string $model = null): string
    {
        return $this->reply($messages, $model, false)->text;
    }

    public function generateWebReply(array $messages, string $model): AiReply
    {
        return $this->reply($messages, $model, $this->supportsTools($model));
    }

    private function supportsTools(string $model): bool
    {
        foreach ($this->listModels() as $entry) {
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
            // Route only to upstream providers that do not collect user data.
            'provider' => ['data_collection' => 'deny'],
            ...($web ? ['tools' => WebTools::compat(), 'parallel_tool_calls' => false] : []),
        ]);
        if (isset($body['error'])) {
            throw new AiConnectionException('invalid_response');
        }
        $message = $body['choices'][0]['message'] ?? null;
        if (!is_array($message)) {
            throw new AiConnectionException('invalid_response');
        }
        $call = $message['tool_calls'][0]['function'] ?? null;
        if ($web && is_array($call)) {
            return new AiReply('', webRequest: WebTools::request($call['name'] ?? '', $call['arguments'] ?? null));
        }
        $text = $message['content'] ?? null;
        if (!is_string($text) || trim($text) === '') {
            throw new AiConnectionException('invalid_response');
        }
        return new AiReply($text);
    }
}
