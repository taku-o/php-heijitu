# php-heijitu

日本の営業日計算ライブラリ（PHP 版）— go-heijitu の PHP 移植版。

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## 動作要件

- PHP 7.4 または 8.x

## インストール

このパッケージは GitHub 経由で配布しています（Packagist 未公開）。`composer.json` に VCS リポジトリを追加してください。

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

ライブラリをインストールします。

```bash
composer require taku-o/php-heijitu
```

### プロバイダー別の追加依存

| プロバイダー | 必要パッケージ | インストールコマンド |
|------------|-------------|-------------------|
| HolidayJp | `holiday-jp/holiday_jp` | `composer require holiday-jp/holiday_jp` |
| GoogleCalendar | `google/apiclient` | `composer require google/apiclient:~2.16.0` |
| CaoCsv（SJIS デコード） | `ext-mbstring` | PHP 拡張（ほとんどの環境で標準搭載） |
| 設定ファイル YAML 対応 | `symfony/yaml` | `composer require symfony/yaml` |

## クイックスタート

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
echo $cal->nextBusinessDay($date)->format('Y-m-d'); // 2024-01-04
```

## 除外日付の指定

### コンストラクタ引数で指定

```php
use Heijitu\MonthDay;

$cal = new BusinessCalendar($provider, [
    new MonthDay(8, 15),  // お盆
    new MonthDay(12, 31), // 大晦日
]);
```

### 設定ファイルで指定（JSON / YAML）

```php
use Heijitu\Config;

$excluded = Config::loadExcludedDates('/path/to/config.json');
$merged   = array_merge($excluded, [new MonthDay(12, 31)]);
$cal      = new BusinessCalendar($provider, $merged);
```

**JSON 形式:**
```json
{
    "excluded_dates": [
        { "month": 8, "day": 15 },
        { "month": 12, "day": 31 }
    ]
}
```

**YAML 形式**（`symfony/yaml` が必要）:
```yaml
excluded_dates:
  - { month: 8, day: 15 }
  - { month: 12, day: 31 }
```

## プロバイダー

各プロバイダーのセットアップ方法は [`docs/ja/providers.md`](docs/ja/providers.md) を参照してください。

| プロバイダー | 依存パッケージ | データソース |
|------------|-------------|------------|
| `HolidayJp` | `holiday-jp/holiday_jp` | 組み込みデータ（オフライン） |
| `CaoCsv` | `ext-mbstring` | 内閣府 CSV（オンライン取得またはローカルファイル） |
| `GoogleCalendar` | `google/apiclient` | Google Calendar API |

## データの陳腐化に関する注意

`HolidayJp` プロバイダーが使用する `holiday-jp/holiday_jp` パッケージのデータは **2020 年以降更新されていません**。2021 年以降の祝日変更（山の日の調整等）は反映されていません。

本番運用では、最新の祝日データを取得できる **`CaoCsv`** または **`GoogleCalendar`** プロバイダーの使用を推奨します。

## タイムゾーンについて

このライブラリは PHP のデフォルトタイムゾーンを使って日付を比較します。必ずライブラリ使用前にタイムゾーンを `Asia/Tokyo` に設定してください。

```php
date_default_timezone_set('Asia/Tokyo');
```

設定しない場合、深夜 0 時付近の日付で 1 日ずれる可能性があります。

## ドキュメント

- [API リファレンス](docs/ja/api-spec.md)
- [使い方ガイド](docs/ja/usage.md)
- [プロバイダーガイド](docs/ja/providers.md)
- [English README](README.md)

## ライセンス

MIT — 詳細は [LICENSE](LICENSE) を参照してください。
