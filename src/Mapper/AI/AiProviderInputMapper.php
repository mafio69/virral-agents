<?php

declare(strict_types=1);

namespace App\Mapper\AI;

use App\Dto\AI\AiProviderInput;
use App\Exception\ValidationException;

final readonly class AiProviderInputMapper
{
    public function map(array $data): AiProviderInput
    {
        $name = $data['name'] ?? '';
        $secret = $data['secret'] ?? '';
        $endpointUrl = $data['endpoint_url'] ?? '';

        if (!is_string($name) || trim($name) === '') {
            throw new ValidationException('Nazwa dostawcy jest wymagana.');
        }
        if (!is_string($secret) || trim($secret) === '') {
            throw new ValidationException('Klucz API jest wymagany.');
        }
        if (!is_string($endpointUrl) || !self::isHttpUrl($endpointUrl)) {
            throw new ValidationException('Podaj poprawny adres endpointu (http/https).');
        }

        $modelId = $data['model_id'] ?? null;
        if ($modelId !== null && (!is_string($modelId) || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}$/D', $modelId))) {
            throw new ValidationException('Niepoprawny identyfikator modelu.');
        }

        $modelName = $data['model_name'] ?? null;
        if ($modelName !== null && (!is_string($modelName) || trim($modelName) === '')) {
            $modelName = null;
        }

        $description = $data['description'] ?? null;
        if ($description !== null && (!is_string($description) || trim($description) === '')) {
            $description = null;
        }

        return new AiProviderInput(
            name: trim($name),
            secret: trim($secret),
            endpointUrl: rtrim(trim($endpointUrl), '/'),
            modelId: is_string($modelId) ? trim($modelId) : null,
            modelName: is_string($modelName) ? trim($modelName) : null,
            description: is_string($description) ? trim($description) : null,
        );
    }

    private static function isHttpUrl(string $value): bool
    {
        $url = filter_var($value, FILTER_VALIDATE_URL);
        if ($url === false) {
            return false;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return in_array($scheme, ['http', 'https'], true);
    }
}
