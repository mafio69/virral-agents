<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

final class SearchException extends \RuntimeException
{
    public function __construct() { parent::__construct('Wyszukiwanie jest niedostępne.'); }
}
