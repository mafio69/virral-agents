<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

final class SearchException extends \RuntimeException
{
    /** Page fetch failures; closed list shown to the user and passed to the model as evidence. */
    public const FETCH_REASONS = [
        'dns_failed', 'private_address', 'timeout', 'connection_failed', 'http_blocked', 'http_not_found', 'http_error',
        'redirect', 'too_many_redirects', 'redirect_loop', 'insecure_redirect', 'unsupported_type', 'unsupported_encoding', 'too_large', 'charset_error', 'empty_content',
    ];

    public readonly string $reason;
    public readonly string $stage;

    public function __construct(string $reason = 'unavailable', string $stage = 'unknown')
    {
        $reasons = ['unavailable', 'missing_configuration', 'connection_refused', 'protocol_error', 'tool_error', 'provider_response', ...self::FETCH_REASONS];
        $this->reason = in_array($reason, $reasons, true) ? $reason : 'unavailable';
        $this->stage = in_array($stage, ['unknown', 'definition', 'initialize', 'initialized', 'tools_list', 'tools_call', 'search_result', 'fetch'], true) ? $stage : 'unknown';
        parent::__construct('Wyszukiwanie jest niedostępne.');
    }
}
