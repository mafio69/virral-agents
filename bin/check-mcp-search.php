<?php

declare(strict_types=1);

use App\Infrastructure\Search\McpSearchClient;

require __DIR__.'/../vendor/autoload.php';

// Deliberately does not load application settings, .env, credentials or database.
$options = getopt('', ['url:', 'query:', 'approve-search']);
$url = $options['url'] ?? 'http://127.0.0.1:8890/mcp';
$query = $options['query'] ?? null;
// The token comes only from the process environment (export MCP_SEARCH_TOKEN), never from .env.
$envToken = getenv('MCP_SEARCH_TOKEN');
$token = is_string($envToken) ? $envToken : '';

function isValidUrl(mixed $url): bool
{
    if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $parts = parse_url($url);

    return in_array($parts['scheme'] ?? null, ['http', 'https'], true)
        && !isset($parts['user'])
        && !isset($parts['pass']);
}

function isLoopbackHost(string $url): bool
{
    return in_array(parse_url($url, PHP_URL_HOST), ['127.0.0.1', 'localhost', '[::1]'], true);
}

function isValidQuery(mixed $query, bool $approved): bool
{
    return $approved
        && is_string($query)
        && trim($query) !== ''
        && strlen($query) <= 1000;
}

$urlValid = isValidUrl($url);
$queryValid = $query === null || isValidQuery($query, array_key_exists('approve-search', $options));
$tokenSafe = !$urlValid
    || $token === ''
    || parse_url($url, PHP_URL_SCHEME) === 'https'
    || isLoopbackHost($url);

if (!$urlValid || !$queryValid) {
    fwrite(STDERR, "Użycie: php bin/check-mcp-search.php [--url=URL] [--query='publiczne zapytanie' --approve-search]\nBez --query skrypt tylko sprawdza protokół i narzędzie, nie wyszukuje WWW.\n");
    exit(2);
}
if (!$tokenSafe) {
    fwrite(STDERR, "MCP_SEARCH_TOKEN nie zostanie wysłany jawnym tekstem: użyj https albo adresu lokalnego (127.0.0.1, localhost).\n");
    exit(2);
}
$client = new McpSearchClient($url, token: $token);
$exitCode = 0;
try {
    $definition = $client->definition();
    echo 'MCP OK: '.$definition['function']['name'].PHP_EOL;
    if ($query !== null) {
        $result = $client->search($query, 3);
        echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
        if ($result['results'] === []) {
            $exitCode = 1;
        }
    }
} catch (\App\Infrastructure\Search\SearchException $error) {
    fwrite(STDERR, "MCP niedostępne (kod: {$error->reason}, etap: {$error->stage}).\n");
    $exitCode = 1;
} catch (Throwable) {
    fwrite(STDERR, "MCP niedostępne lub niepoprawna odpowiedź protokołu.\n");
    $exitCode = 1;
} finally {
    $client->close();
}
exit($exitCode);
