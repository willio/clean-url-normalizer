# Clean URL Normalizer

[English](README.md) · [Bahasa Indonesia](README.id.md) · [简体中文](README.zh-CN.md)

`README.md` is the canonical technical documentation. The linked translations mirror its contract and examples.

Remove tracking noise from the clean URL without losing the URL you received or the tokens your workflow needs.

By default, tracking parameters such as `utm_source`, `fbclid`, and `gclid` are removed from the clean URL and comparison key. The original input is always preserved byte-for-byte—including `utm_source`—and unknown, affiliate, and referral parameters remain available in the clean form.

`willio/clean-url-normalizer` is a small PHP 8.1+ library for URL cleaning and comparison. It is intended for import pipelines, display preparation, optional deduplication, and similar workflows where mutating the caller's original URL would be undesirable.

## What it does

The library keeps the original input byte-for-byte in `CleanUrlResult::originalUrl()`. For supported HTTP(S) URLs it can additionally produce:

- a clean URL with scheme/host normalization and explicitly configured generic tracking parameters removed;
- a deterministic comparison key with surviving raw query pairs sorted;
- the names of removed parameters;
- warnings when the library deliberately avoids asserting an equivalence;
- validation errors for unsupported or malformed input.

This gives callers three useful levels of information:

- `originalUrl()` is the exact URL received, including tracking and attribution data;
- `cleanUrl()` is a readable URL with configured tracking noise removed while meaningful query parameters remain;
- `comparisonKey()` is a deterministic key for cautious matching or deduplication.

It does **not** claim that two URLs with the same key are universally equivalent. Comparison behavior is a policy heuristic suitable only when its assumptions fit the caller's domain.

## Installation

Once the package is available through Packagist, install it with Composer:

```bash
composer require willio/clean-url-normalizer
```

The package has no runtime dependencies beyond PHP 8.1 or newer.

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

### Common links, before and after

The normalizer does not need to know what Google Maps, YouTube, Instagram, or an affiliate network means. It removes only the parameters covered by policy and keeps the destination parameters intact.

| Common input | `cleanUrl()` | `comparisonKey()` | Useful outcome |
| --- | --- | --- | --- |
| `https://www.google.com/maps/search/?api=1&query=Monas%2C+Jakarta&utm_source=share` | `https://www.google.com/maps/search/?api=1&query=Monas%2C+Jakarta` | `https://www.google.com/maps/search?api=1&query=Monas%2C+Jakarta` | Removes the sharing tracker while retaining the Maps destination and query. |
| `https://www.youtube.com/watch?v=dQw4w9WgXcQ&utm_source=share` | `https://www.youtube.com/watch?v=dQw4w9WgXcQ` | `https://www.youtube.com/watch?v=dQw4w9WgXcQ` | Keeps the video ID while removing campaign noise. |
| `https://www.instagram.com/p/ABC123/?igshid=tracking` | `https://www.instagram.com/p/ABC123/` | `https://www.instagram.com/p/ABC123` | Keeps the post URL and makes the trailing slash irrelevant for comparison. |
| `https://store.example.com/product?ref=creator&aff_id=abc&utm_source=instagram` | `https://store.example.com/product?ref=creator&aff_id=abc` | `https://store.example.com/product?aff_id=abc&ref=creator` | Preserves referral and affiliate tokens while removing `utm_source`. |

In each case, `originalUrl()` still returns the complete input exactly as received. This lets an importer store the source URL, show a cleaner link, and compare later variants without silently throwing away attribution data.

### Keep UTM parameters when the clean form needs them

UTM parameters are removable by default, not forbidden. If the clean URL must retain `utm_source` or other UTM values, configure the policy explicitly and continue stripping only the trackers you do not want:

```php
use Willio\CleanUrlNormalizer\CleanUrlNormalizer;
use Willio\CleanUrlNormalizer\UrlCleaningPolicy;

$policy = new UrlCleaningPolicy(
    trackingParameters: ['fbclid', 'gclid'],
    stripUtmParameters: false,
);

$result = (new CleanUrlNormalizer($policy))->clean(
    'https://example.com/article?utm_source=newsletter&fbclid=tracking'
);

$result->originalUrl();       // https://example.com/article?utm_source=newsletter&fbclid=tracking
$result->cleanUrl();          // https://example.com/article?utm_source=newsletter
$result->comparisonKey();     // https://example.com/article?utm_source=newsletter
$result->removedParameters(); // ['fbclid']
```

This policy model is useful for campaign reporting, affiliate attribution, import pipelines, display/share links, and cautious URL deduplication. The library never makes a network request or claims that matching comparison keys prove universal destination equivalence.

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

## Provenance and license

The comparison behavior was extracted from Linkee's first-party `app/core/import-url.php` implementation, introduced in Linkee commit `a0e10e5aeb16bb64a0b281744b3972662e291a9f` (`fix(import): add canonical URL comparison and link dedup`). Linkee itself currently declares a proprietary license.

This standalone package is licensed under the MIT License. The original Linkee application remains proprietary; this repository contains only the reusable URL-cleaning and comparison layer, its tests, and its documentation. The extraction contract was reviewed against Linkee's internal URL Equivalence design draft, which is not included here.

## Development

```bash
composer test
```

The current suite is dependency-free and exercises the inherited Linkee comparison cases plus conservative edge cases around tracking, affiliate/referral parameters, query ordering, repeats, empty values, fragments, ports, IPv6, userinfo, schemes, encoded paths, IDNs, aliases, and optional deduplication.
