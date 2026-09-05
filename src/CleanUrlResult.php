<?php

declare(strict_types=1);

/**
 * Immutable clean URL result.
 * File: src/CleanUrlResult.php
 */

namespace Willio\CleanUrlNormalizer;

final class CleanUrlResult
{
    /**
     * @param list<string> $removedParameters
     * @param list<string> $warnings
     * @param list<string> $validationErrors
     */
    public function __construct(
        private readonly string $originalUrl,
        private readonly ?string $cleanUrl,
        private readonly ?string $comparisonKey,
        private readonly array $removedParameters,
        private readonly array $warnings,
        private readonly array $validationErrors
    ) {
    }

    public function originalUrl(): string
    {
        return $this->originalUrl;
    }

    public function cleanUrl(): ?string
    {
        return $this->cleanUrl;
    }

    public function comparisonKey(): ?string
    {
        return $this->comparisonKey;
    }

    /** @return list<string> */
    public function removedParameters(): array
    {
        return $this->removedParameters;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /** @return list<string> */
    public function validationErrors(): array
    {
        return $this->validationErrors;
    }

    public function isValid(): bool
    {
        return $this->validationErrors === [];
    }
}
