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

    public function addMessage(AddChatMessageInput $input, ?int $retryMessageId = null): array
    {
        $chat = $this->chatRepository->findById($input->chatId);

        if (!$chat) {
            throw new NotFoundException('Chat not found');
        }

        if ((int)$chat['user_id'] !== $input->userId) {
            throw new NotFoundException('Chat not found');
        }

        $history = $this->chatRepository->findMessagesByChatId($input->chatId);
        $retrySource = null;
        if ($retryMessageId !== null) {
            foreach ($history as $index => $message) {
                if ((int)$message['id'] !== $retryMessageId || $message['role'] !== 'user') {
                    continue;
                }
                // A repeated retry whose response was lost returns the existing pair.
                if (isset($history[$index + 1]) && $history[$index + 1]['role'] === 'assistant') {
                    return [$message, $history[$index + 1]];
                }
                if ($index !== count($history) - 1) {
                    throw new ValidationException('Można ponowić wyłącznie ostatnią wiadomość bez odpowiedzi.');
                }
                $retrySource = $message;
            }
            if ($retrySource === null) {
                throw new NotFoundException('Message not found');
            }
            // Retry never reuses a search consent. Existing research evidence is retained.
            $input = new AddChatMessageInput($input->chatId, $input->userId, $retrySource['content'], $retrySource['images'] ?? []);
        }
        $selector = $this->resolveModel($chat['model'] ?? null);
        $registered = $this->usesRegistry($selector) ? $this->modelRegistry->resolve($selector) : null;
        $client = $registered !== null ? $this->modelRegistry->clientFor($registered) : $this->ovhAiClient;
        $apiModel = $registered?->idModel ?? $selector;
        $images = $this->imageValidator->validate($input->images);
        if ($images !== [] && !$this->modelCatalog->supportsImages($this->resolveModel($chat['model'] ?? null))) {
            throw new ValidationException('Wybrany model nie obsługuje obrazów. Rozpocznij rozmowę z modelem tekst i obrazy.');
        }
        if ($input->research) {
            $this->research->evidence($input->userId, $input->chatId);
        }

        if ($retrySource !== null) {
            $research = $this->chatRepository->claimRetry($retryMessageId);
            $input = new AddChatMessageInput($input->chatId, $input->userId, $input->content, $images, research: $research);
            array_pop($history);
        }
        $messageHistory = $this->historyForModel($history);

        // Add user message to history for AI
        $messageHistory[] = [
            'role' => 'user',
            'content' => $this->modelContent($input->content, $images),
        ];

        $userMessageId = $retryMessageId ?? $this->chatRepository->addMessage(
            $input->chatId,
            'user',
            $input->content,
            $images,
            $input->kind,
        );

        $userMessage = [
            'id' => $userMessageId,
            'chat_id' => $input->chatId,
            'role' => 'user',
            'content' => $input->content,
            'kind' => $input->kind,
            'created_at' => $retrySource['created_at'] ?? date('Y-m-d H:i:s'),
        ];

        if ($images !== []) {
            $userMessage['images'] = $images;
        }

        if ($retryMessageId === null) {
            $this->chatRepository->startReply($userMessageId, $input->research);
        }
        // Add assistant message
        try {
            $reply = $input->research ? $this->researchReply->generate($client, $messageHistory, $apiModel, $input->userId, $input->chatId)
                : $this->replyGenerator->generate($client, $messageHistory, $apiModel, $input->search);
            $aiContent = $reply->text;
            $aiMessageId = $this->chatRepository->addMessage($input->chatId, 'assistant', $aiContent, $reply->images, $reply->kind);
            $this->chatRepository->finishReply($userMessageId, true);
        } catch (\Throwable $e) {
            $this->chatRepository->finishReply($userMessageId, false);
            $this->logger->error('AI call failed', [
                'chat_id' => $input->chatId,
                'user_id' => $input->userId,
                'model' => $selector,
                'reason' => $e instanceof AiConnectionException ? $e->reason : 'internal_error',
                'type' => get_class($e),
                'origin' => basename($e->getFile()).':'.$e->getLine(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
            ]);
            throw $e;
        }

        $assistantMessage = [
            'id' => $aiMessageId,
            'chat_id' => $input->chatId,
            'role' => 'assistant',
            'content' => $aiContent,
            'kind' => $reply->kind,
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

    public function retryMessage(\App\Dto\Chat\RetryMessageInput $input): array
    {
        return $this->addMessage(new AddChatMessageInput($input->chatId, $input->userId, ''), $input->messageId);
    }

    /** Answers the latest real question again with the current research evidence; no new user message is saved. */
    public function continueMessage(\App\Dto\Chat\RetryMessageInput $input): array
    {
        $chat = $this->chatRepository->findById($input->chatId);

        if (!$chat) {
            throw new NotFoundException('Chat not found');
        }

        if ((int)$chat['user_id'] !== $input->userId) {
            throw new NotFoundException('Chat not found');
        }

        $history = $this->chatRepository->findMessagesByChatId($input->chatId);
        $targetIndex = null;
        foreach ($history as $index => $message) {
            if ($message['role'] !== 'user') {
                continue;
            }
            $targetIndex = (int)$message['id'] === $input->messageId ? $index : null;
        }
        if ($targetIndex === null) {
            throw new ValidationException('Kontynuować można wyłącznie ostatnie pytanie.');
        }

        $selector = $this->resolveModel($chat['model'] ?? null);
        $registered = $this->usesRegistry($selector) ? $this->modelRegistry->resolve($selector) : null;
        $client = $registered !== null ? $this->modelRegistry->clientFor($registered) : $this->ovhAiClient;
        $apiModel = $registered?->idModel ?? $selector;
        $this->research->evidence($input->userId, $input->chatId);
        $messageHistory = $this->historyForModel(array_slice($history, 0, $targetIndex + 1));

        try {
            $reply = $this->researchReply->generate($client, $messageHistory, $apiModel, $input->userId, $input->chatId);
            $aiMessageId = $this->chatRepository->addMessage($input->chatId, 'assistant', $reply->text, $reply->images, $reply->kind);
        } catch (\Throwable $e) {
            $this->logger->error('AI continue failed', [
                'chat_id' => $input->chatId,
                'user_id' => $input->userId,
                'model' => $selector,
                'reason' => $e instanceof AiConnectionException ? $e->reason : 'internal_error',
                'type' => get_class($e),
                'origin' => basename($e->getFile()).':'.$e->getLine(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
            ]);
            throw $e;
        }

        $assistantMessage = [
            'id' => $aiMessageId,
            'chat_id' => $input->chatId,
            'role' => 'assistant',
            'content' => $reply->text,
            'kind' => $reply->kind,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if ($reply->images !== []) {
            $assistantMessage['images'] = $reply->images;
        }
        if ($reply->search !== null) {
            $assistantMessage['search'] = $reply->search;
        }
        return $assistantMessage;
    }

    /** @param list<array{id: int, role: string, content: string, kind?: string, images?: list<string>}> $messages */
    private function historyForModel(array $messages): array
    {
        $history = [];
        foreach ($messages as $msg) {
            // Mechanical assistant notices never reach the model; user instructions always do.
            if ($msg['role'] === 'assistant' && ($msg['kind'] ?? 'message') !== 'message') {
                continue;
            }
            $history[] = [
                'role' => $msg['role'],
                // Generated assistant images are displayed from storage, not replayed without Gemini thought signatures.
                'content' => match (true) {
                    $msg['role'] === 'assistant' && !empty($msg['images']) => $msg['content'] ?: 'Wygenerowano obraz w poprzedniej odpowiedzi.',
                    default => $this->modelContent($msg['content'], $msg['images'] ?? []),
                },
            ];
        }
        return $history;
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
