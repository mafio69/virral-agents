<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Exception\AiConnectionException;

final class OpenAiProvider implements ChatProviderInterface, WebToolClientInterface
{
    private ?array $models = null;

    public function __construct(private readonly ProviderTransport $transport)
    {
    }
    public function isConfigured(): bool
    {
        return $this->transport->isConfigured();
    }
    public function id(): string
    {
        return 'openai';
    }
    public function name(): string
    {
        return 'OpenAI';
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
            if (!is_string($id) || !preg_match('/^[a-zA-Z0-9._-]{1,150}$/D', $id)) {
                throw new AiConnectionException('invalid_response');
            }
            // Responses supports current GPT and reasoning families, not media-only APIs.
            if (!preg_match('/^(?:gpt-(?:4o|4\.1|[5-9])|o[134](?:-|$))/', $id)
                || preg_match('/image|audio|realtime|transcrib|tts|search|deep-research|embedding/', $id)) {
                continue;
            }
            $models[$id] = ['id' => $id, 'name' => $id, 'supports_images' => str_starts_with($id, 'gpt-')];
        }
        ksort($models);
        return $this->models = array_values($models);
    }

    public function generate(array $messages, ?string $model = null): string
    {
        return $this->reply($messages, $model, false)->text;
    }

    public function generateWebReply(array $messages, string $model): \App\Dto\AI\AiReply
    {
        return $this->reply($messages, $model, true);
    }

    private function reply(array $messages, ?string $model, bool $web): \App\Dto\AI\AiReply
    {
        $input = [];
        foreach ($messages as $message) {
            $content = $message['content'];
            if (is_array($content)) {
                $content = array_map(static fn (array $part): array => match ($part['type']) {
                    'text' => ['type' => 'input_text', 'text' => $part['text']],
                    'image_url' => ['type' => 'input_image', 'image_url' => $part['image_url']['url']],
                    default => throw new AiConnectionException('invalid_response'),
                }, $content);
            }
            $input[] = ['role' => $message['role'], 'content' => $content];
        }
        $body = $this->transport->request('POST', 'responses', [
            'model' => $model ?? throw new AiConnectionException('model_unavailable'),
            'input' => $input, 'store' => false, 'max_output_tokens' => 4096,
            ...($web ? ['tools' => array_map(static fn (array $tool): array => ['type' => 'function', ...$tool], WebTools::definitions()), 'parallel_tool_calls' => false] : []),
        ]);
        if (isset($body['error'])) {
            throw new AiConnectionException('invalid_response');
        }
        $text = [];
        foreach ($body['output'] ?? [] as $item) {
            if ($web && ($item['type'] ?? '') === 'function_call') {
                return new \App\Dto\AI\AiReply('', webRequest: WebTools::request($item['name'] ?? '', $item['arguments'] ?? null));
            }
            if (($item['type'] ?? '') !== 'message') {
                continue;
            }
            foreach ($item['content'] ?? [] as $part) {
                if (($part['type'] ?? '') === 'output_text' && is_string($part['text'] ?? null)) {
                    $text[] = $part['text'];
                }
            }
        }
        $result = implode("\n", $text);
        if (trim($result) === '' || isset($body['error'])) {
            throw new AiConnectionException('invalid_response');
        }
        return new \App\Dto\AI\AiReply($result);
    }
}
