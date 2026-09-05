# Clean URL Normalizer

[English](README.md) · [Bahasa Indonesia](README.id.md) · [简体中文](README.zh-CN.md)

Ini adalah terjemahan Bahasa Indonesia dari [`README.md`](README.md), yang menjadi dokumentasi teknis kanonik.

> Bersihkan URL dari noise pelacakan tanpa kehilangan URL yang diterima atau token yang dibutuhkan workflow Anda.

Secara default, parameter pelacakan seperti `utm_source`, `fbclid`, dan `gclid` dihapus dari URL bersih dan kunci perbandingan. Input asli selalu dipertahankan byte demi byte, termasuk `utm_source`, sementara parameter yang tidak dikenal, afiliasi, dan referral tetap tersedia pada URL bersih.

`willio/clean-url-normalizer` adalah library PHP 8.1+ kecil untuk membersihkan dan membandingkan URL. Library ini ditujukan untuk pipeline impor, persiapan tampilan, deduplikasi opsional, dan alur serupa ketika URL asli tidak boleh diubah secara diam-diam.

## Fungsinya

Library mempertahankan input asli byte demi byte melalui `CleanUrlResult::originalUrl()`. Untuk URL HTTP(S) yang didukung, library juga dapat menghasilkan:

- URL bersih dengan normalisasi skema/host dan penghapusan parameter pelacakan generik yang dikonfigurasi secara eksplisit;
- kunci perbandingan deterministik dengan pasangan query mentah yang masih ada diurutkan;
- nama parameter yang dihapus;
- peringatan ketika library sengaja tidak menyatakan dua URL ekuivalen;
- kesalahan validasi untuk input yang tidak didukung atau tidak valid.

Dengan demikian, pemanggil mendapatkan tiga tingkat informasi yang berguna:

- `originalUrl()` adalah URL persis yang diterima, termasuk data tracking dan atribusi;
- `cleanUrl()` adalah URL yang lebih mudah dibaca, dengan noise pelacakan yang dikonfigurasi dihapus sementara parameter query yang bermakna tetap ada;
- `comparisonKey()` adalah kunci deterministik untuk pencocokan atau deduplikasi secara hati-hati.

Library ini **tidak** menyatakan bahwa dua URL dengan kunci yang sama selalu ekuivalen. Perilaku perbandingan adalah heuristik kebijakan dan hanya sesuai jika asumsi tersebut cocok dengan domain pemanggil.

## Instalasi

Setelah package tersedia melalui Packagist, instal dengan Composer:

```bash
composer require willio/clean-url-normalizer
```

Package ini tidak memiliki dependensi runtime selain PHP 8.1 atau yang lebih baru.

## Default konservatif

`UrlCleaningPolicy::conservative()`:

- hanya mendukung URL `http://` dan `https://` yang ditulis secara eksplisit;
- mengubah skema dan host menjadi huruf kecil;
- menghapus `utm_*`, `fbclid`, `gclid`, `igshid`, `ttclid`, `mc_cid`, `mc_eid`, dan `_hsenc`;
- mempertahankan parameter yang tidak dikenal, afiliasi, dan referral secara default;
- mempertahankan parameter query yang berulang;
- mempertahankan urutan query yang tersisa pada URL bersih;
- hanya mengurutkan pasangan query mentah pada kunci perbandingan;
- mempertahankan fragment pada URL bersih, tetapi menghilangkannya dari kunci perbandingan;
- menormalkan port default eksplisit hanya pada kunci perbandingan;
- memperlakukan normalisasi garis miring terakhir sebagai heuristik perbandingan;
- tidak menyimpulkan alias host kecuali pemanggil mengaktifkannya;
- mempertahankan userinfo, bukan membuangnya secara diam-diam;
- mempertahankan ejaan host Unicode/punycode seperti yang diberikan dan tidak menyimpulkan ekuivalensi IDNA;
- tidak melakukan decode atau normalisasi percent-encoding pada path.

## Penggunaan

```php
use Willio\CleanUrlNormalizer\CleanUrlNormalizer;

$normalizer = new CleanUrlNormalizer();
$result = $normalizer->clean('https://Example.com/item?utm_source=ig&ref=alice');

$result->originalUrl();       // input asli pemanggil
$result->cleanUrl();          // https://example.com/item?ref=alice
$result->comparisonKey();     // https://example.com/item?ref=alice
$result->removedParameters(); // ['utm_source']
$result->warnings();
$result->validationErrors();
$result->isValid();
```

### Contoh link umum, sebelum dan sesudah

Normalizer tidak perlu mengetahui arti Google Maps, YouTube, Instagram, atau jaringan afiliasi. Normalizer hanya menghapus parameter yang tercakup dalam kebijakan dan mempertahankan parameter tujuan.

