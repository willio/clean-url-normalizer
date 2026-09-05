<?php

declare(strict_types=1);

/**
 * Policy-driven URL cleaner and comparison-key generator.
 * File: src/CleanUrlNormalizer.php
 */

namespace Willio\CleanUrlNormalizer;

final class CleanUrlNormalizer
{
    public function __construct(private readonly ?UrlCleaningPolicy $policy = null)
    {
    }

    public function clean(string $url): CleanUrlResult
    {
        $policy = $this->policy ?? UrlCleaningPolicy::conservative();
        $original = $url;
        $input = trim($url);
        $warnings = [];

        if ($input !== $original) {
            $warnings[] = 'Leading or trailing whitespace was ignored while parsing.';
        }

        if ($input === '') {
            return new CleanUrlResult($original, null, null, [], $warnings, ['URL is empty.']);
        }

        $parts = parse_url($input);
        if (!is_array($parts)) {
            return new CleanUrlResult($original, null, null, [], $warnings, ['URL could not be parsed.']);
        }

        $scheme = isset($parts['scheme']) ? (string)$parts['scheme'] : '';
        if ($scheme === '' || !in_array(strtolower($scheme), ['http', 'https'], true)) {
            return new CleanUrlResult(
                $original,
                null,
                null,
                [],
                $warnings,
                [$scheme === '' ? 'URL must include an http or https scheme.' : 'Unsupported URL scheme: ' . $scheme . '.']
            );
        }

        if (!isset($parts['host']) || (string)$parts['host'] === '') {
            return new CleanUrlResult($original, null, null, [], $warnings, ['URL must include a host.']);
        }

        $rawHost = (string)$parts['host'];
        if (preg_match('/[\x00-\x20\x7F]/u', $rawHost) === 1) {
            return new CleanUrlResult($original, null, null, [], $warnings, ['URL host contains invalid whitespace or control characters.']);
        }

        $scheme = $policy->normalizeScheme($scheme);
        $host = $policy->normalizeHost((string)$parts['host']);
        [$cleanQuery, $comparisonQuery, $removed] = $this->processQuery((string)($parts['query'] ?? ''), $policy);

        $cleanAuthority = $this->buildAuthority($parts, $host, $scheme, false, $policy);
        $comparisonAuthority = $this->buildAuthority($parts, $host, $scheme, true, $policy);
        $path = (string)($parts['path'] ?? '');
        $comparisonPath = $path;

        if ($policy->normalizeTrailingSlashInComparisonKey()) {
            $comparisonPath = $path === '/' ? '' : rtrim($path, '/');
        }

        $clean = $scheme . '://' . $cleanAuthority . $path;
        if ($cleanQuery !== '') {
            $clean .= '?' . $cleanQuery;
        }
        if ($policy->preserveFragmentInCleanUrl() && isset($parts['fragment'])) {
            $clean .= '#' . (string)$parts['fragment'];
        }

        $comparison = $scheme . '://' . $comparisonAuthority . $comparisonPath;
        if ($comparisonQuery !== '') {
            $comparison .= '?' . $comparisonQuery;
        }
        if ($policy->includeFragmentInComparisonKey() && isset($parts['fragment'])) {
            $comparison .= '#' . (string)$parts['fragment'];
        }

        if (preg_match('/[^\x00-\x7F]/', $host) === 1) {
            $warnings[] = 'Unicode host preserved as supplied; no IDNA equivalence is inferred.';
        }

        return new CleanUrlResult($original, $clean, $comparison, $removed, $warnings, []);
    }

    /**
     * Deduplicate URL strings by comparison key, retaining the first original string.
     * Invalid inputs are retained because no safe comparison key can be established.
     *
     * @param iterable<string> $urls
     * @return array{urls: list<string>, duplicates_removed: int}
     */
    public function deduplicate(iterable $urls): array
    {
        $unique = [];
        $seen = [];
        $duplicatesRemoved = 0;

        foreach ($urls as $url) {
            $result = $this->clean($url);
            $key = $result->comparisonKey();

            if ($key === null) {
                $unique[] = $url;
                continue;
            }

            if (isset($seen[$key])) {
                $duplicatesRemoved++;
                continue;
            }

            $seen[$key] = true;
            $unique[] = $url;
        }

        return ['urls' => $unique, 'duplicates_removed' => $duplicatesRemoved];
    }

    /**
     * @return array{0: string, 1: string, 2: list<string>}
     */
    private function processQuery(string $query, UrlCleaningPolicy $policy): array
    {
        if ($query === '') {
            return ['', '', []];
        }

        $kept = [];
        $removed = [];

        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                $kept[] = $pair;
                continue;
            }

            [$rawKey] = array_pad(explode('=', $pair, 2), 2, '');
            if ($policy->shouldStripParameter($rawKey)) {
                $removed[] = rawurldecode(str_replace('+', ' ', $rawKey));
                continue;
            }

            $kept[] = $pair;
        }

        $clean = implode('&', $kept);
        $comparisonPairs = array_values(array_filter($kept, static fn(string $pair): bool => $pair !== ''));
        sort($comparisonPairs, SORT_STRING);

        return [$clean, implode('&', $comparisonPairs), $removed];
    }

    /** @param array<string, mixed> $parts */
    private function buildAuthority(
        array $parts,
        string $host,
        string $scheme,
        bool $comparison,
        UrlCleaningPolicy $policy
    ): string {
        $authority = '';

        if (isset($parts['user'])) {
            $authority .= (string)$parts['user'];
            if (isset($parts['pass'])) {
                $authority .= ':' . (string)$parts['pass'];
            }
            $authority .= '@';
        }

        $authority .= $this->formatHost($host);

        if (isset($parts['port'])) {
            $port = (int)$parts['port'];
            $isDefault = ($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443);
            if (!$comparison || !$policy->normalizeDefaultPortInComparisonKey() || !$isDefault) {
                $authority .= ':' . $port;
            }
        }

        return $authority;
    }

    private function formatHost(string $host): string
    {
        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            return '[' . $host . ']';
        }

        return $host;
    }
}
