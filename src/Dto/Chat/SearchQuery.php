<?php

declare(strict_types=1);

namespace App\Dto\Chat;

use App\Exception\ValidationException;

/**
 * Structured search query: base text plus advanced filters. Filters are composed
 * into one query string by SearchQueryBuilder right before the MCP call.
 */
final readonly class SearchQuery
{
    public const MAX_QUERY_BYTES = 1000;
    public const MAX_TOKEN_CHARS = 64;
    public const MAX_TOKENS = 10;
    public const MAX_PHRASE_CHARS = 100;
    public const MAX_SITES = 5;

    private const MESSAGE = 'Nieprawidłowe parametry wyszukiwania.';
    private const TOKEN_PATTERN = '/^[\p{L}\p{N}][^"\x00-\x1F\x7F]*$/u';

    public function __construct(
        public string $query = '',
        public array $exclude = [],
        public array $mustHave = [],
        public string $exactPhrase = '',
        public array $sites = [],
    ) {
        if (strlen($query) > self::MAX_QUERY_BYTES) {
            throw new ValidationException(self::MESSAGE);
        }
        foreach ([$exclude, $mustHave] as $tokens) {
            if (count($tokens) > self::MAX_TOKENS) {
                throw new ValidationException(self::MESSAGE);
            }
            foreach ($tokens as $token) {
                self::assertToken($token);
            }
        }
        if ($exactPhrase !== '') {
            self::assertPhrase($exactPhrase);
        }
        if (count($sites) > self::MAX_SITES) {
            throw new ValidationException(self::MESSAGE);
        }
        foreach ($sites as $site) {
            self::assertSite($site);
        }
    }

    /**
     * Strict input shape: query, exclude[], must_have[], exact_phrase, sites[].
     * Values are normalized (trim, whitespace collapse, dedup, lowercase hosts).
     */
    public static function fromArray(mixed $input): self
    {
        if (!is_array($input) || (array_is_list($input) && $input !== [])
            || array_diff(array_keys($input), ['query', 'exclude', 'must_have', 'exact_phrase', 'sites']) !== []) {
            throw new ValidationException(self::MESSAGE);
        }
        $query = self::optionalString($input, 'query');
        $phrase = self::collapse(self::optionalString($input, 'exact_phrase'));
        return new self(
            trim($query),
            self::tokenList($input, 'exclude'),
            self::tokenList($input, 'must_have'),
            $phrase,
            self::siteList($input),
        );
    }

    /** Stable snake_case shape for persistence and evidence; empty filters are omitted. */
    public function toArray(): array
    {
        $result = ['query' => $this->query];
        if ($this->mustHave !== []) {
            $result['must_have'] = $this->mustHave;
        }
        if ($this->exclude !== []) {
            $result['exclude'] = $this->exclude;
        }
        if ($this->exactPhrase !== '') {
            $result['exact_phrase'] = $this->exactPhrase;
        }
        if ($this->sites !== []) {
            $result['sites'] = $this->sites;
        }
        return $result;
    }

    /** True when any advanced filter is set (drives SearchOptions approval rules). */
    public function hasFilters(): bool
    {
        return $this->exclude !== [] || $this->mustHave !== [] || $this->exactPhrase !== '' || $this->sites !== [];
    }

    private static function assertToken(mixed $token): void
    {
        if (!is_string($token) || $token === '' || str_starts_with($token, '-') || str_starts_with($token, '+')
            || mb_strlen($token) > self::MAX_TOKEN_CHARS
            || !preg_match(self::TOKEN_PATTERN, $token)) {
            throw new ValidationException(self::MESSAGE);
        }
    }

    private static function assertPhrase(string $phrase): void
    {
        if (mb_strlen($phrase) > self::MAX_PHRASE_CHARS || !preg_match(self::TOKEN_PATTERN, $phrase)) {
            throw new ValidationException(self::MESSAGE);
        }
    }

    private static function assertSite(mixed $site): void
    {
        if (!is_string($site) || !preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/', $site)) {
            throw new ValidationException(self::MESSAGE);
        }
    }

    private static function optionalString(array $input, string $key): string
    {
        if (!array_key_exists($key, $input)) {
            return '';
        }
        if (!is_string($input[$key])) {
            throw new ValidationException(self::MESSAGE);
        }
        return $input[$key];
    }

    private static function tokenList(array $input, string $key): array
    {
        if (!array_key_exists($key, $input)) {
            return [];
        }
        if (!is_array($input[$key]) || !array_is_list($input[$key]) || count($input[$key]) > self::MAX_TOKENS) {
            throw new ValidationException(self::MESSAGE);
        }
        $tokens = [];
        foreach ($input[$key] as $token) {
            if (!is_string($token)) {
                throw new ValidationException(self::MESSAGE);
            }
            $normalized = self::collapse($token);
            if ($normalized !== '') {
                $tokens[] = $normalized;
            }
        }
        return array_values(array_unique($tokens));
    }

    private static function siteList(array $input): array
    {
        if (!array_key_exists('sites', $input)) {
            return [];
        }
        if (!is_array($input['sites']) || !array_is_list($input['sites']) || count($input['sites']) > self::MAX_SITES) {
            throw new ValidationException(self::MESSAGE);
        }
        $sites = [];
        foreach ($input['sites'] as $site) {
            if (!is_string($site)) {
                throw new ValidationException(self::MESSAGE);
            }
            $sites[] = mb_strtolower(trim($site));
        }
        return array_values(array_unique($sites));
    }

    private static function collapse(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
