<?php

declare(strict_types=1);

// Reports dotenv syntax errors by line number and category only.
// It never prints names, values or the parser message, because those contain the file content.

use Dotenv\Exception\InvalidFileException;
use Dotenv\Parser\Parser;

require __DIR__.'/../vendor/autoload.php';

$path = $argv[1] ?? __DIR__.'/../.env';
if (!is_file($path) || !is_readable($path)) {
    fwrite(STDERR, "Brak pliku środowiska do sprawdzenia.\n");
    exit(2);
}

$categories = [
    'unexpected whitespace' => 'spacja w wartości bez cudzysłowu (ujmij wartość w cudzysłów)',
    'a missing closing quote' => 'brak zamykającego cudzysłowu',
    'an invalid name' => 'niedozwolona nazwa zmiennej (dozwolone: litery, cyfry, _ i .)',
    'an unexpected equals' => 'brak nazwy przed znakiem =',
    'an unexpected escape sequence' => 'niedozwolony znak \\ w wartości w cudzysłowie',
];

$parser = new Parser();
$errors = 0;
foreach (file($path, FILE_IGNORE_NEW_LINES) as $index => $line) {
    $trimmed = trim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '#')) {
        continue;
    }
    try {
        // An unterminated quote opens a multi-line value, so the line yields no entry instead of an error.
        if ($parser->parse($line) === []) {
            printf("Linia %d: %s\n", $index + 1, $categories['a missing closing quote']);
            $errors++;
        }
    } catch (InvalidFileException $error) {
        $category = 'inny błąd składni';
        foreach ($categories as $needle => $label) {
            if (str_contains($error->getMessage(), 'Encountered '.$needle)) {
                $category = $label;
                break;
            }
        }
        printf("Linia %d: %s\n", $index + 1, $category);
        $errors++;
    }
}

if ($errors === 0) {
    // The application parses the whole file at once; a multi-line value can fail only here.
    try {
        $parser->parse((string)file_get_contents($path));
    } catch (InvalidFileException) {
        echo "Błąd obejmuje kilka linii (np. wartość wieloliniowa lub cudzysłów otwarty w jednej linii i zamknięty w innej).\n";
        $errors++;
    }
}
if ($errors === 0) {
    echo "Składnia pliku środowiska jest poprawna.\n";
}
exit($errors === 0 ? 0 : 1);
