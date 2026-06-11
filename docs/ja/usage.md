# 使い方ガイド

## インストール

このパッケージは Packagist 未公開です。`composer.json` に VCS リポジトリを追加してください。

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/taku-o/php-heijitu"
        }
    ]
}
```

インストールします。

```bash
composer require taku-o/php-heijitu
```

プロバイダー別の依存については [プロバイダーガイド](providers.md) を参照してください。

---

## 基本的な使い方

```php
<?php
require 'vendor/autoload.php';

date_default_timezone_set('Asia/Tokyo');

use Heijitu\BusinessCalendar;
use Heijitu\Providers\HolidayJp\Provider as HolidayJpProvider;

$provider = new HolidayJpProvider();
$cal      = new BusinessCalendar($provider);

$date = new DateTimeImmutable('2024-01-01');

echo ($cal->isBusinessDay($date) ? 'true' : 'false') . PHP_EOL; // false
echo $cal->nextBusinessDay($date)->format('Y-m-d') . PHP_EOL;   // 2024-01-04
```

---

## 除外日付の指定

### コンストラクタ引数で指定

`MonthDay[]` をコンストラクタに渡すと、指定した日付を毎年の非営業日として扱います（会社の休業日など）。

```php
use Heijitu\MonthDay;

$cal = new BusinessCalendar($provider, [
    new MonthDay(8, 15),  // お盆
    new MonthDay(12, 31), // 大晦日
]);

$obon = new DateTimeImmutable('2024-08-15');
echo ($cal->isBusinessDay($obon) ? 'true' : 'false') . PHP_EOL; // false
```

### 設定ファイルで指定

`Config::loadExcludedDates()` で JSON または YAML ファイルから除外日付を読み込みます。YAML を使用する場合は `symfony/yaml` の追加インストールが必要です。

```bash
composer require symfony/yaml
```

**config.json の例:**
```json
{
    "excluded_dates": [
        { "month": 8, "day": 15 },
        { "month": 12, "day": 31 }
    ]
}
```

```php
use Heijitu\BusinessCalendar;
use Heijitu\Config;

$excluded = Config::loadExcludedDates('/path/to/config.json');
$cal      = new BusinessCalendar($provider, $excluded);
```

### コンストラクタ引数とファイルを array_merge で合成

```php
use Heijitu\BusinessCalendar;
use Heijitu\Config;
use Heijitu\MonthDay;

$fromFile   = Config::loadExcludedDates('/path/to/config.json');
$additional = [new MonthDay(5, 1)]; // 実行時に追加する日付
$excluded   = array_merge($fromFile, $additional);

$cal = new BusinessCalendar($provider, $excluded);
```

フィールドリファレンスと例外の詳細は API リファレンスの [設定ファイル仕様](api-spec.md#設定ファイル仕様) を参照してください。

---

## API 別コード例

### `isBusinessDay`

指定日が営業日かどうかを確認します。

```php
$date = new DateTimeImmutable('2024-01-04');

echo ($cal->isBusinessDay($date) ? 'true' : 'false') . PHP_EOL; // true
```

1回限りの追加除外については [`extraExcluded`](#extraexcluded--1回限りの除外) を参照してください。

### `nextBusinessDay`

指定日の翌営業日を取得します。

```php
$newYear    = new DateTimeImmutable('2024-01-01');
$nextBizDay = $cal->nextBusinessDay($newYear);

echo $nextBizDay->format('Y-m-d') . PHP_EOL; // 2024-01-04
```

### `firstBusinessDayOfMonth`

指定年月の月初営業日を取得します。

```php
$first = $cal->firstBusinessDayOfMonth(2024, 1);

echo $first->format('Y-m-d') . PHP_EOL; // 2024-01-04
```

### `firstBusinessDaysOfYear`

指定年の各月の月初営業日を取得します（12件）。

```php
$firsts = $cal->firstBusinessDaysOfYear(2024);

foreach ($firsts as $i => $date) {
    $month = $i + 1;
    echo sprintf('%02d: %s', $month, $date->format('Y-m-d')) . PHP_EOL;
}
// 01: 2024-01-04
// 02: 2024-02-01
// ...
```

### `holidays`

指定期間の祝日を取得します。

```php
$from = new DateTimeImmutable('2024-01-01');
$to   = new DateTimeImmutable('2024-03-31');

foreach ($cal->holidays($from, $to) as $holiday) {
    echo $holiday->getDate()->format('Y-m-d') . ' ' . $holiday->getName() . PHP_EOL;
}
// 2024-01-01 元日
// 2024-01-08 成人の日
// ...
```

### `extraExcluded` — 1回限りの除外

`isBusinessDay` の可変長引数として `MonthDay` を渡すと、その呼び出しのみ有効な除外日を指定できます。カレンダーインスタンスは変更されません。

```php
$date     = new DateTimeImmutable('2024-08-12');
$extraDay = new MonthDay(8, 12);

// 追加除外なし
echo ($cal->isBusinessDay($date) ? 'true' : 'false') . PHP_EOL;           // true

// この呼び出しのみ 8/12 を除外
echo ($cal->isBusinessDay($date, $extraDay) ? 'true' : 'false') . PHP_EOL; // false
```

---

## タイムゾーンの設定

このライブラリは PHP のプロセスデフォルトタイムゾーンを使って日付を比較します。ライブラリを使用する前に必ず `date_default_timezone_set()` を呼び出してください。

```php
date_default_timezone_set('Asia/Tokyo');
```

設定しない場合、サーバーのタイムゾーンが Asia/Tokyo と異なるとき、深夜 0 時付近の日付で 1 日ずれる可能性があります。

詳細は PHP マニュアルの [`date_default_timezone_set`](https://www.php.net/manual/ja/function.date-default-timezone-set.php) を参照してください。
