<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

final class SearchException extends \RuntimeException
{
    public readonly string $reason;
    public readonly string $stage;

    public function __construct(string $reason = 'unavailable', string $stage = 'unknown')
    {
        $this->reason = in_array($reason, ['unavailable', 'missing_configuration', 'dns_failed', 'connection_refused', 'timeout', 'connection_failed', 'http_error', 'protocol_error', 'tool_error', 'provider_response'], true) ? $reason : 'unavailable';
        $this->stage = in_array($stage, ['unknown', 'definition', 'initialize', 'initialized', 'tools_list', 'tools_call', 'search_result'], true) ? $stage : 'unknown';
        parent::__construct('Wyszukiwanie jest niedostępne.');
    }
}
