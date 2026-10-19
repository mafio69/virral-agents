<?php

declare(strict_types=1);

namespace Tests;

use App\Dto\Chat\ModelSelector;
use PHPUnit\Framework\TestCase;

final class ModelSelectorTest extends TestCase
{
    public function testSelectorsKeepProviderAndModelIdentity(): void
    {
        foreach ([['google:gemini:variant', true, 'google', 'gemini:variant', false],
            ['db:12', true, 'db', '12', false],
            ['Qwen3.6-27B', false, 'ovh', 'Qwen3.6-27B', false],
            ['glm-legacy', false, 'ovh', 'glm-legacy', true],
            ['zai:glm-5', true, 'zai', 'glm-5', false]] as [$value, $registry, $provider, $id, $legacy]) {
            $selector = new ModelSelector($value);
            self::assertSame($registry, $selector->isRegistry());
            self::assertSame($provider, $selector->provider());
            self::assertSame($id, $selector->modelId());
            self::assertSame($legacy, $selector->isLegacyGlm());
        }
    }
}
