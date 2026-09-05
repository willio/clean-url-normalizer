# Changelog

All notable changes to this package are documented here.

## [Unreleased]

## [0.1.0] - 2026-09-05

Initial standalone release candidate.

- Preserve the original URL exactly as supplied.
- Produce conservative clean URLs and deterministic comparison keys.
- Remove only explicitly supported generic tracking parameters by default.
- Preserve unknown, affiliate, referral, repeated, and empty query parameters.
- Report validation errors and equivalence warnings without making network requests.
- Provide opt-in host aliases and a thin first-original-url deduplication helper.
