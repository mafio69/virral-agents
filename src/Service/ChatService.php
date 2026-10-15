<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Chat\AddChatMessageInput;
use App\Dto\Chat\ChatResult;
use App\Dto\Chat\CreateChatInput;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Infrastructure\AI\OvhAiClient;
use App\Infrastructure\AI\ChatModelRegistryInterface;
use App\Infrastructure\AI\ZaiEndpointPolicy;
use App\Exception\AiConnectionException;
use App\Repository\ChatRepository;
use Psr\Log\LoggerInterface;

final readonly class ChatService
{
    public function __construct(
        private readonly ChatRepository $chatRepository,
        private readonly OvhAiClient $ovhAiClient,
        private readonly LoggerInterface $logger,
        private readonly ?ChatModelRegistryInterface $modelRegistry = null,
    ) {
    }

    public function createChat(CreateChatInput $input): ChatResult
    {
        $model = $this->resolveModel($input->model);
        if ($this->usesRegistry($model)) $this->registry()->resolve($model);
        $id = $this->chatRepository->create($input->userId, $input->title, $model);

        $this->logger->info('Chat created', ['chat_id' => $id, 'user_id' => $input->userId]);

        return new ChatResult(
            id: $id,
            userId: $input->userId,
            title: $input->title,
            createdAt: date('Y-m-d H:i:s'),
            model: $model,
        );
    }

    public function getUserChats(int $userId): array
    {
        $rows = $this->chatRepository->findByUserId($userId);

        return array_map(
            fn(array $row) => new ChatResult(
                id: (int)$row['id'],
                userId: (int)$row['user_id'],
                title: $row['title'],
                createdAt: $row['created_at'],
                model: $this->resolveModel($row['model'] ?? null),
            ),
            $rows
        );
    }

    public function getChatWithMessages(int $chatId, int $userId): ChatResult
    {
        $chat = $this->chatRepository->findById($chatId);

        if (!$chat) {
            throw new NotFoundException('Chat not found');
        }

        if ((int)$chat['user_id'] !== $userId) {
            throw new NotFoundException('Chat not found');
        }

        $messages = $this->chatRepository->findMessagesByChatId($chatId);

        return new ChatResult(
            id: (int)$chat['id'],
            userId: (int)$chat['user_id'],
            title: $chat['title'],
            createdAt: $chat['created_at'],
            messages: $messages,
            model: $this->resolveModel($chat['model'] ?? null),
        );
    }

    public function addMessage(AddChatMessageInput $input): array
    {
        $chat = $this->chatRepository->findById($input->chatId);

        if (!$chat) {
            throw new NotFoundException('Chat not found');
        }

        if ((int)$chat['user_id'] !== $input->userId) {
            throw new NotFoundException('Chat not found');
        }

        $selector = $this->resolveModel($chat['model'] ?? null);
        $registered = $this->usesRegistry($selector) ? $this->registry()->resolve($selector) : null;
        $client = $registered !== null ? $this->registry()->clientFor($registered) : $this->ovhAiClient;
        $apiModel = $registered?->idModel ?? $selector;
        $images = (new ChatImageValidator())->validate($input->images);
        if ($images !== [] && !$this->supportsImages($this->resolveModel($chat['model'] ?? null))) {
            throw new ValidationException('Wybrany model nie obsługuje obrazów. Rozpocznij rozmowę z modelem tekst i obrazy.');
        }
        $history = $this->chatRepository->findMessagesByChatId($input->chatId);

        $messageHistory = [];
        foreach ($history as $msg) {
            $messageHistory[] = [
                'role' => $msg['role'],
                // Generated assistant images are displayed from storage, not replayed without Gemini thought signatures.
                'content' => $msg['role'] === 'assistant' && !empty($msg['images'])
                    ? ($msg['content'] ?: 'Wygenerowano obraz w poprzedniej odpowiedzi.')
                    : $this->modelContent($msg['content'], $msg['images'] ?? []),
            ];
        }

        // Add user message to history for AI
        $messageHistory[] = [
            'role' => 'user',
            'content' => $this->modelContent($input->content, $images),
        ];

        $userMessageId = $this->chatRepository->addMessage(
            $input->chatId,
            'user',
            $input->content,
            $images,
        );
        
        $userMessage = [
            'id' => $userMessageId,
            'chat_id' => $input->chatId,
            'role' => 'user',
            'content' => $input->content,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if ($images !== []) $userMessage['images'] = $images;

        // Add assistant message
        try {
            $reply = $client instanceof \App\Infrastructure\AI\MultimodalClientInterface
                ? $client->generateReply($messageHistory, $apiModel)
                : new \App\Dto\AI\AiReply($client->generate($messageHistory, $apiModel));
            $aiContent = $reply->text;
        } catch (\Throwable $e) {
            $this->logger->error('AI call failed', ['chat_id' => $input->chatId]);
            throw $e;
        }
        
        $aiMessageId = $this->chatRepository->addMessage(
            $input->chatId,
            'assistant',
            $aiContent,
            $reply->images,
        );
        
        $assistantMessage = [
            'id' => $aiMessageId,
            'chat_id' => $input->chatId,
            'role' => 'assistant',
            'content' => $aiContent,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // Return both messages
        if ($reply->images !== []) $assistantMessage['images'] = $reply->images;
        return [$userMessage, $assistantMessage];
    }

    private function resolveModel(?string $model): string
    {
        if ($model !== null && $this->usesRegistry($model)) return $model;
        if ($model !== null && str_starts_with($model, 'glm-')) {
            throw new AiConnectionException('model_unavailable');
        }
        return \App\Infrastructure\AI\OvhModelDefaults::resolve($model,
            \App\Infrastructure\AI\OvhModelDefaults::resolve($this->ovhAiClient->getDefaultModel()));
    }

    private function registry(): ChatModelRegistryInterface
    {
        return $this->modelRegistry ?? throw new AiConnectionException('missing_configuration');
    }

    private function usesRegistry(string $model): bool
    {
        return str_contains($model, ':');
    }

    private function modelContent(string $text, array $images): string|array
    {
        if ($images === []) return $text;
        $parts = [['type' => 'text', 'text' => $text !== '' ? $text : 'Opisz ten obraz.']];
        foreach ($images as $image) {
            $parts[] = ['type' => 'image_url', 'image_url' => ['url' => $image]];
        }
        return $parts;
    }

    public function getConnections(): array
    {
        return [
            ['id' => 'ovh', 'name' => 'OVH'],
            ['id' => 'zai', 'name' => 'Z.ai / GLM'],
            ...($this->modelRegistry instanceof \App\Infrastructure\AI\MultiProviderRegistryInterface ? $this->modelRegistry->connections() : []),
        ];
    }

    public function getAvailableModels(?string $connection = null): array
    {
        if ($connection !== null && !in_array($connection, array_column($this->getConnections(), 'id'), true)) {
            throw new ValidationException('Nieznane połączenie AI.');
        }
        if ($connection !== null && $connection !== 'ovh') {
            $registry = $this->registry();
            return $registry instanceof \App\Infrastructure\AI\MultiProviderRegistryInterface ? $registry->listFor($connection) : $registry->listModels();
        }
        // The legacy endpoint without a connection parameter remains the OVH catalogue.
        try {
            $catalogue = $this->ovhAiClient->listModels();

            // Filter and format models
            $models = [];
            foreach ($catalogue as $model) {
                // Keep chat models; image understanding is a separate capability.
                if (preg_match('/embedding|stable-diffusion|bge-|whisper|guard|tts|^ppl$/i', $model['id'])) {
                    continue;
                }

                $models[] = [
                    'id' => $model['id'],
                    'provider' => 'ovh',
                    'name' => $model['id'],
                    'description' => $this->getModelDescription($model['id']),
                    'category' => $this->getModelCategory($model['id']),
                    'supports_images' => $this->supportsImages($model['id']),
                    'is_default' => $model['id'] === $this->resolveModel(null),
                    'context_length' => $model['context_length'] ?? 0,
                    'max_completion_tokens' => $model['max_completion_tokens'] ?? 0,
                ];
            }
            
            return $models;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch models from OVH AI API');
            throw $e;
        }
    }

    private function getModelDescription(string $modelId): string
    {
        $descriptions = [
            'Qwen3.8-27B' => 'Coding and reasoning with image understanding',
            'Qwen3-Coder-30B-A3B-Instruct' => 'Advanced coding model optimized for code generation and debugging',
            'Qwen3-32B' => 'General purpose large language model',
            'Qwen3.5-397B-A17B' => 'Large reasoning model for complex tasks',
            'Qwen3.6-27B' => 'Visual language model with multimodal capabilities',
            'Qwen3.5-9B' => 'Efficient model for general tasks',
            'Mistral-7B-Instruct-v0.3' => 'Balanced model for general tasks',
            'Mistral-Small-3.2-24B-Instruct-2506' => 'Latest Mistral model with improved performance',
            'Mistral-Nemo-Instruct-2407' => 'Mistral Nemo model for general tasks',
            'Meta-Llama-3_3-70B-Instruct' => 'Meta Llama 3.3 70B model for general tasks',
            'Llama-3.1-8B-Instruct' => 'Efficient Llama model for general tasks',
            'gpt-oss-20b' => 'OpenAI-style 20B model for general tasks',
            'gpt-oss-120b' => 'Large OpenAI-style 120B model for complex tasks',
            'Qwen2.5-VL-72B-Instruct' => 'Visual language model with image understanding',
            'whisper-large-v3' => 'Speech to text model',
            'whisper-large-v3-turbo' => 'Fast speech to text model',
        ];
        
        return $descriptions[$modelId] ?? 'AI model for various tasks';
    }

    private function supportsImages(string $modelId): bool
    {
        if ($this->usesRegistry($modelId)) {
            if ($this->registry() instanceof \App\Infrastructure\AI\MultiProviderRegistryInterface) return $this->registry()->supportsImages($modelId);
            return ZaiEndpointPolicy::supportsImages($this->registry()->resolve($modelId)->idModel);
        }
        return in_array($modelId, [
            'Qwen3.8-27B', 'Qwen3.6-27B', 'Qwen3.5-397B-A17B',
            'Qwen3.5-9B', 'Qwen2.5-VL-72B-Instruct',
        ], true);
    }

    private function getModelCategory(string $modelId): string
    {
        if ($modelId === 'Qwen3.8-27B' || str_contains($modelId, 'Coder')) {
            return 'code';
        }
        if ($this->supportsImages($modelId)) {
            return 'visual';
        }
        if (str_contains($modelId, 'whisper')) {
            return 'audio';
        }
        if (str_contains($modelId, 'Guard')) {
            return 'guard';
        }
        return 'general';
    }
}