| Input umum | `cleanUrl()` | `comparisonKey()` | Hasil yang berguna |
| --- | --- | --- | --- |
| `https://www.google.com/maps/search/?api=1&query=Monas%2C+Jakarta&utm_source=share` | `https://www.google.com/maps/search/?api=1&query=Monas%2C+Jakarta` | `https://www.google.com/maps/search?api=1&query=Monas%2C+Jakarta` | Menghapus tracker berbagi tanpa menghilangkan tujuan dan query Maps. |
| `https://www.youtube.com/watch?v=dQw4w9WgXcQ&utm_source=share` | `https://www.youtube.com/watch?v=dQw4w9WgXcQ` | `https://www.youtube.com/watch?v=dQw4w9WgXcQ` | Mempertahankan ID video dan menghapus noise kampanye. |
| `https://www.instagram.com/p/ABC123/?igshid=tracking` | `https://www.instagram.com/p/ABC123/` | `https://www.instagram.com/p/ABC123` | Mempertahankan URL post dan membuat garis miring terakhir tidak berpengaruh pada perbandingan. |
| `https://store.example.com/product?ref=creator&aff_id=abc&utm_source=instagram` | `https://store.example.com/product?ref=creator&aff_id=abc` | `https://store.example.com/product?aff_id=abc&ref=creator` | Mempertahankan token referral dan afiliasi sambil menghapus `utm_source`. |

Pada setiap contoh, `originalUrl()` tetap mengembalikan input lengkap persis seperti saat diterima. Dengan begitu, importer dapat menyimpan URL sumber, menampilkan link yang lebih bersih, dan membandingkan varian berikutnya tanpa membuang data atribusi secara diam-diam.

### Mempertahankan parameter UTM ketika URL bersih membutuhkannya

Parameter UTM dapat dihapus secara default, tetapi tidak wajib dihapus. Jika URL bersih harus mempertahankan `utm_source` atau nilai UTM lainnya, konfigurasikan policy secara eksplisit dan terus hapus hanya tracker yang tidak diperlukan:

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

Model policy ini berguna untuk pelaporan kampanye, atribusi afiliasi, pipeline impor, link tampilan/berbagi, dan deduplikasi URL secara hati-hati. Library tidak pernah melakukan request jaringan dan tidak mengklaim bahwa kunci perbandingan yang sama membuktikan tujuan universal yang sama.

Alias host bersifat opt-in karena alias provider/domain bergantung pada konteks dan bukan aturan universal:

```php
use Willio\CleanUrlNormalizer\CleanUrlNormalizer;
use Willio\CleanUrlNormalizer\UrlCleaningPolicy;

$policy = UrlCleaningPolicy::conservative()->withHostAliases([
    'twitter.com' => 'x.com',
]);

$normalizer = new CleanUrlNormalizer($policy);
```

## Deduplikasi opsional

`CleanUrlNormalizer::deduplicate()` menerima kumpulan string URL, hanya membandingkan input yang menghasilkan kunci perbandingan valid, dan mempertahankan string URL asli pertama secara persis. Input yang tidak didukung dipertahankan dan tidak dideduplikasi secara spekulatif.

## Bukan tujuan dan batas keamanan

Package ini tidak melakukan permintaan jaringan. Package ini tidak menyelesaikan redirect, DNS, status HTTP, URL pendek, identitas provider, atau kebijakan SSRF. Package ini tidak berisi deteksi provider Linkee, fetcher impor, ekstraksi LLM, Creator Agent, Oversight, normalisasi block, autentikasi, commerce, database, environment, kredensial, storage, atau data produksi.

Pemanggil yang mengambil URL melalui jaringan harus menerapkan kontrol jaringan dan SSRF masing-masing secara terpisah.

## Provenance dan lisensi

Perilaku perbandingan diekstrak dari implementasi first-party Linkee di `app/core/import-url.php`, yang diperkenalkan pada commit Linkee `a0e10e5aeb16bb64a0b281744b3972662e291a9f` (`fix(import): add canonical URL comparison and link dedup`). Aplikasi Linkee sendiri tetap menggunakan lisensi proprietary.

Package mandiri ini menggunakan MIT License. Aplikasi Linkee asli tetap proprietary; repository ini hanya berisi lapisan pembersihan dan perbandingan URL yang dapat digunakan ulang, test, dan dokumentasinya. Kontrak ekstraksi telah ditinjau terhadap rancangan internal URL Equivalence milik Linkee, yang tidak disertakan di sini.

## Pengembangan

```bash
composer test
```

Test saat ini bebas dependensi dan mencakup kasus perbandingan Linkee yang diwarisi, serta edge case konservatif untuk tracking, parameter afiliasi/referral, urutan query, parameter berulang, nilai kosong, fragment, port, IPv6, userinfo, skema, path ter-encode, IDN, alias, dan deduplikasi opsional.
