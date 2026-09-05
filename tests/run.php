<?php

declare(strict_types=1);

/**
 * Dependency-free test suite.
 * File: tests/run.php
 */

require dirname(__DIR__) . '/src/UrlCleaningPolicy.php';
require dirname(__DIR__) . '/src/CleanUrlResult.php';
require dirname(__DIR__) . '/src/CleanUrlNormalizer.php';

use Willio\CleanUrlNormalizer\CleanUrlNormalizer;
use Willio\CleanUrlNormalizer\UrlCleaningPolicy;

$failures = [];
$assertions = 0;

$assertSame = static function (mixed $expected, mixed $actual, string $label) use (&$failures, &$assertions): void {
    $assertions++;
    if ($expected !== $actual) {
        $failures[] = $label . "\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true);
    }
};

$assertTrue = static function (bool $actual, string $label) use (&$failures, &$assertions): void {
    $assertions++;
    if (!$actual) {
        $failures[] = $label . '\n  expected true';
    }
};

$n = new CleanUrlNormalizer();

$base = $n->clean('https://example.com/item?a=1&utm_source=instagram&b=2');
$variant = $n->clean('https://example.com/item/?b=2&a=1&fbclid=tracking');
$assertSame($base->comparisonKey(), $variant->comparisonKey(), 'Linkee comparison case remains equivalent.');
$assertSame('https://example.com/item?a=1&b=2', $base->cleanUrl(), 'Clean form removes tracking but preserves surviving order.');
$assertSame(['utm_source'], $base->removedParameters(), 'Removed tracking parameter is reported.');

$affiliate = $n->clean('https://shopee.co.id/product?af_code=abc&sf_cc=creator&utm_campaign=social');
$assertSame('https://shopee.co.id/product?af_code=abc&sf_cc=creator', $affiliate->cleanUrl(), 'Affiliate/referral parameters are preserved by default.');
$assertSame(['utm_campaign'], $affiliate->removedParameters(), 'UTM wildcard is removed.');

$assertTrue(
    $n->clean('https://www.example.com')->comparisonKey() !== $n->clean('https://example.com')->comparisonKey(),
    'Host aliases are not assumed by default.'
);

$repeated = $n->clean('https://example.com?tag=b&tag=a');
$assertSame('https://example.com?tag=b&tag=a', $repeated->cleanUrl(), 'Repeated parameters preserve clean-form order.');
$assertSame('https://example.com?tag=a&tag=b', $repeated->comparisonKey(), 'Repeated parameters sort deterministically for comparison.');

$emptyValues = $n->clean('https://example.com?a=&b&c=3');
$assertSame('https://example.com?a=&b&c=3', $emptyValues->cleanUrl(), 'Empty and valueless parameters are preserved.');

$emptyComponents = $n->clean('https://example.com?a=1&&b=2&');
$assertSame('https://example.com?a=1&&b=2&', $emptyComponents->cleanUrl(), 'Empty query components remain in clean form.');
$assertSame('https://example.com?a=1&b=2', $emptyComponents->comparisonKey(), 'Empty query components do not affect comparison.');

$encodedTracker = $n->clean('https://example.com?UTM%5Fsource=x&ref=y');
$assertSame('https://example.com?ref=y', $encodedTracker->cleanUrl(), 'Encoded tracker names are recognized conservatively.');

$plusTracker = $n->clean('https://example.com?utm%5Fmedium=x&ref=y');
$assertSame('https://example.com?ref=y', $plusTracker->cleanUrl(), 'Percent-encoded underscore tracker names are removed.');

$fragment = $n->clean('HTTPS://Example.COM/path#section');
$assertSame('https://example.com/path#section', $fragment->cleanUrl(), 'Scheme/host lowercase and fragment preserved in clean form.');
$assertSame('https://example.com/path', $fragment->comparisonKey(), 'Fragments are omitted from comparison by default.');

