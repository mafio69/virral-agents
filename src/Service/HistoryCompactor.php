<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Rule-based history compaction (anti snowball): the last N turns travel to the
 * model in full; older turns keep only the user's intent, trimmed to plain text.
 */
class HistoryCompactor
{
    public const DEFAULT_RECENT_TURNS = 8;
    private const LEGACY_USER_CHARS = 300;

    public function __construct(private readonly int $recentTurns = self::DEFAULT_RECENT_TURNS)
    {
        if ($this->recentTurns < 1) {
            throw new \InvalidArgumentException('Liczba ostatnich tur musi być większa od zera.');
        }
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @return list<array<string, mixed>>
     */
    public function compact(array $messages): array
    {
        // Mechanical assistant notices never reach the model; user instructions always do.
        $visible = array_values(array_filter(
            $messages,
            static fn (array $msg): bool => !($msg['role'] === 'assistant' && ($msg['kind'] ?? 'message') !== 'message'),
        ));
        $cutoff = $this->cutoff($visible);
        $history = [];
        foreach ($visible as $index => $msg) {
            if ($index >= $cutoff) {
                // Generated assistant images are displayed from storage, not replayed without Gemini thought signatures.
                $history[] = ['role' => $msg['role'], 'content' => match (true) {
                    $msg['role'] === 'assistant' && !empty($msg['images']) => $msg['content'] ?: 'Wygenerowano obraz w poprzedniej odpowiedzi.',
                    default => self::modelContent((string) $msg['content'], $msg['images'] ?? []),
                }];
                continue;
            }
            // Older turns: the user's intent survives as trimmed text; stale answers and images drop out.
            if ($msg['role'] === 'user') {
                $text = mb_substr(trim((string) $msg['content']), 0, self::LEGACY_USER_CHARS);
                if ($text !== '') {
                    $history[] = ['role' => 'user', 'content' => $text];
                }
            }
        }
        return $history;
    }

    /** Index where the recent window starts; each user message opens a new turn. */
    private function cutoff(array $visible): int
    {
        $turns = 0;
        for ($index = count($visible) - 1; $index >= 0; $index--) {
            if ($visible[$index]['role'] === 'user' && ++$turns >= $this->recentTurns) {
                return $index;
            }
        }
        return 0;
    }

    /** Shapes one message for the model: plain text, or text+image parts when images exist. */
    public static function modelContent(string $text, array $images): string|array
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
