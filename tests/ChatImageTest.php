<?php

declare(strict_types=1);

namespace Tests;

use App\Exception\ValidationException;
use App\Mapper\Chat\AddChatMessageInputMapper;
use App\Service\ChatImageValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChatImageTest extends TestCase
{
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aXfoAAAAASUVORK5CYII=';

    public function testImageOnlyMessageIsAccepted(): void
    {
        $input = (new AddChatMessageInputMapper())->map(['images' => [self::PNG]], 1, 2);
        $this->assertSame('', $input->content);
        $this->assertSame([self::PNG], $input->images);
    }

    #[DataProvider('invalidImages')]
    public function testUnsafeImagesAreRejected(mixed $images): void
    {
        $this->expectException(ValidationException::class);
        (new ChatImageValidator())->validate($images);
    }

    public static function invalidImages(): array
    {
        return [
            'remote URL' => [['https://example.invalid/picture.png']],
            'SVG' => [['data:image/svg+xml;base64,PHN2Zz4=']],
            'fake JPEG MIME' => [[str_replace('image/png', 'image/jpeg', self::PNG)]],
            'broken image' => [['data:image/png;base64,YWJj']],
            'too many' => [[self::PNG, self::PNG]],
            'wrong shape' => ['not-an-array'],
            'oversized' => [['data:image/png;base64,'.str_repeat('A', 700001)]],
        ];
    }

    public function testEmptyMessageStillFails(): void
    {
        $this->expectException(ValidationException::class);
        (new AddChatMessageInputMapper())->map(['content' => ' '], 1, 2);
    }
}
