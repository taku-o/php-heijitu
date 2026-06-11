# API リファレンス

## BusinessCalendar

`HolidayProvider` と任意の除外日付リストを使って営業日を計算します。

### コンストラクタ

```php
public function __construct(
    HolidayProvider $provider,
    array $excludedDates = []
)
```

| パラメータ | 型 | 説明 |
|-----------|------|------|
| `$provider` | `HolidayProvider` | 祝日データソース |
| `$excludedDates` | `MonthDay[]` | 毎年除外する日付リスト（会社の休業日など） |

**例外:** `\InvalidArgumentException` — `$excludedDates` に `MonthDay` 以外の要素が含まれる場合。

### メソッド

#### `isBusinessDay`

```php
public function isBusinessDay(
    \DateTimeImmutable $t,
    MonthDay ...$extraExcluded
): bool
```

`$t` が営業日（週末でなく・祝日でなく・`$excludedDates` に含まれず・`$extraExcluded` に含まれない）であれば `true` を返します。

| パラメータ | 型 | 説明 |
|-----------|------|------|
| `$t` | `\DateTimeImmutable` | 判定対象日 |
| `...$extraExcluded` | `MonthDay` | この呼び出しのみ有効な追加除外日付 |

**戻り値:** `bool`

**例外:** `Exception\ProviderException` — プロバイダーからそのまま伝播。

---

#### `nextBusinessDay`

```php
public function nextBusinessDay(\DateTimeImmutable $from): \DateTimeImmutable
```

`$from` の翌日以降で最初の営業日を返します（`$from` 自身は含みません）。

| パラメータ | 型 | 説明 |
|-----------|------|------|
| `$from` | `\DateTimeImmutable` | 起点日（結果に含まない） |

**戻り値:** `\DateTimeImmutable` — プロセスデフォルトタイムゾーンで生成。

**例外:** `Exception\ProviderException` — プロバイダーからそのまま伝播。

---

#### `firstBusinessDayOfMonth`

```php
public function firstBusinessDayOfMonth(int $year, int $month): \DateTimeImmutable
```

指定年月の月初営業日を返します。

| パラメータ | 型 | 説明 |
|-----------|------|------|
| `$year` | `int` | 年 |
| `$month` | `int` | 月（1〜12） |

**戻り値:** `\DateTimeImmutable`

**例外:** `Exception\ProviderException` — プロバイダーからそのまま伝播。

---

#### `firstBusinessDaysOfYear`

```php
public function firstBusinessDaysOfYear(int $year): array
```

指定年の各月の月初営業日を返します。

| パラメータ | 型 | 説明 |
|-----------|------|------|
| `$year` | `int` | 年 |

**戻り値:** `\DateTimeImmutable[]` — 12件（index 0 = 1月、index 11 = 12月）。

**例外:** `Exception\ProviderException` — プロバイダーからそのまま伝播。

---

#### `holidays`

```php
public function holidays(
    \DateTimeImmutable $from,
    \DateTimeImmutable $to
): array
```

`$from` から `$to` までの祝日を昇順で返します（両端含む）。

| パラメータ | 型 | 説明 |
|-----------|------|------|
| `$from` | `\DateTimeImmutable` | 開始日（含む） |
| `$to` | `\DateTimeImmutable` | 終了日（含む） |

**戻り値:** `Holiday[]` — `$from > $to` の場合は空配列。

**例外:** `Exception\ProviderException` — プロバイダーからそのまま伝播。

---

## HolidayProvider

全プロバイダー実装が満たすべきインターフェース。組み込みプロバイダーのセットアップと使い方は [プロバイダーガイド](providers.md) を参照してください。

```php
interface HolidayProvider
```

### メソッド

#### `isHoliday`

```php
public function isHoliday(\DateTimeImmutable $t): bool
```

**例外:** `Exception\ProviderException` — データ取得失敗時。

#### `holidayName`

```php
public function holidayName(\DateTimeImmutable $t): string
```

祝日名を返します。`$t` が祝日でない場合は空文字 `""` を返します（例外にしません）。

**例外:** `Exception\ProviderException` — データ取得失敗時。

#### `holidaysBetween`

