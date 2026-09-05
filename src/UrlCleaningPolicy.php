<?php

declare(strict_types=1);

/**
 * URL cleaning policy.
 * File: src/UrlCleaningPolicy.php
 */

namespace Willio\CleanUrlNormalizer;

final class UrlCleaningPolicy
{
    /** @var array<string, true> */
    private array $trackingParameters;

    /** @var array<string, string> */
    private array $hostAliases;

    /**
     * @param list<string> $trackingParameters
     * @param array<string, string> $hostAliases
     */
    public function __construct(
        array $trackingParameters = [],
        private readonly bool $stripUtmParameters = true,
        private readonly bool $lowercaseScheme = true,
        private readonly bool $lowercaseHost = true,
        private readonly bool $preserveFragmentInCleanUrl = true,
        private readonly bool $includeFragmentInComparisonKey = false,
        private readonly bool $normalizeTrailingSlashInComparisonKey = true,
        private readonly bool $normalizeDefaultPortInComparisonKey = true,
        array $hostAliases = []
    ) {
        $this->trackingParameters = [];
        foreach ($trackingParameters as $parameter) {
            $this->trackingParameters[strtolower($parameter)] = true;
        }

        $this->hostAliases = [];
        foreach ($hostAliases as $from => $to) {
            $this->hostAliases[strtolower($from)] = strtolower($to);
        }
    }

    public static function conservative(): self
    {
        return new self([
            'fbclid',
            'gclid',
            'igshid',
            'ttclid',
            'mc_cid',
            'mc_eid',
            '_hsenc',
        ]);
    }

    /** @param array<string, string> $aliases */
    public function withHostAliases(array $aliases): self
    {
        return new self(
            array_keys($this->trackingParameters),
            $this->stripUtmParameters,
            $this->lowercaseScheme,
            $this->lowercaseHost,
            $this->preserveFragmentInCleanUrl,
            $this->includeFragmentInComparisonKey,
            $this->normalizeTrailingSlashInComparisonKey,
            $this->normalizeDefaultPortInComparisonKey,
            $aliases
        );
    }

    public function shouldStripParameter(string $rawKey): bool
    {
        $decoded = rawurldecode(str_replace('+', ' ', $rawKey));
        $key = strtolower($decoded);

        return ($this->stripUtmParameters && str_starts_with($key, 'utm_'))
            || isset($this->trackingParameters[$key]);
    }

    public function normalizeScheme(string $scheme): string
    {
        return $this->lowercaseScheme ? strtolower($scheme) : $scheme;
    }

    public function normalizeHost(string $host): string
    {
        $host = $this->lowercaseHost ? strtolower($host) : $host;
        return $this->hostAliases[$host] ?? $host;
    }

    public function preserveFragmentInCleanUrl(): bool
    {
        return $this->preserveFragmentInCleanUrl;
    }

    public function includeFragmentInComparisonKey(): bool
    {
        return $this->includeFragmentInComparisonKey;
    }

    public function normalizeTrailingSlashInComparisonKey(): bool
    {
        return $this->normalizeTrailingSlashInComparisonKey;
    }

    public function normalizeDefaultPortInComparisonKey(): bool
    {
        return $this->normalizeDefaultPortInComparisonKey;
    }
}
