# Clean URL Normalizer

[English](README.md) · [Bahasa Indonesia](README.id.md) · [简体中文](README.zh-CN.md)

这是 [`README.md`](README.md) 的简体中文翻译；`README.md` 是本项目的规范技术文档。

> 从清理后的 URL 中移除跟踪噪声，同时保留收到的 URL 以及业务需要的标记。

默认情况下，`utm_source`、`fbclid` 和 `gclid` 等跟踪参数会从清理 URL 和比较键中移除。原始输入始终按字节完整保留，包括 `utm_source`；未知参数、联盟参数和 referral 参数默认仍保留在清理 URL 中。

`willio/clean-url-normalizer` 是一个面向 PHP 8.1+ 的轻量 URL 清理与比较库。它适用于导入流程、显示前处理、可选去重，以及任何不应悄然修改调用方原始 URL 的场景。

## 功能

对于原始输入，库通过 `CleanUrlResult::originalUrl()` 按字节保留调用方传入的内容。对于支持的 HTTP(S) URL，库还可以生成：

- 经过 scheme/host 规范化，并移除明确配置的通用跟踪参数的清理 URL；
- 对剩余原始 query 参数排序后生成的确定性比较键；
- 被移除的参数名称；
- 当库有意不判断等价性时给出的警告；
- 针对不支持或格式错误输入的验证错误。

因此，调用方可以获得三个有用层次的信息：

- `originalUrl()` 是收到的完整原始 URL，包括跟踪和归因数据；
- `cleanUrl()` 是更易读的 URL，移除策略配置的跟踪噪声，同时保留有意义的 query 参数；
- `comparisonKey()` 是用于谨慎匹配或去重的确定性键。

库**不会**声称具有相同比较键的两个 URL 在所有场景下都等价。比较行为是策略启发式，只有在这些假设适合调用方业务时才应使用。

## 安装

当本包通过 Packagist 提供后，可使用 Composer 安装：

```bash
composer require willio/clean-url-normalizer
```

除 PHP 8.1 或更高版本外，本包没有运行时依赖。

## 保守默认策略

`UrlCleaningPolicy::conservative()`：

- 只支持明确写出的 `http://` 和 `https://` URL；
- 将 scheme 和 host 转为小写；
- 移除 `utm_*`、`fbclid`、`gclid`、`igshid`、`ttclid`、`mc_cid`、`mc_eid` 和 `_hsenc`；
- 默认保留未知参数、联盟参数和 referral 参数；
- 保留重复的 query 参数；
- 在清理 URL 中保留剩余 query 参数的原始顺序；
- 仅在比较键中对剩余原始 query 参数排序；
- 在清理 URL 中保留 fragment，但默认从比较键中省略；
- 仅在比较键中规范化显式的默认端口；
- 将末尾斜杠规范化视为比较启发式；
- 除非调用方显式启用，否则不推断 host 别名；
- 保留 userinfo，不会静默丢弃；
- 保留输入中的 Unicode/punycode host 拼写，不推断 IDNA 等价性；
- 不对 path 的 percent-encoding 进行解码或规范化。

## 用法

```php
use Willio\CleanUrlNormalizer\CleanUrlNormalizer;

$normalizer = new CleanUrlNormalizer();
$result = $normalizer->clean('https://Example.com/item?utm_source=ig&ref=alice');

$result->originalUrl();       // 调用方传入的原始 URL
$result->cleanUrl();          // https://example.com/item?ref=alice
$result->comparisonKey();     // https://example.com/item?ref=alice
$result->removedParameters(); // ['utm_source']
$result->warnings();
$result->validationErrors();
$result->isValid();
```

### 常见链接：处理前与处理后

Normalizer 不需要理解 Google Maps、YouTube、Instagram 或联盟网络的业务含义。它只移除策略覆盖的参数，并保留目的地参数。

