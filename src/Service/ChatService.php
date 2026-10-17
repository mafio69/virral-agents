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
use App\Exception\AiConnectionException;
use App\Repository\ChatRepository;
use Psr\Log\LoggerInterface;

final readonly class ChatService
{
    public function __construct(
        private readonly ChatRepository $chatRepository,
        private readonly OvhAiClient $ovhAiClient,
        private readonly LoggerInterface $logger,
        private readonly ChatModelRegistryInterface $modelRegistry,
        private readonly ChatReplyGeneratorInterface $replyGenerator,
        private readonly ChatImageValidator $imageValidator,
        private readonly ModelCatalogInterface $modelCatalog,
        private readonly ResearchServiceInterface $research,
        private readonly ResearchReplyGeneratorInterface $researchReply,
    ) {
    }

    public function createChat(CreateChatInput $input): ChatResult
    {
        $model = $this->resolveModel($input->model);
        if ($this->usesRegistry($model)) {
            $this->modelRegistry->resolve($model);
        }
        $id = $this->chatRepository->create($input->userId, $input->title, $model);
        $this->research->adoptDraft($input->userId, $id);

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
            fn (array $row) => new ChatResult(
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
        $registered = $this->usesRegistry($selector) ? $this->modelRegistry->resolve($selector) : null;
        $client = $registered !== null ? $this->modelRegistry->clientFor($registered) : $this->ovhAiClient;
        $apiModel = $registered?->idModel ?? $selector;
        $images = $this->imageValidator->validate($input->images);
        if ($images !== [] && !$this->modelCatalog->supportsImages($this->resolveModel($chat['model'] ?? null))) {
            throw new ValidationException('Wybrany model nie obsługuje obrazów. Rozpocznij rozmowę z modelem tekst i obrazy.');
        }
        $history = $this->chatRepository->findMessagesByChatId($input->chatId);
        if ($input->research) {
            $this->research->evidence($input->userId, $input->chatId);
        }

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

        if ($images !== []) {
            $userMessage['images'] = $images;
        }

        // Add assistant message
        try {
            $reply = $input->research ? $this->researchReply->generate($client, $messageHistory, $apiModel, $input->userId, $input->chatId)
                : $this->replyGenerator->generate($client, $messageHistory, $apiModel, $input->search);
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
        if ($reply->images !== []) {
            $assistantMessage['images'] = $reply->images;
        }
        if ($reply->search !== null) {
            $assistantMessage['search'] = $reply->search;
        }
        return [$userMessage, $assistantMessage];
    }

    private function resolveModel(?string $model): string
    {
        if ($model !== null && $this->usesRegistry($model)) {
            return $model;
        }
        if ($model !== null && (new \App\Dto\Chat\ModelSelector($model))->isLegacyGlm()) {
            throw new AiConnectionException('model_unavailable');
        }
        return \App\Infrastructure\AI\OvhModelDefaults::resolve(
            $model,
            \App\Infrastructure\AI\OvhModelDefaults::resolve($this->ovhAiClient->getDefaultModel())
        );
    }

    private function usesRegistry(string $model): bool
    {
        return (new \App\Dto\Chat\ModelSelector($model))->isRegistry();
    }

    private function modelContent(string $text, array $images): string|array
    {
        if ($images === []) {
            return $text;
        }
        $parts = [['type' => 'text', 'text' => $text !== '' ? $text : 'Opisz ten obraz.']];
        foreach ($images as $image) {
            $parts[] = ['type' => 'image_url', 'image_url' => ['url' => $image]];
        }
        return $parts;
    }

}
