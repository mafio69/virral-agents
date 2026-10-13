<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ValidationException;

final readonly class ChatImageValidator
{
    public const MAX_BYTES = 524288;

    /** @return list<string> Validated inline images; never fetch remote URLs. */
    public function validate(mixed $images): array
    {
        if (!is_array($images) || !array_is_list($images) || count($images) > 1) {
            throw new ValidationException('Do wiadomości można dodać jeden obraz.');
        }
        foreach ($images as $image) {
            if (!is_string($image) || strlen($image) > 700000
                || !preg_match('~^data:(image/(?:jpeg|png|webp));base64,([A-Za-z0-9+/]+={0,2})$~D', $image, $match)) {
                throw new ValidationException('Dozwolone są tylko obrazy PNG, JPEG i WebP.');
            }
            $bytes = base64_decode($match[2], true);
            if ($bytes === false || strlen($bytes) > self::MAX_BYTES) {
                throw new ValidationException('Obraz przekracza limit 512 KiB.');
            }
            $info = @getimagesizefromstring($bytes);
            if ($info === false || ($info['mime'] ?? '') !== $match[1]
                || $info[0] < 1 || $info[1] < 1 || $info[0] > 4096 || $info[1] > 4096
                || $info[0] * $info[1] > 16000000) {
                throw new ValidationException('Nieprawidłowy obraz lub zbyt duże wymiary.');
            }
        }
        return $images;
    }
}
