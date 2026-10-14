<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Exception\AiConnectionException;

final class GeminiProvider implements ChatProviderInterface
{
    private ?array $models = null;

    public function __construct(private readonly ProviderTransport $transport) {}
    public function id(): string { return 'google'; }
    public function name(): string { return 'Google / Gemini'; }

    public function listModels(): array
    {
        if ($this->models !== null) return $this->models;
        $models = [];
        $cursor = null;
        $seen = [];
        for ($page = 0; $page < 20; $page++) {
            $body = $this->transport->request('GET', 'models', query: ['pageSize' => 100, ...($cursor !== null ? ['pageToken' => $cursor] : [])]);
            $data = $body['models'] ?? null;
            if (!is_array($data) || !array_is_list($data)) throw new AiConnectionException('invalid_response');
            foreach ($data as $entry) {
                $name = is_array($entry) ? ($entry['name'] ?? null) : null;
                if (!is_string($name)) throw new AiConnectionException('invalid_response');
                if (!preg_match('#^models/(gemini-[a-zA-Z0-9._-]{1,150})$#D', $name, $matches)) continue;
                $methods = $entry['supportedGenerationMethods'] ?? [];
                if (!is_array($methods)) throw new AiConnectionException('invalid_response');
                if (!in_array('generateContent', $methods, true) || preg_match('/image|audio|tts|robotics/', $matches[1])) continue;
                $id = $matches[1];
                $models[$id] = ['id' => $id, 'name' => is_string($entry['displayName'] ?? null) ? $entry['displayName'] : $id, 'supports_images' => true];
            }
            $cursor = $body['nextPageToken'] ?? '';
            if ($cursor === '') return $this->models = array_values($models);
            if (!is_string($cursor) || strlen($cursor) > 2048 || isset($seen[$cursor])) throw new AiConnectionException('invalid_response');
            $seen[$cursor] = true;
        }
        throw new AiConnectionException('invalid_response');
    }

    public function generate(array $messages, ?string $model = null): string
    {
        if ($model === null || !preg_match('/^gemini-[a-zA-Z0-9._-]{1,150}$/D', $model)) throw new AiConnectionException('model_unavailable');
        $contents = [];
        $system = [];
        foreach ($messages as $message) {
            $parts = is_string($message['content']) ? [['text' => $message['content']]]
                : array_map(static function (array $part): array {
                    if ($part['type'] === 'text') return ['text' => $part['text']];
                    if ($part['type'] !== 'image_url') throw new AiConnectionException('invalid_response');
                    $image = InlineImage::parse($part['image_url']['url']);
                    return ['inlineData' => ['mimeType' => $image['mime_type'], 'data' => $image['data']]];
                }, $message['content']);
            if (in_array($message['role'], ['system', 'developer'], true)) {
                foreach ($parts as $part) {
                    if (!isset($part['text'])) throw new AiConnectionException('invalid_response');
                    $system[] = $part;
                }
            } elseif (in_array($message['role'], ['user', 'assistant'], true)) {
                $contents[] = ['role' => $message['role'] === 'assistant' ? 'model' : 'user', 'parts' => $parts];
            } else {
                throw new AiConnectionException('invalid_response');
            }
        }
        $body = $this->transport->request('POST', 'models/'.$model.':generateContent', [
            'contents' => $contents, 'generationConfig' => ['maxOutputTokens' => 4096],
            ...($system !== [] ? ['systemInstruction' => ['parts' => $system]] : []),
        ]);
        if (isset($body['error']) || isset($body['promptFeedback']['blockReason'])) throw new AiConnectionException('invalid_response');
        $text = [];
        foreach ($body['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (($part['thought'] ?? false) !== true && is_string($part['text'] ?? null)) $text[] = $part['text'];
        }
        $result = implode("\n", $text);
        if (trim($result) === '') throw new AiConnectionException('invalid_response');
        return $result;
    }
}
