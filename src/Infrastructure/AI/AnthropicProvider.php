<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Exception\AiConnectionException;

final class AnthropicProvider implements ChatProviderInterface
{
    private ?array $models = null;

    public function __construct(private readonly ProviderTransport $transport) {}
    public function id(): string { return 'anthropic'; }
    public function name(): string { return 'Anthropic / Claude'; }

    public function listModels(): array
    {
        if ($this->models !== null) return $this->models;
        $models = [];
        $cursor = null;
        $seen = [];
        for ($page = 0; $page < 20; $page++) {
            $body = $this->transport->request('GET', 'models', query: ['limit' => 100, ...($cursor !== null ? ['after_id' => $cursor] : [])]);
            $data = $body['data'] ?? null;
            if (!is_array($data) || !array_is_list($data)) throw new AiConnectionException('invalid_response');
            foreach ($data as $entry) {
                $id = is_array($entry) ? ($entry['id'] ?? null) : null;
                if (!is_string($id) || !preg_match('/^claude-[a-zA-Z0-9._-]{1,150}$/D', $id)) throw new AiConnectionException('invalid_response');
                if (($entry['lifecycle'] ?? '') === 'retired') continue;
                $models[$id] = ['id' => $id, 'name' => is_string($entry['display_name'] ?? null) ? $entry['display_name'] : $id,
                    'supports_images' => ($entry['capabilities']['image_input']['supported'] ?? (bool)preg_match('/^claude-(?:3|[4-9]|opus|sonnet|haiku|fable)/', $id)) === true];
            }
            if (($body['has_more'] ?? false) === false) return $this->models = array_values($models);
            $cursor = $body['last_id'] ?? null;
            if (!is_string($cursor) || !preg_match('/^[a-zA-Z0-9._-]{1,200}$/D', $cursor) || isset($seen[$cursor])) {
                throw new AiConnectionException('invalid_response');
            }
            $seen[$cursor] = true;
        }
        throw new AiConnectionException('invalid_response');
    }

    public function generate(array $messages, ?string $model = null): string
    {
        $system = [];
        $conversation = [];
        foreach ($messages as $message) {
            $content = is_string($message['content']) ? [['type' => 'text', 'text' => $message['content']]]
                : array_map(static function (array $part): array {
                    if ($part['type'] === 'text') return ['type' => 'text', 'text' => $part['text']];
                    if ($part['type'] !== 'image_url') throw new AiConnectionException('invalid_response');
                    $image = InlineImage::parse($part['image_url']['url']);
                    return ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $image['mime_type'], 'data' => $image['data']]];
                }, $message['content']);
            if (in_array($message['role'], ['system', 'developer'], true)) {
                foreach ($content as $block) {
                    if ($block['type'] !== 'text') throw new AiConnectionException('invalid_response');
                    $system[] = $block;
                }
            } elseif (in_array($message['role'], ['user', 'assistant'], true)) {
                $conversation[] = ['role' => $message['role'], 'content' => $content];
            } else {
                throw new AiConnectionException('invalid_response');
            }
        }
        $body = $this->transport->request('POST', 'messages', [
            'model' => $model ?? throw new AiConnectionException('model_unavailable'),
            'max_tokens' => 4096, 'messages' => $conversation,
            ...($system !== [] ? ['system' => $system] : []),
        ]);
        $text = [];
        foreach ($body['content'] ?? [] as $part) {
            if (($part['type'] ?? '') === 'text' && is_string($part['text'] ?? null)) $text[] = $part['text'];
        }
        $result = implode("\n", $text);
        if (trim($result) === '' || isset($body['error'])) throw new AiConnectionException('invalid_response');
        return $result;
    }
}
