# 要件定義書: Step 2 — holidayjp プロバイダー + 残り全 API 実装

## Introduction

`php-heijitu` の `BusinessCalendar` を完全に動作する状態にするため、デフォルト祝日プロバイダー（`HolidayJp`）の実装と、Step 1 で骨格のみ実装した `BusinessCalendar` の残り API（`nextBusinessDay` / `firstBusinessDayOfMonth` / `firstBusinessDaysOfYear` / `holidays`）を追加する。

Step 1 で実装済みの `isBusinessDay()` とコア型・インターフェース・例外型はそのまま活用し、本ステップでは「営業日ベースの日付計算全 API」と「デフォルトプロバイダー」を揃えることを目的とする。

`holiday-jp/holiday_jp` は `decisions.md` B-1 で確定されたデフォルトプロバイダーであり、埋め込みデータで動作するため外部 API 接続は不要。

## Boundary Context

- **In scope**:
  - `Providers/HolidayJp/Provider.php` — `holiday-jp/holiday_jp` を使った `HolidayProvider` 実装
  - `BusinessCalendar` への残り API 追加（`nextBusinessDay` / `firstBusinessDayOfMonth` / `firstBusinessDaysOfYear` / `holidays`）
  - ライブラリが内部生成する日付のタイムゾーン処理（実行環境デフォルト TZ / フォールバック `Asia/Tokyo`）
  - `holiday-jp/holiday_jp` の `composer require-dev` への追加
  - `HolidayJp\Provider` のユニットテスト
  - `BusinessCalendar` 残り API のテスト（holidayjp ベース・モックプロバイダー含む）
- **Out of scope**:
  - `CaoCsv` プロバイダー実装（Step 3）
  - `GoogleCalendar` プロバイダー実装（Step 4）
  - `isBusinessDay()` の実装変更（Step 1 実装済み）
  - `Config.php`・例外型の変更（Step 1 実装済み）
  - examples・PHPDoc・README 等のドキュメント整備（Step 5）
- **Adjacent expectations**:
  - `HolidayProvider` インターフェースは Step 1 で定義済み。本ステップはその実装を提供する。
  - `BusinessCalendar` のコンストラクタおよび `isBusinessDay()` は Step 1 で実装済み。残り API はこの判定ロジックを前提とする。
  - `holiday-jp/holiday_jp` のデータには将来年のカバレッジに限界がある（go-heijitu と同じ既知の弱点）。最新性が必要な場合の対応は後続ステップのプロバイダーに委ねる。

---

## Requirements

### Requirement 1: HolidayJp プロバイダーの実装

**Objective:** As a ライブラリ利用者, I want `holiday-jp/holiday_jp` を使った祝日判定プロバイダー, so that 外部 API 接続なしに日本の祝日データを使って `BusinessCalendar` を利用できる

#### Acceptance Criteria

1. The php-heijitu ライブラリ shall provide a `HolidayJp\Provider` class that implements the `HolidayProvider` interface.
2. When `isHoliday(DateTimeImmutable $t)` is called for a date recognized as a Japanese public holiday, the `HolidayJp\Provider` shall return `true`.
3. When `isHoliday(DateTimeImmutable $t)` is called for a date not recognized as a Japanese public holiday, the `HolidayJp\Provider` shall return `false`.
4. When `holidayName(DateTimeImmutable $t)` is called for a date recognized as a Japanese public holiday, the `HolidayJp\Provider` shall return the holiday name as a non-empty string.
5. When `holidayName(DateTimeImmutable $t)` is called for a date not recognized as a holiday, the `HolidayJp\Provider` shall return an empty string.
6. When `holidaysBetween(DateTimeImmutable $from, DateTimeImmutable $to)` is called, the `HolidayJp\Provider` shall return all Japanese public holidays within the `$from`–`$to` range (両端含む) as a `Holiday[]` sorted in ascending date order.
7. When `holidaysBetween` is called with a `$from` date that is after `$to`, the `HolidayJp\Provider` shall return an empty array.
8. If the `holiday-jp/holiday_jp` library is not installed when `HolidayJp\Provider` is used, the php-heijitu ライブラリ shall throw a `ProviderException` with a message that guides the user to install the missing dependency. （可能なら実施。未実施時のデフォルト挙動は `Error: Class not found`。）

---

### Requirement 2: nextBusinessDay — 翌営業日の取得

**Objective:** As a ライブラリ利用者, I want 指定日の翌日以降で最初の営業日を取得できる, so that 締め切りや支払い日などを営業日ベースで計算できる

#### Acceptance Criteria

