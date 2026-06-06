# 要件定義書: Step 1 — プロジェクト初期化 + コア実装

## Introduction

日本の営業日を計算する PHP ライブラリ `php-heijitu`（Go 製 `go-heijitu` の PHP 移植版）のコア部分を実装する。
本ステップでは、ライブラリの骨格となるプロジェクト構成・値オブジェクト・`HolidayProvider` インターフェース・`BusinessCalendar` を構築し、`isBusinessDay()` の判定ロジックまでを動作可能な状態にする。

祝日判定の具体的な実装（各プロバイダー）と `isBusinessDay` 以外の API は後続ステップで実装する。Go 版の `(値, error)` 多値返却は PHP の例外送出に、関数オプション（`WithExcludedDates` / `WithConfig`）はコンストラクタ引数＋設定ローダーに置き換える（`docs/planning/decisions.md` で確定）。

## Boundary Context

- **In scope**:
  - プロジェクト初期化（`composer.json`・PSR-4 オートロード・`require`/`suggest`/`require-dev` の依存方針・PHPUnit 設定）
  - PHP 7.4 を基準とし PHP 8.1 でもエラー・非推奨警告なく動作するコード
  - `MonthDay`・`Holiday` 値オブジェクト
  - `HolidayProvider` インターフェース定義
  - `BusinessCalendar` の構築（コンストラクタ引数 ＋ 設定ローダー）と `isBusinessDay()` 判定
  - 設定ファイル（YAML / JSON）の読み込み
  - 例外型（`HeijituException` マーカーインターフェース・`ConfigException`・`ProviderException`）
  - モックプロバイダーを使った単体テスト（PHPUnit）
- **Out of scope**:
  - 実プロバイダー実装（`HolidayJp` / `CaoCsv` / `GoogleCalendar`）— Step 2〜4
  - `isBusinessDay` 以外の API（`nextBusinessDay` / `firstBusinessDayOfMonth` / `firstBusinessDaysOfYear` / `holidays`）— Step 2
  - ライブラリが内部生成する日付のタイムゾーン処理（内部で日付を生成する API は本ステップに存在しないため発生しない）— Step 2
  - examples・PHPDoc・README 等のドキュメント整備 — Step 5
- **Adjacent expectations**:
  - `HolidayProvider` に準拠した本番用実装は後続ステップが提供する。本ステップのテストで用いるモックプロバイダーは本番利用を目的としない。
  - 各プロバイダーの外部依存（`holiday-jp/holiday_jp`・`google/apiclient`）・YAML 設定の `symfony/yaml`・`ext-mbstring` は `suggest` とし、利用者は自分が使うプロバイダー／機能の依存だけを追加する。

---

## Requirements

### Requirement 1: プロジェクト初期化とパッケージ構成

**Objective:** As a ライブラリ利用者, I want Composer で導入できる適切なパッケージ定義, so that 自分のプロジェクトに php-heijitu を組み込み、必要なプロバイダー依存だけを追加できる

#### Acceptance Criteria

