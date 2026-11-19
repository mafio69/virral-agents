<?php

declare(strict_types=1);

namespace App\Mapper\AI;

use App\Dto\AI\AiModelInput;
use App\Exception\ValidationException;

final readonly class AiModelInputMapper
{
    public function map(array $data): AiModelInput
    {
        $providerId = $data['provider_id'] ?? null;
        $idModel = $data['id_model'] ?? '';
        $nameDisplay = $data['name_display'] ?? '';
        $endpointUrl = $data['endpoint_url'] ?? '';

        if (!is_int($providerId) && !ctype_digit((string)$providerId)) {
            throw new ValidationException('Identyfikator dostawcy jest wymagany.');
        }
        if (!is_string($idModel) || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}$/D', $idModel)) {
            throw new ValidationException('Niepoprawny identyfikator modelu.');
        }
        if (!is_string($nameDisplay) || trim($nameDisplay) === '') {
            $nameDisplay = $idModel;
        }
        if (!is_string($endpointUrl) || filter_var($endpointUrl, FILTER_VALIDATE_URL) === false) {
            throw new ValidationException('Podaj poprawny adres endpointu.');
        }

        return new AiModelInput(
            providerId: (int)$providerId,
            idModel: trim($idModel),
            nameDisplay: trim($nameDisplay),
            endpointUrl: rtrim(trim($endpointUrl), '/'),
        );
    }
}