| 常见输入 | `cleanUrl()` | `comparisonKey()` | 实际帮助 |
| --- | --- | --- | --- |
| `https://www.google.com/maps/search/?api=1&query=Monas%2C+Jakarta&utm_source=share` | `https://www.google.com/maps/search/?api=1&query=Monas%2C+Jakarta` | `https://www.google.com/maps/search?api=1&query=Monas%2C+Jakarta` | 移除分享跟踪参数，同时保留 Maps 目的地和 query。 |
| `https://www.youtube.com/watch?v=dQw4w9WgXcQ&utm_source=share` | `https://www.youtube.com/watch?v=dQw4w9WgXcQ` | `https://www.youtube.com/watch?v=dQw4w9WgXcQ` | 保留视频 ID，同时移除活动跟踪噪声。 |
| `https://www.instagram.com/p/ABC123/?igshid=tracking` | `https://www.instagram.com/p/ABC123/` | `https://www.instagram.com/p/ABC123` | 保留帖子 URL，并让末尾斜杠在比较时不产生差异。 |
| `https://store.example.com/product?ref=creator&aff_id=abc&utm_source=instagram` | `https://store.example.com/product?ref=creator&aff_id=abc` | `https://store.example.com/product?aff_id=abc&ref=creator` | 保留 referral 和联盟标记，同时移除 `utm_source`。 |

在每个示例中，`originalUrl()` 仍会按收到时的原样返回完整输入。这样，导入器可以保存来源 URL、展示更清晰的链接，并在后续比较变体时避免悄悄丢失归因数据。

### 当清理 URL 需要 UTM 参数时保留它们

UTM 参数默认可以被移除，但并非禁止保留。如果清理 URL 必须保留 `utm_source` 或其他 UTM 值，可以显式配置策略，并只移除不需要的跟踪器：

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

这种策略模型适用于活动报告、联盟归因、导入流程、展示/分享链接以及谨慎的 URL 去重。库不会发起网络请求，也不会声称相同的比较键就证明两个 URL 在所有场景下指向完全相同的目标。

Host 别名默认关闭，因为 provider/domain 别名取决于上下文，并不是通用规则：

```php
use Willio\CleanUrlNormalizer\CleanUrlNormalizer;
use Willio\CleanUrlNormalizer\UrlCleaningPolicy;

$policy = UrlCleaningPolicy::conservative()->withHostAliases([
    'twitter.com' => 'x.com',
]);

$normalizer = new CleanUrlNormalizer($policy);
```

## 可选去重

`CleanUrlNormalizer::deduplicate()` 接受 URL 字符串，仅比较能够生成有效比较键的输入，并完整保留第一个原始字符串。不支持的输入会被保留，不会被推测性地去重。

## 非目标与安全边界

本包不会发起网络请求，也不会解析 redirect、DNS、HTTP 状态、短链接、provider 身份或 SSRF 策略。本包不包含 Linkee provider 检测、导入 fetcher、LLM 提取、Creator Agent、Oversight、block 规范化、认证、commerce、数据库、环境变量、凭据、存储或生产数据逻辑。

需要抓取 URL 的调用方必须单独实施自己的网络与 SSRF 控制。

## 来源与许可证

比较行为提取自 Linkee 的一方实现 `app/core/import-url.php`，该实现由 Linkee commit `a0e10e5aeb16bb64a0b281744b3972662e291a9f`（`fix(import): add canonical URL comparison and link dedup`）引入。Linkee 应用本身仍使用 proprietary 许可证。

本独立包使用 MIT License。原始 Linkee 应用仍为 proprietary；本仓库只包含可复用的 URL 清理与比较层、测试和文档。提取契约已经参考 Linkee 内部的 URL Equivalence 设计进行审查，该设计未包含在本仓库中。

## 开发

```bash
composer test
```

当前测试套件无运行时依赖，覆盖继承自 Linkee 的比较案例，以及跟踪参数、联盟/referral 参数、query 顺序、重复参数、空值、fragment、端口、IPv6、userinfo、scheme、编码 path、IDN、别名和可选去重等保守边界情况。
