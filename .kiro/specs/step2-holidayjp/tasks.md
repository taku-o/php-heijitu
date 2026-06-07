# Implementation Plan

- [x] 1. (P) HolidayJp プロバイダーの実装
- [x] 1.1 Provider クラスの骨格を作成し、依存未導入時の検出を実装する
  - `src/Providers/HolidayJp/Provider.php` を新規作成し、名前空間 `Heijitu\Providers\HolidayJp` を宣言する
  - `final class Provider implements HolidayProvider` を宣言し、インターフェースの 3 メソッドスタブを配置する
  - コンストラクタで `class_exists(\HolidayJp\HolidayJp::class)` を確認し、false の場合は `ProviderException` を throw する
  - `composer dump-autoload` 後に `new \Heijitu\Providers\HolidayJp\Provider()` がオートロードエラーなく実行できる
  - _Requirements: 1.1, 1.8_
  - _Boundary: HolidayJp\Provider_

- [x] 1.2 isHoliday と holidayName を実装する
  - `isHoliday(\DateTimeImmutable $t): bool` — `new \DateTime($t->format('Y-m-d'))` に変換して `\HolidayJp\HolidayJp::isHoliday()` に委譲する
  - `holidayName(\DateTimeImmutable $t): string` — `\HolidayJp\HolidayJp\Holidays::$holidays[$t->format('Y-m-d')]['name'] ?? ''` でキー `Y-m-d` 形式を直接参照する
  - 既知祝日（`2020-01-01`）で `isHoliday` が `true` を、非祝日（`2020-01-02`）で `false` を返す
  - `holidayName` が祝日で非空文字、非祝日で空文字 `''` を返す
  - _Requirements: 1.2, 1.3, 1.4, 1.5_
  - _Boundary: HolidayJp\Provider_

- [x] 1.3 holidaysBetween を実装する
  - `holidaysBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array` — `\HolidayJp\HolidayJp::between()` に変換後の `\DateTime` を渡して実行する
  - 戻り値の各要素の `$entry['date']`（`DateTime`）を `\DateTimeImmutable::createFromMutable()` で変換して `Holiday` オブジェクトを構築する
  - `usort()` で日付昇順ソートを明示的に適用する
  - `from > to` のとき空配列を返す（`between()` の自然な動作に委ねる）
  - `holidaysBetween(2020-01-01, 2020-01-13)` が元日・成人の日を含む `Holiday[]` を昇順で返す
  - _Requirements: 1.6, 1.7_
  - _Boundary: HolidayJp\Provider_

- [x] 2. (P) BusinessCalendar 残り API の実装
- [x] 2.1 nextBusinessDay を実装する
  - `nextBusinessDay(\DateTimeImmutable $from): \DateTimeImmutable` を `BusinessCalendar` に追加する
  - `date_default_timezone_get()` で TZ を取得し `new \DateTimeZone($tz)` を生成する
  - `new \DateTimeImmutable($from->format('Y-m-d'), $tz)->modify('+1 day')` を起点に `isBusinessDay($candidate)` が true になるまで 1 日ずつ前進させる
  - コンストラクタの `excludedDates` が `isBusinessDay()` 経由で自動適用される
  - プロバイダーが例外を throw した場合はそのまま伝播する
  - `nextBusinessDay(new DateTimeImmutable('2020-01-10'))` が `2020-01-13` を返す（週末スキップ）
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_
  - _Boundary: BusinessCalendar_

- [x] 2.2 firstBusinessDayOfMonth を実装する
  - `firstBusinessDayOfMonth(int $year, int $month): \DateTimeImmutable` を追加する
  - `date_default_timezone_get()` で TZ を取得し `new \DateTimeZone($tz)` を生成する
  - `new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $tz)` を起点に `isBusinessDay()` でループする
  - コンストラクタの `excludedDates` が `isBusinessDay()` 経由で自動適用される
  - プロバイダーが例外を throw した場合はそのまま伝播する
  - `firstBusinessDayOfMonth(2020, 1)` が `2020-01-02` を返す（1/1 が元日のため）
  - _Requirements: 3.1, 3.2, 3.3, 3.4_
  - _Boundary: BusinessCalendar_

- [x] 2.3 firstBusinessDaysOfYear を実装する
  - `firstBusinessDaysOfYear(int $year): array` を追加し、`firstBusinessDayOfMonth` を 1〜12 月で呼び出す
  - 戻り値は必ず 12 件の `\DateTimeImmutable[]`（index 0 = 1月、index 11 = 12月）
  - プロバイダーが例外を throw した場合はそのまま伝播する
  - `firstBusinessDaysOfYear(2020)` が要素数 12 の配列を返す
  - _Requirements: 4.1, 4.2, 4.3_
  - _Boundary: BusinessCalendar_

