<?php

declare(strict_types=1);

namespace Tests;

use App\Infrastructure\Http\JsonResponder;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Response;

final class JsonResponderTest extends TestCase
{
    public function testInvalidUtf8IsSubstitutedInsteadOfFailingTheResponse(): void
    {
        $response = JsonResponder::respond(new Response(), ['text' => "Fixture \xC3\x28 reply"], 200);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame("Fixture \u{FFFD}( reply", json_decode((string)$response->getBody(), true, flags: JSON_THROW_ON_ERROR)['text']);
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }

    public function testUnencodableDataThrowsInsteadOfWritingAnEmptyBody(): void
    {
        $this->expectException(\JsonException::class);
        JsonResponder::respond(new Response(), ['value' => NAN]);
    }
}
