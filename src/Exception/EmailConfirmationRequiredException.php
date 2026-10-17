<?php

declare(strict_types=1);

namespace App\Exception;

final class EmailConfirmationRequiredException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Potwierdź adres email przed zalogowaniem. Sprawdź wiadomość z linkiem potwierdzającym.');
    }
}
