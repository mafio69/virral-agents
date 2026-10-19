<?php

declare(strict_types=1);

// Loopback-only contract doubles. No real WWW, provider credentials or user data.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
header('Content-Type: application/json');
$counterFile = __DIR__.'/runtime/counters.json';
$counts = is_file($counterFile) ? json_decode(file_get_contents($counterFile), true) : ['search' => 0, 'fetch' => [], 'ai' => 0];
function countCall(string $kind, ?string $url = null): void
{
    global $counts, $counterFile;
    if ($kind === 'fetch') {
        $counts['fetch'][] = $url;
    } else {
        ++$counts[$kind];
    }
    file_put_contents($counterFile, json_encode($counts));
}
if ($path === '/health') {
    echo '{}';
} elseif ($path === '/counts') {
    echo json_encode($counts);
} elseif ($path === '/mcp') {
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        http_response_code(204);
        return;
    }
    $rpc = json_decode(file_get_contents('php://input'), true, flags: JSON_THROW_ON_ERROR);
    $method = $rpc['method'];
    if ($method === 'notifications/initialized') {
        http_response_code(202);
        return;
    }
    header('Mcp-Session-Id: e2e-fixture-session');
    if ($method === 'initialize') {
        $result = ['protocolVersion' => $rpc['params']['protocolVersion'], 'capabilities' => ['tools' => (object)[]], 'serverInfo' => ['name' => 'e2e-mcp', 'version' => '1']];
    } elseif ($method === 'tools/list') {
        $result = ['tools' => [['name' => 'web_search', 'inputSchema' => ['type' => 'object', 'properties' => [
            'query' => ['type' => 'string'], 'max_results' => ['type' => 'integer'],
        ], 'required' => ['query']]]]];
    } elseif ($method === 'tools/call') {
        countCall('search');
        $query = $rpc['params']['arguments']['query'];
        $prefix = str_contains($query, 'drugie') ? 'b' : 'a';
        $body = ['status' => 'ok', 'source' => 'searxng', 'results' => array_map(static fn (int $i): array => [
            'title' => 'Źródło '.$prefix.$i, 'url' => 'https://example.org/'.$prefix.$i, 'snippet' => 'Syntetyczny opis '.$prefix.$i,
        ], range(1, $prefix === 'a' ? 4 : 2)), 'errors' => []];
        if (str_contains($query, 'empty')) {
            $body = ['status' => 'empty', 'source' => null, 'results' => [], 'errors' => []];
        } elseif (str_contains($query, 'error')) {
            $body = ['status' => 'error', 'source' => null, 'results' => [], 'errors' => ['searxng_timeout', 'perplexity_no_key']];
        }
        $result = ['content' => [['type' => 'text', 'text' => json_encode($body)]]];
    } else {
        http_response_code(400);
        return;
    }
    echo json_encode(['jsonrpc' => '2.0', 'id' => $rpc['id'], 'result' => $result]);
} elseif ($path === '/v1beta/models') {
    echo json_encode(['models' => [['name' => 'models/gemini-e2e', 'displayName' => 'E2E Gemini', 'supportedGenerationMethods' => ['generateContent']]]]);
} elseif ($path === '/v1beta/models/gemini-e2e:generateContent') {
    countCall('ai');
    $body = json_decode(file_get_contents('php://input'), true, flags: JSON_THROW_ON_ERROR);
    $texts = [];
    foreach ($body['contents'] as $content) {
        foreach ($content['parts'] as $part) {
            if (isset($part['text'])) {
                $texts[] = $part['text'];
            }
        }
    }
    $latest = end($texts);
    $parts = [['text' => 'Odpowiedź E2E.']];
    if (str_contains($latest, 'dane JSON')) {
        $evidence = json_decode(substr($latest, strpos($latest, "\n") + 1), true, flags: JSON_THROW_ON_ERROR);
        $parts = [['text' => 'Opracowanie E2E: '.implode(', ', array_map(
            static fn (array $source): string => $source['status'] === 'loaded' ? $source['text'] : 'Niedostępne źródło',
            $evidence['sources'],
        ))]];
    }
    foreach ($texts as $text) {
        if ($text === 'AI poproś o zgodę' && !str_contains($latest, '"status":"denied"')) {
            $parts = [['functionCall' => ['name' => 'web_search', 'args' => ['query' => 'Publiczne zapytanie E2E']]]];
        }
    }
    echo json_encode(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => $parts]]]]);
} elseif ($path === '/page') {
    $url = $_GET['url'] ?? '';
    countCall('fetch', $url);
    if ($url === 'https://example.org/a4') {
        http_response_code(503);
        echo '{}';
    } else {
        echo json_encode(['url' => $url, 'text' => 'Treść E2E '.basename($url), 'truncated' => false]);
    }
} else {
    http_response_code(404);
    echo '{}';
}
