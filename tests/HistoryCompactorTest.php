<?php

declare(strict_types=1);

namespace Tests;

use App\Service\HistoryCompactor;
use PHPUnit\Framework\TestCase;

final class HistoryCompactorTest extends TestCase
{
    private function turn(int $number, string $question = 'Pytanie', string $answer = 'Odpowiedź'): array
    {
        return [
            ['role' => 'user', 'content' => $question.' nr '.$number],
            ['role' => 'assistant', 'content' => $answer.' nr '.$number],
        ];
    }

    public function testThirtyTurnsKeepEightFullAndTrimOlderUserMessages(): void
    {
        $messages = [];
        for ($i = 1; $i <= 30; $i++) {
            $messages = [...$messages, ...$this->turn($i)];
        }
        $history = (new HistoryCompactor())->compact($messages);
        // 22 old user messages (trimmed) + 8 full turns (16 messages).
        self::assertCount(22 + 16, $history);
        $old = array_slice($history, 0, 22);
        self::assertContainsOnly('string', array_column($old, 'content'));
        self::assertSame(array_fill(0, 22, 'user'), array_column($old, 'role'));
        $recent = array_slice($history, 22);
        self::assertSame(['user', 'assistant'], [ $recent[0]['role'], $recent[1]['role'] ]);
        self::assertSame('Pytanie nr 23', $recent[0]['content']);
        self::assertSame('Odpowiedź nr 30', $recent[15]['content']);
    }

    public function testOlderUserMessagesAreTrimmedToPlainThreeHundredChars(): void
    {
        $messages = [];
        for ($i = 1; $i <= 10; $i++) {
            $messages[] = ['role' => 'user', 'content' => $i === 1 ? str_repeat('a', 500) : 'krótkie pytanie '.$i];
            $messages[] = ['role' => 'assistant', 'content' => 'odpowiedź '.$i];
        }
        $history = (new HistoryCompactor(2))->compact($messages);
        $first = $history[0];
        self::assertSame('user', $first['role']);
        self::assertSame(300, mb_strlen($first['content']));
        self::assertStringContainsString('odpowiedź 10', implode(' ', array_column($history, 'content')));
    }

    public function testShortHistoryTravelsUnchangedIncludingImages(): void
    {
        $messages = [
            ['role' => 'user', 'content' => 'Opisz to', 'images' => ['data:image/png;base64,fixture']],
            ['role' => 'assistant', 'content' => '', 'images' => ['data:image/png;base64,generated']],
            ['role' => 'user', 'content' => 'Kontynuuj'],
        ];
        $history = (new HistoryCompactor())->compact($messages);
        self::assertCount(3, $history);
        self::assertSame([
            ['type' => 'text', 'text' => 'Opisz to'],
            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,fixture']],
        ], $history[0]['content']);
        self::assertSame('Wygenerowano obraz w poprzedniej odpowiedzi.', $history[1]['content']);
        self::assertSame('Kontynuuj', $history[2]['content']);
    }

    public function testMechanicalAssistantNoticesNeverReachTheModel(): void
    {
        $messages = [
            ['role' => 'assistant', 'kind' => 'approval_request', 'content' => 'AI prosi o zgodę'],
            ['role' => 'assistant', 'kind' => 'continuation', 'content' => 'mechaniczna'],
            ...$this->turn(1),
        ];
        $history = (new HistoryCompactor(1))->compact($messages);
        self::assertSame([['role' => 'user', 'content' => 'Pytanie nr 1'], ['role' => 'assistant', 'content' => 'Odpowiedź nr 1']], $history);
    }

    public function testOldUserImagesDropOutButTextSurvives(): void
    {
        $messages = [
            ['role' => 'user', 'content' => '  Stare pytanie z obrazem  ', 'images' => ['data:image/png;base64,stale']],
            ...$this->turn(1),
        ];
        $history = (new HistoryCompactor(1))->compact($messages);
        self::assertSame('Stare pytanie z obrazem', $history[0]['content']);
        self::assertCount(3, $history);
    }

    public function testTrailingUserMessageCountsAsItsOwnTurn(): void
    {
        $messages = [];
        for ($i = 1; $i <= 3; $i++) {
            $messages = [...$messages, ...$this->turn($i)];
        }
        $messages[] = ['role' => 'user', 'content' => 'Jeszcze bez odpowiedzi'];
        $history = (new HistoryCompactor(2))->compact($messages);
        // Turn 3 and the trailing question stay full; turn 1 and 2 collapse to trimmed user text.
        self::assertSame('Pytanie nr 1', $history[0]['content']);
        self::assertSame('Pytanie nr 2', $history[1]['content']);
        self::assertSame('Pytanie nr 3', $history[2]['content']);
        self::assertSame('Odpowiedź nr 3', $history[3]['content']);
        self::assertSame('Jeszcze bez odpowiedzi', $history[4]['content']);
        self::assertCount(5, $history);
    }

    public function testNonPositiveRecentTurnsIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HistoryCompactor(0);
    }
}