```php
public function holidaysBetween(
    \DateTimeImmutable $from,
    \DateTimeImmutable $to
): array
```

`Holiday[]` を日付昇順で返します。`$from > $to` の場合は空配列を返します。

**例外:** `Exception\ProviderException` — データ取得失敗時。

---

## MonthDay

月日を表す不変値オブジェクト。年に依存しません。除外日付の指定に使用します。

### コンストラクタ

```php
public function __construct(int $month, int $day)
```

| パラメータ | 型 | 説明 |
|-----------|------|------|
| `$month` | `int` | 月（1〜12） |
| `$day` | `int` | 日（1〜31） |

バリデーションは行いません。`MonthDay(2, 30)` のような不正な日付はいかなる日付にも一致しません。

### メソッド

#### `getMonth`

```php
public function getMonth(): int
```

#### `getDay`

```php
public function getDay(): int
```

#### `matches`

```php
public function matches(\DateTimeImmutable $t): bool
```

`$t` の月・日がこのオブジェクトと一致する場合に `true` を返します（年は無視）。

---

## Holiday

祝日の日付と名称を保持する不変値オブジェクト。`HolidayProvider::holidaysBetween()` と `BusinessCalendar::holidays()` の戻り値として使用します。

### コンストラクタ

```php
public function __construct(\DateTimeImmutable $date, string $name)
```

### メソッド

#### `getDate`

```php
public function getDate(): \DateTimeImmutable
```

#### `getName`

```php
public function getName(): string
```

---

## Config

JSON または YAML 設定ファイルから除外日付を読み込みます。

### メソッド

#### `loadExcludedDates`

```php
public static function loadExcludedDates(string $path): array
```

JSON または YAML ファイルを読み込み `MonthDay[]` を返します。

| パラメータ | 型 | 説明 |
|-----------|------|------|
| `$path` | `string` | 設定ファイルの絶対パスまたは相対パス |

**戻り値:** `MonthDay[]`

**例外:** `Exception\ConfigException` — 非対応の拡張子・ファイル読み込み失敗・パース失敗時。

**注意:** YAML 対応には `symfony/yaml` が必要です（`composer require symfony/yaml`）。

---

## 例外クラス

### 継承関係

```
\RuntimeException
├── Exception\ConfigException  (implements Exception\HeijituException)
└── Exception\ProviderException  (implements Exception\HeijituException)

interface Exception\HeijituException
```

### `Exception\HeijituException`

本ライブラリが投げる全例外の共通マーカーインターフェース。このインターフェースを catch することで全ライブラリ例外を一括捕捉できます。

```php
try {
    // ...
} catch (\Heijitu\Exception\HeijituException $e) {
    // ConfigException・ProviderException の両方を捕捉
}
```

### `Exception\ConfigException`

`Config::loadExcludedDates()` が以下の場合に投げます。
- 非対応の拡張子（`.json`・`.yaml`・`.yml` 以外）
- ファイルを読み込めない場合
- ファイルのパースに失敗した場合

### `Exception\ProviderException`

プロバイダーのコンストラクタおよび `HolidayProvider` メソッドが以下の場合に投げます。
- 必要なパッケージが未導入（`holiday-jp/holiday_jp`・`google/apiclient`）
- ファイルを読み込めない場合（CaoCsv ローカルモード）
- HTTP リクエスト失敗（CaoCsv オンラインモード・GoogleCalendar）
- Google Calendar API 認証失敗

---

## 設定ファイル仕様

`Config::loadExcludedDates()` は以下の構造の JSON または YAML ファイルを受け付けます。

### JSON

```json
{
    "excluded_dates": [
        { "month": 8, "day": 15 },
        { "month": 12, "day": 31 }
    ]
}
```

### YAML

```yaml
excluded_dates:
  - { month: 8, day: 15 }
  - { month: 12, day: 31 }
```

### フィールド一覧

| フィールド | 型 | 説明 |
|-----------|------|------|
| `excluded_dates` | 配列 | 毎年除外する月/日のペアのリスト |
| `excluded_dates[].month` | 整数 | 月（1〜12） |
| `excluded_dates[].day` | 整数 | 日（1〜31） |

`excluded_dates` がファイルに存在しない場合、`loadExcludedDates()` は空配列を返します。