$ports = $n->clean('https://Example.com:443/path');
$assertSame('https://example.com:443/path', $ports->cleanUrl(), 'Explicit default port remains visible in clean form.');
$assertSame('https://example.com/path', $ports->comparisonKey(), 'Default port is normalized in comparison key.');
$assertSame('https://example.com:8443/path', $n->clean('https://example.com:8443/path')->comparisonKey(), 'Non-default port remains significant.');

$ipv6 = $n->clean('https://[2001:db8::1]:8443/a');
$assertSame('https://[2001:db8::1]:8443/a', $ipv6->cleanUrl(), 'IPv6 authority is preserved correctly.');

$userInfo = $n->clean('https://user:pass@Example.com/path');
$assertSame('https://user:pass@example.com/path', $userInfo->cleanUrl(), 'Userinfo is preserved rather than silently discarded.');
$assertSame('https://user:pass@example.com/path', $userInfo->comparisonKey(), 'Userinfo remains comparison-significant.');

$invalidScheme = $n->clean('ftp://example.com/file');
$assertSame(false, $invalidScheme->isValid(), 'Unsupported scheme is invalid.');
$assertSame(null, $invalidScheme->cleanUrl(), 'Unsupported scheme has no clean form.');

$missingScheme = $n->clean('example.com/path');
$assertSame(false, $missingScheme->isValid(), 'Scheme-relative inference is not performed by default.');

$invalidHost = $n->clean('https://exa mple.com/path');
$assertSame(false, $invalidHost->isValid(), 'Host whitespace is rejected.');

$encodedPath = $n->clean('https://example.com/a%2Fb');
$literalPath = $n->clean('https://example.com/a/b');
$assertTrue($encodedPath->comparisonKey() !== $literalPath->comparisonKey(), 'Encoded path separators are not treated as equivalent to literal separators.');

$idn = $n->clean('https://münich.example/path');
$assertTrue($idn->isValid(), 'Unicode host is accepted as supplied.');
$assertTrue($idn->warnings() !== [], 'Unicode host produces an IDNA non-equivalence warning.');

$original = "  https://Example.com/path?utm_source=x&ref=y  ";
$originalResult = $n->clean($original);
$assertSame($original, $originalResult->originalUrl(), 'Original URL is preserved byte-for-byte.');
$assertSame('https://example.com/path?ref=y', $originalResult->cleanUrl(), 'Parsing may ignore surrounding whitespace without mutating original.');

$aliases = UrlCleaningPolicy::conservative()->withHostAliases([
    'twitter.com' => 'x.com',
    'm.youtube.com' => 'www.youtube.com',
]);
$aliasNormalizer = new CleanUrlNormalizer($aliases);
$assertSame('https://x.com/a', $aliasNormalizer->clean('https://twitter.com/a')->comparisonKey(), 'Host aliases are available only when explicitly configured.');

$dedupe = $n->deduplicate([
    'https://shop.example/item?af_code=a&utm_source=ig',
    'https://shop.example/item/?af_code=a&fbclid=x',
    'https://shop.example/item?af_code=b',
]);
$assertSame(2, count($dedupe['urls']), 'Dedupe retains two distinct destinations.');
$assertSame(1, $dedupe['duplicates_removed'], 'Dedupe removes one equivalent URL.');
$assertSame('https://shop.example/item?af_code=a&utm_source=ig', $dedupe['urls'][0], 'Dedupe retains first original URL exactly.');

$invalidDedupe = $n->deduplicate(['mailto:a@example.com', 'mailto:a@example.com']);
$assertSame(2, count($invalidDedupe['urls']), 'Invalid/unsupported inputs are not deduplicated speculatively.');

if ($failures !== []) {
    fwrite(STDERR, implode("\n\n", $failures) . "\n");
    fwrite(STDERR, count($failures) . " failure(s), {$assertions} assertion(s).\n");
    exit(1);
}

echo "PASS: {$assertions} assertions.\n";