- [x] 2.4 holidays を実装する
  - `holidays(\DateTimeImmutable $from, \DateTimeImmutable $to): array` を追加する
  - `return $this->provider->holidaysBetween($from, $to)` のみで実装する（変換・フィルタなし）
  - コンストラクタの `excludedDates` はこの API に影響しない
  - プロバイダーが例外を throw した場合はそのまま伝播する
  - `holidays(2020-01-01, 2020-01-13)` がプロバイダーの返す `Holiday[]` をそのまま返す
  - _Requirements: 5.1, 5.2, 5.3_
  - _Boundary: BusinessCalendar_

- [x] 3. テスト実装
- [x] 3.1 (P) HolidayJp\ProviderTest を実装する
  - `tests/Providers/HolidayJp/ProviderTest.php` を新規作成し、名前空間 `Heijitu\Tests\Providers\HolidayJp` を宣言する（PSR-4 で `tests/` 配下に自動対応済み）
  - 以下のテストメソッドを実装する:
    - `testIsHolidayReturnsTrueForKnownHoliday` — `2020-01-01` → `true`
    - `testIsHolidayReturnsFalseForNonHoliday` — `2020-01-02` → `false`
    - `testHolidayNameReturnsNameForKnownHoliday` — `2020-01-01` → `'元日'`
    - `testHolidayNameReturnsEmptyStringForNonHoliday` — `2020-01-02` → `''`
    - `testHolidaysBetweenReturnsHolidaysInRange` — `2020-01-01`〜`2020-01-13` で祝日 2 件を昇順返却
    - `testHolidaysBetweenReturnsEmptyArrayWhenFromAfterTo` — `2020-01-13`〜`2020-01-01` → `[]`
    - `testHolidaysBetweenIncludesBothEndpoints` — `2020-01-01`〜`2020-01-01` で元日 1 件を返却
  - `phpunit --filter ProviderTest` が全テスト通過する
  - _Requirements: 6.1_
  - _Boundary: HolidayJp\ProviderTest_
  - _Depends: 1.1, 1.2, 1.3_

- [x] 3.2 (P) BusinessCalendarTest に残り API のテストを追記する
  - 既存の `tests/BusinessCalendarTest.php` に以下の 8 テストメソッドを追記する（既存テストは変更しない）:
    - `testNextBusinessDaySkipsWeekend` — 入力 `2020-01-10`（金）、期待値 `2020-01-13`（月）
    - `testNextBusinessDaySkipsHoliday` — 入力 `2019-12-31`、期待値 `2020-01-02`（1/1 が元日のため HolidayJp\Provider 使用）
    - `testNextBusinessDayWithExcludedDates` — 入力 `2020-01-09`（木）、1/10 を除外、期待値 `2020-01-13`（月）
    - `testFirstBusinessDayOfMonthWhenFirstIsHoliday` — `(2020, 1)` → `2020-01-02`（HolidayJp\Provider 使用）
    - `testFirstBusinessDayOfMonthWithExcludedDates` — `(2020, 4)` で 4/1 を除外 → `2020-04-02`
    - `testFirstBusinessDaysOfYearReturns12Entries` — `(2020)` → 要素数 12
    - `testHolidaysReturnsProviderData` — `2020-01-01`〜`2020-01-13` で元日・成人の日を含む結果
    - `testHolidaysIgnoresExcludedDates` — `2020-01-01`〜`2020-01-13` で 1/1 を除外しても元日が返る
  - `phpunit --filter BusinessCalendarTest` が既存テストを含む全テスト通過する
  - _Requirements: 6.2, 6.3_
  - _Boundary: BusinessCalendarTest_
  - _Depends: 1.1, 1.2, 1.3, 2.1, 2.2, 2.3, 2.4_

- [ ] 4. PHP 7.4・8.1 両環境での全テスト通過確認
- [ ] 4.1 Docker 両バージョンで phpunit を実行し全テストが通過することを確認する
  - `docker compose run php74 vendor/bin/phpunit` が全テスト通過する
  - `docker compose run php81 vendor/bin/phpunit` が全テスト通過し、deprecation 警告が出ない
  - Step 1 で実装した既存テスト（isBusinessDay 系）も引き続き通過することを確認する
  - _Requirements: 6.4, 6.5_
  - _Depends: 3.1, 3.2_