1. When `nextBusinessDay(DateTimeImmutable $from)` is called, the php-heijitu ライブラリ shall return the earliest business day that falls strictly after `$from` (the date of `$from` itself is not a candidate).
2. When `nextBusinessDay` is called and the immediately following day is a Saturday, Sunday, or holiday, the php-heijitu ライブラリ shall continue advancing until the first day that qualifies as a business day is found.
3. When `nextBusinessDay` is called, the php-heijitu ライブラリ shall apply the same excluded date rules (コンストラクタで指定された除外日付) as `isBusinessDay`.
4. When `nextBusinessDay` generates internal candidate dates, the php-heijitu ライブラリ shall use the execution environment's default timezone (`date_default_timezone_get()`). If no default timezone is configured, it shall fall back to `Asia/Tokyo`.
5. If the `HolidayProvider` throws an exception during `nextBusinessDay`, the php-heijitu ライブラリ shall propagate it to the caller without suppressing it.

---

### Requirement 3: firstBusinessDayOfMonth — 月初営業日の取得

**Objective:** As a ライブラリ利用者, I want 指定年月の最初の営業日を取得できる, so that 月初の処理日や締め日などを正確に算出できる

#### Acceptance Criteria

1. When `firstBusinessDayOfMonth(int $year, int $month)` is called, the php-heijitu ライブラリ shall return the first business day on or after the 1st of the specified year and month.
2. When the 1st of the specified month is not a business day (Saturday, Sunday, 祝日, またはコンストラクタで登録された除外日付), the php-heijitu ライブラリ shall advance day-by-day until a business day within that month is found.
3. When `firstBusinessDayOfMonth` generates internal candidate dates, the php-heijitu ライブラリ shall use the execution environment's default timezone. If no default timezone is configured, it shall fall back to `Asia/Tokyo`.
4. If the `HolidayProvider` throws an exception during `firstBusinessDayOfMonth`, the php-heijitu ライブラリ shall propagate it to the caller without suppressing it.

---

### Requirement 4: firstBusinessDaysOfYear — 年間月初営業日の一括取得

**Objective:** As a ライブラリ利用者, I want 指定年の各月の最初の営業日を一度に取得できる, so that 年間の月初処理スケジュールを一括生成できる

#### Acceptance Criteria

1. When `firstBusinessDaysOfYear(int $year)` is called, the php-heijitu ライブラリ shall return an array of exactly 12 `DateTimeImmutable` values (index 0 = 1月, index 11 = 12月).
2. Each element in the returned array shall be the first business day of the corresponding month, consistent with `firstBusinessDayOfMonth` の動作.
3. If the `HolidayProvider` throws an exception during `firstBusinessDaysOfYear`, the php-heijitu ライブラリ shall propagate it to the caller without suppressing it.

---

### Requirement 5: holidays — 期間内祝日一覧の取得

**Objective:** As a ライブラリ利用者, I want 指定期間内の祝日一覧を取得できる, so that カレンダー表示や祝日に基づく処理に利用できる

#### Acceptance Criteria

1. When `holidays(DateTimeImmutable $from, DateTimeImmutable $to)` is called, the php-heijitu ライブラリ shall return the `Holiday[]` produced by the provider's `holidaysBetween($from, $to)` without modification.
2. The php-heijitu ライブラリ shall not apply the constructor-supplied excluded dates when returning holidays; the result reflects only the provider's holiday data.
3. If the `HolidayProvider` throws an exception during `holidays`, the php-heijitu ライブラリ shall propagate it to the caller without suppressing it.

---

### Requirement 6: テスト

**Objective:** As a php-heijitu 開発者, I want 全 API の振る舞いを確認するテスト, so that 実装の正確性を継続的に検証できる

#### Acceptance Criteria

1. The php-heijitu プロジェクト shall provide a `Providers/HolidayJp/ProviderTest` that verifies `isHoliday`, `holidayName`, and `holidaysBetween` using known Japanese public holiday dates.
2. The php-heijitu プロジェクト shall extend `BusinessCalendarTest` with tests for `nextBusinessDay` (翌日が週末・祝日の場合のスキップを含む), `firstBusinessDayOfMonth` (1日が祝日の場合の翌営業日返却を含む), `firstBusinessDaysOfYear` (12件返却の確認), and `holidays` (指定期間の祝日が正しく返る確認).
3. The php-heijitu プロジェクト shall include tests verifying that the constructor-supplied excluded dates (コンストラクタ引数指定・設定ファイル指定) are correctly respected by `nextBusinessDay`, `firstBusinessDayOfMonth`, and `firstBusinessDaysOfYear`.
4. When the test suite is executed (integration グループを除く), the php-heijitu プロジェクト shall produce no failures on PHP 7.4 and PHP 8.1.
5. Tests that require network access (オンライン取得を伴うテスト) shall be annotated with `@group integration` and shall not run during normal `phpunit` execution.
