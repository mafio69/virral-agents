<?php
declare(strict_types=1);

// Local-only integration fixture. Every search, page and AI reply is synthetic.
require __DIR__.'/../../vendor/autoload.php';

use App\Dto\AI\AiReply;
use App\Infrastructure\AI\AiClientInterface;
use App\Infrastructure\AI\ChatModelRegistryInterface;
use App\Infrastructure\AI\ChatProviderInterface;
use App\Infrastructure\AI\ProviderRegistry;
use App\Infrastructure\AI\WebToolClientInterface;
use App\Infrastructure\DataProtection\DataProtectionMigration;
use App\Infrastructure\DataProtection\SodiumDataCipher;
use App\Infrastructure\Database\MigrationRunner;
use App\Infrastructure\Search\SearchClientInterface;
use App\Infrastructure\Search\WebPageFetcherInterface;
use App\Repository\ChatRepository;
use App\Repository\ResearchRepository;
use App\Service\ChatReplyGenerator;
use App\Service\ResearchReplyGenerator;
use App\Service\ResearchService;

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/' || $path === '/chat') {
    header('Content-Type: text/html; charset=UTF-8');
    readfile(__DIR__.'/../../public/ui/chat.html');
    return;
}
if (preg_match('~^/ui/(?:theme\.css|lib/(?:marked\.umd\.js|purify\.min\.js)|chat/[a-z-]+\.(?:js|css))$~D', $path)) {
    header('Content-Type: '.(str_ends_with($path, '.css') ? 'text/css' : 'text/javascript').'; charset=UTF-8');
    readfile(__DIR__.'/../../public'.$path);
    return;
}
if (!str_starts_with($path, '/api/')) { http_response_code(404); return; }
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$fixtureDatabase = getenv('RESEARCH_FIXTURE_DB') ?: __DIR__.'/research-fixture.sqlite';
$pdo = new PDO('sqlite:'.$fixtureDatabase, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
(new MigrationRunner($pdo))->run();
$cipher = new SodiumDataCipher(str_repeat('ab', 32)); // Dummy key for synthetic fixture data only.
(new DataProtectionMigration($pdo, $cipher))->initializeEmptyDatabase();
$chats = new ChatRepository($pdo, $cipher);
$search = new class implements SearchClientInterface {
    public function definition(): array { return []; }
    public function close(): void {}
    public function search(string $query, int $maxResults): array {
        $prefix = str_contains(strtolower($query), 'drugie') ? 'b' : 'a';
        return ['source' => 'searxng', 'results' => array_map(static fn(int $i): array => [
            'title' => 'Strona testowa '.$prefix.$i, 'url' => 'https://example.org/'.$prefix.$i, 'snippet' => 'Fikcyjny fragment źródła '.$prefix.$i.'. Wybierz checkbox, aby dodać stronę do badania.',
        ], range(1, $prefix === 'a' ? 4 : 2))];
    }
};
$fetch = new class implements WebPageFetcherInterface {
    public function fetch(string $url): array { return ['url' => $url, 'text' => 'Fikcyjna pobrana treść strony '.$url.'. Materiał testowy do analizy.', 'truncated' => false]; }
};
$research = new ResearchService(new ResearchRepository($pdo, $cipher), $chats, $search, $fetch);
$provider = new class implements ChatProviderInterface, WebToolClientInterface {
    public function id(): string { return 'google'; }
    public function name(): string { return 'MODEL TESTOWY — bez sieci'; }
    public function listModels(): array { return [['id' => 'gemini-fixture', 'name' => 'Model testowy — fikcyjne odpowiedzi']]; }
    public function generate(array $messages, ?string $model = null): string { return 'Opracowanie testowe na podstawie wybranych stron.'.str_repeat("\n\n## Fragment odpowiedzi testowej\n\nTo fikcyjny tekst do ręcznego sprawdzenia przewijania czatu. Źródła są zapisane poza układem rozmowy.", 24); }
    public function generateWebReply(array $messages, string $model): AiReply {
        foreach (array_reverse($messages) as $message) {
            if ($message['role'] === 'user' && is_string($message['content']) && str_contains($message['content'], 'AI poproś')) return new AiReply('', webRequest: ['kind' => 'search', 'query' => 'Publiczne zapytanie zaproponowane przez AI']);
        }
        return new AiReply($this->generate($messages, $model));
    }
};
$legacy = new class implements ChatModelRegistryInterface {
    public function listModels(): array { return []; }
    public function resolve(string $selector): \App\Dto\AI\AiModel { throw new \RuntimeException('Fixture model unavailable'); }
    public function clientFor(\App\Dto\AI\AiModel $model): AiClientInterface { throw new \RuntimeException('Fixture model unavailable'); }
};
$plain = new ChatReplyGenerator($search);
$service = new \App\Service\ChatService($chats, new \App\Infrastructure\AI\OvhAiClient('https://example.invalid/', '', 'Qwen3.6-27B'), new \Psr\Log\NullLogger(),
    new ProviderRegistry($legacy, ['google' => $provider]), $plain, $research, new ResearchReplyGenerator($research, $plain));
$data = json_decode(file_get_contents('php://input') ?: '{}', true, 64, JSON_THROW_ON_ERROR);
$method = $_SERVER['REQUEST_METHOD'];
try {
    if ($path === '/api/ai-connections') $result = [['id' => 'google', 'name' => $provider->name()]];
    elseif ($path === '/api/ai-models') $result = $service->getAvailableModels('google');
    elseif ($path === '/api/chats' && $method === 'GET') $result = $service->getUserChats(1);
    elseif ($path === '/api/chats' && $method === 'POST') $result = $service->createChat((new \App\Mapper\Chat\CreateChatInputMapper())->map($data, 1));
    elseif (preg_match('~^/api/chats/(\d+)/messages$~', $path, $matches)) $result = $service->addMessage((new \App\Mapper\Chat\AddChatMessageInputMapper())->map($data, (int)$matches[1], 1));
    elseif (preg_match('~^/api/chats/(\d+)$~', $path, $matches)) $result = $service->getChatWithMessages((int)$matches[1], 1);
    elseif (str_starts_with($path, '/api/research')) {
        $operation = match ($path) { '/api/research' => 'state', '/api/research/actions' => 'propose', '/api/research/decisions' => 'decide', '/api/research/sources' => 'select', '/api/research/prepare' => 'prepare', default => throw new \RuntimeException('Fixture route unavailable') };
        [$scope, $args] = (new \App\Mapper\Chat\ResearchInputMapper())->map($operation, $_GET, $data);
        $result = $research->{$operation}(1, $scope, ...$args);
    } else { http_response_code(404); $result = ['error' => 'Fixture route unavailable']; }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $error) { http_response_code(422); echo json_encode(['error' => 'Fixture failure', 'type' => $error::class], JSON_THROW_ON_ERROR); }
