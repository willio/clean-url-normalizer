# Clean URL Normalizer

Preserve the URL you received. Produce a conservative, policy-driven clean form and a deterministic comparison key when it is safe to do so.

`willio/clean-url-normalizer` is a small PHP 8.1+ library for URL cleaning and comparison. It is intended for import pipelines, display preparation, optional deduplication, and similar workflows where mutating the caller's original URL would be undesirable.

## What it does

The library keeps the original input byte-for-byte in `CleanUrlResult::originalUrl()`. For supported HTTP(S) URLs it can additionally produce:

- a clean URL with scheme/host normalization and explicitly configured generic tracking parameters removed;
- a deterministic comparison key with surviving raw query pairs sorted;
- the names of removed parameters;
- warnings when the library deliberately avoids asserting an equivalence;
- validation errors for unsupported or malformed input.

It does **not** claim that two URLs with the same key are universally equivalent. Comparison behavior is a policy heuristic suitable only when its assumptions fit the caller's domain.

## Conservative defaults

`UrlCleaningPolicy::conservative()`:

- supports explicit `http://` and `https://` URLs only;
- lowercases scheme and host;
- removes `utm_*`, `fbclid`, `gclid`, `igshid`, `ttclid`, `mc_cid`, `mc_eid`, and `_hsenc`;
- preserves unknown, affiliate, and referral parameters by default;
- preserves repeated query parameters;
- preserves surviving query order in the clean URL;
- sorts surviving raw query pairs only in the comparison key;
- preserves fragments in the clean URL but omits them from the comparison key;
- normalizes an explicit default port only in the comparison key;
- treats trailing slash normalization as a comparison heuristic;
- does not infer host aliases unless the caller opts in;
- preserves userinfo instead of silently discarding it;
- preserves Unicode/punycode host spelling as supplied and does not infer IDNA equivalence;
- does not decode or normalize path percent-encoding.

## Usage

```php
use Willio\CleanUrlNormalizer\CleanUrlNormalizer;

$normalizer = new CleanUrlNormalizer();
$result = $normalizer->clean('https://Example.com/item?utm_source=ig&ref=alice');

$result->originalUrl();       // exact caller input
$result->cleanUrl();          // https://example.com/item?ref=alice
$result->comparisonKey();     // https://example.com/item?ref=alice
$result->removedParameters(); // ['utm_source']
$result->warnings();
$result->validationErrors();
$result->isValid();
```

Host aliases are opt-in because provider/domain aliases are contextual rather than universal:

```php
use Willio\CleanUrlNormalizer\CleanUrlNormalizer;
use Willio\CleanUrlNormalizer\UrlCleaningPolicy;

$policy = UrlCleaningPolicy::conservative()->withHostAliases([
    'twitter.com' => 'x.com',
]);

$normalizer = new CleanUrlNormalizer($policy);
```

## Optional deduplication

`CleanUrlNormalizer::deduplicate()` accepts URL strings, compares only inputs that produce a valid comparison key, and retains the first original string exactly. Unsupported inputs are retained rather than deduplicated speculatively.

## Non-goals and security boundary

This package performs no network requests. It does not resolve redirects, DNS, HTTP status, shortened URLs, provider identities, or SSRF policy. It contains no Linkee provider detection, import fetcher, LLM extraction, Creator Agent, Oversight, block normalization, authentication, commerce, database, environment, credential, storage, or production-data logic.

Consumers that fetch URLs must apply their own network and SSRF controls separately.

## Provenance and license status

The comparison behavior was extracted from Linkee's first-party `app/core/import-url.php` implementation, introduced in Linkee commit `a0e10e5aeb16bb64a0b281744b3972662e291a9f` (`fix(import): add canonical URL comparison and link dedup`). Linkee itself currently declares a proprietary license.

MIT is proposed for this standalone package, but relicensing is not yet approved and this draft intentionally contains no `LICENSE` file. The local-only Linkee document `packages/url-equivalence/README.md` was not available through the connected repository source during extraction and remains a provenance review gate before relicensing or publication.

## Development

```bash
composer test
```

The current suite is dependency-free and exercises the inherited Linkee comparison cases plus conservative edge cases around tracking, affiliate/referral parameters, query ordering, repeats, empty values, fragments, ports, IPv6, userinfo, schemes, encoded paths, IDNs, aliases, and optional deduplication.
