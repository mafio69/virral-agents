<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Exception\AiConnectionException;

final class GeminiProvider implements ChatProviderInterface, MultimodalClientInterface, WebToolClientInterface
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
        return 'google';
    }
    public function name(): string
    {
        return 'Google / Gemini';
    }

    public function listModels(): array
    {
        if ($this->models !== null) {
            return $this->models;
        }
        $models = [];
        $cursor = null;
        $seen = [];
        for ($page = 0; $page < 20; $page++) {
            $body = $this->transport->request('GET', 'models', query: ['pageSize' => 100, ...($cursor !== null ? ['pageToken' => $cursor] : [])]);
            $data = $body['models'] ?? null;
            if (!is_array($data) || !array_is_list($data)) {
                throw new AiConnectionException('invalid_response');
            }
            foreach ($data as $entry) {
                $name = is_array($entry) ? ($entry['name'] ?? null) : null;
                if (!is_string($name)) {
                    throw new AiConnectionException('invalid_response');
                }
                if (!preg_match('#^models/(gemini-[a-zA-Z0-9._-]{1,150})$#D', $name, $matches)) {
                    continue;
                }
                $methods = $entry['supportedGenerationMethods'] ?? [];
                if (!is_array($methods)) {
                    throw new AiConnectionException('invalid_response');
                }
                if (!in_array('generateContent', $methods, true) || preg_match('/audio|tts|robotics/', $matches[1])) {
                    continue;
                }
                $id = $matches[1];
                $imageOutput = self::generatesImages($id);
                if (str_contains($id, 'image') && !$imageOutput) {
                    continue;
                }
                $models[$id] = ['id' => $id, 'name' => is_string($entry['displayName'] ?? null) ? $entry['displayName'] : $id,
                    'supports_images' => true, 'generates_images' => $imageOutput];
            }
            $cursor = $body['nextPageToken'] ?? '';
            if ($cursor === '') {
                return $this->models = array_values($models);
            }
            if (!is_string($cursor) || strlen($cursor) > 2048 || isset($seen[$cursor])) {
                throw new AiConnectionException('invalid_response');
            }
            $seen[$cursor] = true;
        }
        throw new AiConnectionException('invalid_response');
    }

    public function generate(array $messages, ?string $model = null): string
    {
        return $this->generateReply($messages, $model)->text;
    }

    public static function generatesImages(string $model): bool
    {
        return preg_match('/^gemini-(?:2\.5-flash-image|3(?:\.1)?-(?:flash(?:-lite)?|pro)-image|nano-banana-2\.1)(?:-preview(?:-\d{2}-\d{2})?)?$/D', $model) === 1;
    }

    public function generateReply(array $messages, ?string $model = null): \App\Dto\AI\AiReply
    {
        return $this->reply($messages, $model, false);
    }

    public function generateWebReply(array $messages, string $model): \App\Dto\AI\AiReply
    {
        return $this->reply($messages, $model, !self::generatesImages($model));
    }

    private function reply(array $messages, ?string $model, bool $web): \App\Dto\AI\AiReply
    {
        if ($model === null || !preg_match('/^gemini-[a-zA-Z0-9._-]{1,150}$/D', $model)) {
            throw new AiConnectionException('model_unavailable');
        }
        $contents = [];
        $system = [];
        foreach ($messages as $message) {
            $parts = is_string($message['content']) ? [['text' => $message['content']]]
                : array_map(static function (array $part): array {
                    if ($part['type'] === 'text') {
                        return ['text' => $part['text']];
                    }
                    if ($part['type'] !== 'image_url') {
                        throw new AiConnectionException('invalid_response');
                    }
                    $image = InlineImage::parse($part['image_url']['url']);
                    return ['inlineData' => ['mimeType' => $image['mime_type'], 'data' => $image['data']]];
                }, $message['content']);
            if (in_array($message['role'], ['system', 'developer'], true)) {
                foreach ($parts as $part) {
                    if (!isset($part['text'])) {
                        throw new AiConnectionException('invalid_response');
                    }
                    $system[] = $part;
                }
            } elseif (in_array($message['role'], ['user', 'assistant'], true)) {
                $contents[] = ['role' => $message['role'] === 'assistant' ? 'model' : 'user', 'parts' => $parts];
            } else {
                throw new AiConnectionException('invalid_response');
            }
        }
        $imageOutput = self::generatesImages($model);
        $body = $this->transport->request('POST', 'models/'.$model.':generateContent', [
            'contents' => $contents, 'generationConfig' => ['maxOutputTokens' => $imageOutput ? 8192 : 4096,
                ...($imageOutput ? ['responseModalities' => ['TEXT', 'IMAGE']] : [])],
            ...($system !== [] ? ['systemInstruction' => ['parts' => $system]] : []),
            ...($web ? ['tools' => [['functionDeclarations' => array_map(static function (array $tool): array {
                // Gemini Schema uses its OpenAPI subset; argument strictness is enforced locally.
                unset($tool['parameters']['additionalProperties']);
                return $tool;
            }, WebTools::definitions())]]] : []),
        ], maxResponseBytes: $imageOutput ? 12582912 : 4194304);
        if (isset($body['error']) || isset($body['promptFeedback']['blockReason'])) {
            throw new AiConnectionException('invalid_response');
        }
        $text = [];
        $images = [];
        $candidate = $body['candidates'][0] ?? [];
        if (isset($candidate['finishReason']) && $candidate['finishReason'] !== 'STOP') {
            throw new AiConnectionException('invalid_response');
        }
        $parts = $candidate['content']['parts'] ?? [];
        if (!is_array($parts) || !array_is_list($parts)) {
            throw new AiConnectionException('invalid_response');
        }
        foreach ($parts as $part) {
            if (!is_array($part)) {
                throw new AiConnectionException('invalid_response');
            }
            if (($part['thought'] ?? false) === true) {
                continue;
            }
            if ($web && isset($part['functionCall'])) {
                $call = $part['functionCall'];
                return new \App\Dto\AI\AiReply('', webRequest: WebTools::request($call['name'] ?? '', $call['args'] ?? null));
            }
            if (is_string($part['text'] ?? null)) {
                $text[] = $part['text'];
            }
            if (isset($part['inlineData'])) {
                $data = $part['inlineData'];
                if (!$imageOutput || !is_array($data) || !is_string($data['mimeType'] ?? null) || !is_string($data['data'] ?? null)) {
                    throw new AiConnectionException('invalid_response');
                }
                $images[] = 'data:'.$data['mimeType'].';base64,'.$data['data'];
            }
        }
        $result = implode("\n", $text);
        try {
            $images = (new \App\Service\ChatImageValidator(8388608))->validate($images);
        } catch (\App\Exception\ValidationException) {
            throw new AiConnectionException('invalid_response');
        }
        if (trim($result) === '' && $images === []) {
            throw new AiConnectionException('invalid_response');
        }
        return new \App\Dto\AI\AiReply($result, $images);
    }
}
