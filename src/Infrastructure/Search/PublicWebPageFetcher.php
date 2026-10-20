<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

/**
 * Follows at most MAX_REDIRECTS redirects, each one revalidated as a new public URL with fresh DNS.
 * Never forwards credentials or cookies, loads subresources or executes scripts.
 */
final readonly class PublicWebPageFetcher implements WebPageFetcherInterface
{
    private const MAX_REDIRECTS = 3;
    private const REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    public function __construct(private ?ClientInterface $http = null, private ?\Closure $resolve = null, private bool $useStreams = false)
    {
    }

    public function fetch(string $url): array
    {
        $url = PublicWebUrl::normalize($url);
        $deadline = microtime(true) + 15;
        $visited = [$url => true];
        try {
            $response = $this->request($url, $deadline);
            for ($hop = 0; in_array($response->getStatusCode(), self::REDIRECT_STATUSES, true); $hop++) {
                $location = $response->getHeaderLine('Location');
                $response->getBody()->close();
                if ($hop >= self::MAX_REDIRECTS) {
                    throw self::failure('too_many_redirects');
                }
                $url = self::redirectTarget($url, $location);
                if (isset($visited[$url])) {
                    throw self::failure('redirect_loop');
                }
                $visited[$url] = true;
                $response = $this->request($url, $deadline);
            }
            $stream = $response->getBody();
            $raw = '';
            try {
                $type = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'))[0]));
                $status = $response->getStatusCode();
                if ($status !== 200) {
                    throw self::failure(self::statusReason($status));
                }
                if (!in_array($type, ['text/html', 'text/plain'], true)) {
                    throw self::failure('unsupported_type');
                }
                if (!in_array(strtolower($response->getHeaderLine('Content-Encoding')), ['', 'identity'], true)) {
                    throw self::failure('unsupported_encoding');
                }
                if ((int)$response->getHeaderLine('Content-Length') > 1048576) {
                    throw self::failure('too_large');
                }
                while (!$stream->eof()) {
                    $raw .= $stream->read(8192);
                    if (strlen($raw) > 1048576) {
                        throw self::failure('too_large');
                    }
                    if (microtime(true) > $deadline) {
                        throw self::failure('timeout');
                    }
                }
            } finally {
                $stream->close();
            }
            $encoding = preg_match('//u', $raw) === 1 ? 'UTF-8' : 'Windows-1250';
            if (preg_match('/charset\s*=\s*["\x27]?([a-zA-Z0-9_-]+)/i', $response->getHeaderLine('Content-Type'), $charset)
                && in_array(strtolower($charset[1]), ['utf-8', 'iso-8859-1', 'iso-8859-2', 'windows-1250', 'windows-1252'], true)) {
                $encoding = $charset[1];
            }
            $converted = iconv($encoding, 'UTF-8//IGNORE', $raw);
            if ($converted === false) {
                throw self::failure('charset_error');
            }
            $raw = $converted;
            if ($type === 'text/html') {
                $document = new \DOMDocument();
                $previous = libxml_use_internal_errors(true);
                try {
                    $document->loadHTML('<?xml encoding="UTF-8">'.$raw, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
                } finally {
                    libxml_clear_errors();
                    libxml_use_internal_errors($previous);
                }
                $xpath = new \DOMXPath($document);
                foreach ($xpath->query('//script|//style|//noscript|//iframe|//svg|//nav|//footer|//head|//form') as $node) {
                    $node->parentNode?->removeChild($node);
                }
                foreach ($xpath->query('//p|//div|//li|//h1|//h2|//h3|//tr|//br|//section|//article') as $node) {
                    $node->appendChild($document->createTextNode("\n"));
                }
                $text = $document->textContent;
            } else {
                $text = $raw;
            }
            $text = trim(preg_replace('/[ \t]+/u', ' ', preg_replace('/\n\s*\n/u', "\n\n", $text)));
            if ($text === '') {
                // Typically a page rendered only by JavaScript, which is never executed here.
                throw self::failure('empty_content');
            }
            return ['url' => $url, 'text' => mb_substr($text, 0, 16000), 'truncated' => mb_strlen($text) > 16000];
        } catch (SearchException $error) {
            throw $error;
        } catch (\GuzzleHttp\Exception\ConnectException $error) {
            throw self::failure(($error->getHandlerContext()['errno'] ?? null) === 28 ? 'timeout' : 'connection_failed');
        } catch (\Throwable) {
            throw self::failure('connection_failed');
        }
    }

    /** Resolves and vets the host again for every hop, then connects only to the vetted IPv4. */
    private function request(string $url, float $deadline): \Psr\Http\Message\ResponseInterface
    {
        $host = parse_url($url, PHP_URL_HOST);
        $addresses = $this->resolve !== null ? ($this->resolve)($host) : gethostbynamel($host);
        if (!is_array($addresses) || $addresses === []) {
            throw self::failure('dns_failed');
        }
        foreach ($addresses as $ip) {
            if (!PublicWebUrl::publicIp($ip)) {
                throw self::failure('private_address');
            }
        }
        $remaining = $deadline - microtime(true);
        if ($remaining <= 0) {
            throw self::failure('timeout');
        }
        $port = parse_url($url, PHP_URL_PORT) ?? (parse_url($url, PHP_URL_SCHEME) === 'https' ? 443 : 80);
        $options = [
            'allow_redirects' => false, 'http_errors' => false, 'cookies' => false, 'proxy' => '', 'verify' => true,
            'connect_timeout' => min(5, $remaining), 'timeout' => $remaining, 'read_timeout' => 10, 'stream' => true, 'decode_content' => false,
            'headers' => ['Accept' => 'text/html, text/plain', 'Accept-Encoding' => 'identity', 'User-Agent' => 'VirralAgents-Research/1.0'],
        ];
        $requestUrl = $url;
        $streams = $this->useStreams || !extension_loaded('curl');
        if ($streams) {
            // PHP-FPM need not have ext-curl. Connect to the vetted IP, with original TLS identity.
            $parts = parse_url($url);
            $requestUrl = $parts['scheme'].'://'.$addresses[0].':'.$port.($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
            $options['headers']['Host'] = $host.(isset($parts['port']) ? ':'.$port : '');
            $options['stream_context'] = ['ssl' => ['peer_name' => $host, 'SNI_enabled' => true, 'verify_peer' => true, 'verify_peer_name' => true]];
        } else {
            $options['curl'] = [CURLOPT_RESOLVE => [$host.':'.$port.':'.$addresses[0]], CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, CURLOPT_FRESH_CONNECT => true, CURLOPT_FORBID_REUSE => true];
        }
        return ($this->http ?? new Client($streams ? ['handler' => new \GuzzleHttp\Handler\StreamHandler()] : []))->request('GET', $requestUrl, $options);
    }

    private static function redirectTarget(string $current, string $location): string
    {
        if ($location === '') {
            throw self::failure('redirect');
        }
        try {
            $target = PublicWebUrl::normalize((string)UriResolver::resolve(new Uri($current), new Uri($location)));
        } catch (\Throwable) {
            throw self::failure('redirect');
        }
        if (parse_url($current, PHP_URL_SCHEME) === 'https' && parse_url($target, PHP_URL_SCHEME) !== 'https') {
            throw self::failure('insecure_redirect');
        }
        return $target;
    }

    private static function failure(string $reason): SearchException
    {
        return new SearchException($reason, 'fetch');
    }

    private static function statusReason(int $status): string
    {
        return match (true) {
            $status >= 300 && $status < 400 => 'redirect',
            in_array($status, [401, 403, 451], true) => 'http_blocked',
            in_array($status, [404, 410], true) => 'http_not_found',
            $status === 408, $status === 504 => 'timeout',
            default => 'http_error',
        };
    }
}