1. The php-heijitu ライブラリ shall declare its Composer package as `taku-o/php-heijitu`, of type `library`, under the MIT license.
2. The php-heijitu ライブラリ shall declare a PHP version constraint of `^7.4 || ^8.0` in the `require` section. (`^8.0` includes PHP 8.1.)
3. The php-heijitu ライブラリ shall map the `Heijitu\` namespace to `src/` and the `Heijitu\Tests\` namespace to `tests/` via PSR-4 autoloading.
4. Where a holiday provider or optional feature depends on an external package (`holiday-jp/holiday_jp`, `google/apiclient`, `symfony/yaml`) or on the `ext-mbstring` extension, the php-heijitu ライブラリ shall list that dependency under `suggest` rather than `require`.
5. The php-heijitu ライブラリ shall list `phpunit/phpunit` (`^9.6`) and all provider/feature dependencies under `require-dev` for its own development and testing.
6. When a user installs php-heijitu without any provider dependency, the php-heijitu ライブラリ shall load and instantiate its core classes (`MonthDay`, `Holiday`, `BusinessCalendar`, `Config`, exceptions) without error.

---

### Requirement 2: PHP 7.4 基準・8.1 互換の実行環境

**Objective:** As a ライブラリ利用者, I want PHP 7.4 でも 8.1 でも同じように動くライブラリ, so that 動作環境の PHP バージョンに縛られずに利用できる

#### Acceptance Criteria

1. The php-heijitu ライブラリ shall be written using only syntax available in PHP 7.4 (union 型・enum・コンストラクタプロモーション・名前付き引数・`match`・`readonly` を使用しない).
2. When the library code is executed on PHP 8.1, the php-heijitu ライブラリ shall run without emitting deprecation warnings.
3. When the test suite is executed on PHP 7.4 and on PHP 8.1, the php-heijitu プロジェクト shall produce passing results on both versions.

---

### Requirement 3: MonthDay 型

**Objective:** As a ライブラリ利用者, I want 年をまたいで有効な月日を指定する値オブジェクト, so that 会社独自の休業日を簡潔に表現できる

#### Acceptance Criteria

1. The php-heijitu ライブラリ shall provide an immutable `MonthDay` value object constructed from a `month` (int) and a `day` (int), exposing them via `getMonth()` and `getDay()`.
2. When `matches(DateTimeImmutable $t)` is called and both the month and the day of `$t` match the `MonthDay`, the php-heijitu ライブラリ shall return `true`, regardless of the year.
3. When `matches(DateTimeImmutable $t)` is called and either the month or the day of `$t` does not match, the php-heijitu ライブラリ shall return `false`.
4. The php-heijitu ライブラリ shall perform no validation on the `month` and `day` values; `matches` shall perform a direct equality comparison only. A `MonthDay` specifying a combination that does not occur (e.g. month 2 / day 30) shall return `false` for all dates.

---

### Requirement 4: Holiday 型

**Objective:** As a ライブラリ利用者, I want 祝日を日付と名称で表現する値オブジェクト, so that 祝日情報を構造的に扱える

#### Acceptance Criteria

1. The php-heijitu ライブラリ shall provide an immutable `Holiday` value object with a `date` (`DateTimeImmutable`) and a `name` (`string`), exposed via `getDate()` and `getName()`.

---

### Requirement 5: HolidayProvider インターフェース

**Objective:** As a ライブラリ利用者, I want 祝日判定の実装を差し替えられる仕組み, so that プロジェクトの要件に応じてデータソースを選択できる

#### Acceptance Criteria

1. The php-heijitu ライブラリ shall define a `HolidayProvider` interface with three methods: `isHoliday(DateTimeImmutable $t): bool`, `holidayName(DateTimeImmutable $t): string`, and `holidaysBetween(DateTimeImmutable $from, DateTimeImmutable $to): array`.
2. When `isHoliday` is called for a date recognized as a holiday, a `HolidayProvider` implementation shall return `true`; for a date not recognized as a holiday, it shall return `false`.
3. When `holidayName` is called for a date recognized as a holiday, a `HolidayProvider` implementation shall return the holiday name as a non-empty string.
4. When `holidayName` is called for a date not recognized as a holiday, a `HolidayProvider` implementation shall return an empty string (例外を送出しない).
5. When `holidaysBetween($from, $to)` is called, a `HolidayProvider` implementation shall include holidays that fall on the `$from` and `$to` dates themselves (both endpoints inclusive) and shall return them sorted in ascending date order.
6. When `holidaysBetween` is called with a `$from` date that is after the `$to` date, a `HolidayProvider` implementation shall return an empty array.
7. If a `HolidayProvider` method fails to obtain its data, the implementation shall throw an exception without suppressing it.

---

### Requirement 6: BusinessCalendar の構築

**Objective:** As a ライブラリ利用者, I want BusinessCalendar を柔軟に構築する手段, so that 会社の休業日ポリシーをカレンダーに反映できる

#### Acceptance Criteria

1. The php-heijitu ライブラリ shall provide a `BusinessCalendar` constructor that accepts a `HolidayProvider` and an optional array of `MonthDay` excluded dates.
2. When excluded dates are passed to the constructor, the php-heijitu ライブラリ shall register them as fixed excluded dates applied to all subsequent `isBusinessDay` calls on that instance.
3. The php-heijitu ライブラリ shall provide a config loader that reads excluded dates from a file and returns a `MonthDay[]`, so that the caller can pass them to the constructor.
4. When excluded dates loaded from a config file are combined with constructor-supplied excluded dates, the php-heijitu ライブラリ shall treat all of them as excluded.
5. If `null` is passed where a `HolidayProvider` is required, the php-heijitu ライブラリ shall reject construction through its non-nullable type declaration (Go 版の nil プロバイダー panic 相当を型で防ぐ).

---

### Requirement 7: isBusinessDay 判定

**Objective:** As a ライブラリ利用者, I want 指定日が営業日かどうかを判定できる, so that 日付に応じたビジネスロジックを実装できる

#### Acceptance Criteria

1. When `isBusinessDay` is called for a date that falls on a Saturday or Sunday, the php-heijitu ライブラリ shall return `false`.
2. When `isBusinessDay` is called for a date that the `HolidayProvider` identifies as a holiday, the php-heijitu ライブラリ shall return `false`.
3. When `isBusinessDay` is called for a date that matches any excluded date registered on the calendar, the php-heijitu ライブラリ shall return `false`.
4. When `isBusinessDay($t, MonthDay ...$extraExcluded)` is called and `$t` matches any of the `$extraExcluded` values, the php-heijitu ライブラリ shall return `false` for that call only, without affecting other calls.
5. When `isBusinessDay` is called for a weekday that is not a holiday and matches no excluded date, the php-heijitu ライブラリ shall return `true`.
6. When `isBusinessDay($t)` evaluates the weekday, the php-heijitu ライブラリ shall use the timezone carried by the supplied `$t` value.
7. If the `HolidayProvider` throws an exception during the `isBusinessDay` call, the php-heijitu ライブラリ shall propagate that exception to the caller without suppressing it.

---

### Requirement 8: 設定ファイルの読み込み

**Objective:** As a ライブラリ利用者, I want 設定ファイルで休業日を管理できる, so that コードを変更せずに休業日ポリシーを更新できる

#### Acceptance Criteria

1. When a config file path ending in `.yaml` or `.yml` is specified, the php-heijitu ライブラリ shall parse it as YAML.
2. When a config file path ending in `.json` is specified, the php-heijitu ライブラリ shall parse it as JSON.
3. The php-heijitu ライブラリ shall read an `excluded_dates` list from the config file, where each entry has a `month` integer (1–12) and a `day` integer (1–31), and convert them into `MonthDay` values.
4. If the config file path has an extension other than `.yaml`, `.yml`, or `.json`, the php-heijitu ライブラリ shall throw a `ConfigException`.
5. If the config file cannot be read or its content cannot be parsed, the php-heijitu ライブラリ shall throw a `ConfigException`.

---

### Requirement 9: 例外設計

**Objective:** As a ライブラリ利用者, I want ライブラリが送出する例外を一貫した型で扱える, so that エラーを適切に捕捉・処理できる

#### Acceptance Criteria

1. The php-heijitu ライブラリ shall provide a `HeijituException` marker interface that is implemented by every exception the library itself throws, so that the caller can catch them all via `catch (HeijituException $e)`.
2. The php-heijitu ライブラリ shall provide `ConfigException` and `ProviderException`, each extending `\RuntimeException` and implementing `HeijituException`.
3. When config file loading, parsing, or extension validation fails, the php-heijitu ライブラリ shall throw a `ConfigException`.
4. When a provider fails to obtain data, call an API, or has insufficient authentication information, the php-heijitu ライブラリ shall throw a `ProviderException`.
5. If an argument that cannot be prevented by a type declaration is invalid (programmer error), the php-heijitu ライブラリ shall throw a standard `\InvalidArgumentException`.
