<?php

declare(strict_types=1);

namespace App\Service;

use App\Infrastructure\Search\SearchClientInterface;
use App\Infrastructure\Search\SearchException;

final readonly class SearchDiagnostics implements SearchDiagnosticsInterface
{
    public function __construct(private SearchClientInterface $client, private string $url, private ?\Closure $resolve = null)
    {
    }

    public function status(): array
    {
        $host = parse_url($this->url, PHP_URL_HOST);
        $target = match (true) {
            in_array($host, ['127.0.0.1', 'localhost', '::1'], true) => 'loopback',
            $host === 'mcp-search' => 'docker-service',
            default => 'custom',
        };
        if ($target === 'docker-service') {
            $addresses = $this->resolve !== null ? ($this->resolve)($host) : gethostbynamel($host);
            if (!$addresses) {
                return ['available' => false, 'target' => $target, 'reason' => 'dns_failed', 'stage' => 'initialize', 'searched' => false];
            }
        }
        try {
            $definition = $this->client->definition();
            return ['available' => true, 'target' => $target, 'tool' => $definition['function']['name'] ?? null, 'searched' => false];
        } catch (SearchException $error) {
            $reason = in_array($error->reason, ['dns_failed', 'connection_refused', 'timeout', 'connection_failed', 'http_error', 'protocol_error'], true) ? $error->reason : 'protocol_error';
            return ['available' => false, 'target' => $target, 'reason' => $reason, 'stage' => $error->stage, 'searched' => false];
        } finally {
            $this->client->close();
        }
    }
}
