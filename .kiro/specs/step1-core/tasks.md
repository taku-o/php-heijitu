# Implementation Plan

## Task 1: Foundation — プロジェクト設定と開発環境のセットアップ

- [ ] 1.1 Composer パッケージ定義を作成する
  - `composer.json` を作成し、パッケージ名 `taku-o/php-heijitu`、タイプ `library`、ライセンス MIT を宣言する
  - `require` には PHP バージョン制約 `^7.4 || ^8.0 || ^8.1` のみを記載し、外部パッケージは記載しない
  - `suggest` に `holiday-jp/holiday_jp`、`google/apiclient`、`symfony/yaml`、`ext-mbstring` を記載する
  - `require-dev` に `phpunit/phpunit ^9.6`・`symfony/yaml ^5.4`・プロバイダー依存を記載する
  - PSR-4 オートロードで `Heijitu\` を `src/`、`Heijitu\Tests\` を `tests/` に対応させる
  - `composer.json` に構文エラーがなく、`composer validate` が通る状態になっている
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6_

- [ ] 1.2 PHPUnit 設定ファイルを作成する
  - `phpunit.xml` を作成し、`bootstrap="vendor/autoload.php"` を設定する
  - テストスイートを `tests/` ディレクトリに向ける
  - `vendor/bin/phpunit --list-suites` を実行してテストランナーが起動する状態になっている
  - _Requirements: 1.5, 2.3_

- [ ] 1.3 Docker 開発環境を構築し composer install を実行する
  - `docker/Dockerfile` を作成し、`ARG PHP_VERSION` で 7.4 / 8.1 を切り替えられるようにする。`ext-mbstring` を導入する
  - `docker/compose.yaml` に `php74`・`php81` の 2 サービスを定義する
  - `docker compose run php74 composer install` を実行して `vendor/` が生成されることを確認する
  - `docker compose run php74 vendor/bin/phpunit --version` でテストランナーが起動することを確認する
  - _Requirements: 2.3_

---

## Task 2: Core — 例外型・値オブジェクトの実装

- [ ] 2.1 (P) 例外型を実装する
  - `src/Exception/HeijituException.php` に `interface HeijituException {}` を作成する（メソッドなし）
  - `src/Exception/ConfigException.php` に `class ConfigException extends \RuntimeException implements HeijituException {}` を作成する
  - `src/Exception/ProviderException.php` に `class ProviderException extends \RuntimeException implements HeijituException {}` を作成する
  - PHP で `try { throw new ConfigException(); } catch (HeijituException $e) {}` が構文エラーなく評価できる状態になっている
  - _Requirements: 9.1, 9.2, 9.3, 9.4_
  - _Boundary: Exception Layer_

- [ ] 2.2 (P) MonthDay 値オブジェクトを実装する
  - `src/MonthDay.php` に `final class MonthDay` を作成し、PHP 7.4 の型付きプロパティ `private int $month`・`private int $day` を使用する
  - `getMonth(): int`・`getDay(): int` の getter を実装する
  - `matches(\DateTimeImmutable $t): bool` を実装する。`(int) $t->format('n')` で月、`(int) $t->format('j')` で日を取得して比較する
  - バリデーションは行わない（2月30日などの存在しない日付は `matches()` が常に `false` を返す）
  - `new MonthDay(8, 15)->matches(new \DateTimeImmutable('2024-08-15'))` が `true` を返すことが確認できる
  - _Requirements: 3.1, 3.2, 3.3, 3.4_
  - _Boundary: Core Types_

- [ ] 2.3 (P) Holiday 値オブジェクトを実装する
  - `src/Holiday.php` に `final class Holiday` を作成し、`private \DateTimeImmutable $date`・`private string $name` を型付きプロパティで持たせる
  - `getDate(): \DateTimeImmutable`・`getName(): string` の getter を実装する
  - `new Holiday(new \DateTimeImmutable('2025-01-01'), '元日')` でインスタンスを生成し getter で値を取得できる状態になっている
  - _Requirements: 4.1_
  - _Boundary: Core Types_

- [ ] 2.4 HolidayProvider インターフェースを定義する
  - `src/HolidayProvider.php` に `interface HolidayProvider` を作成する
  - `isHoliday(\DateTimeImmutable $t): bool` を定義する
  - `holidayName(\DateTimeImmutable $t): string` を定義する（非祝日時は空文字返却の PHPDoc を追記）
  - `holidaysBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array` を定義する（`@return Holiday[]` PHPDoc を追記）
  - 3 メソッドをすべて実装した匿名クラス `new class implements HolidayProvider { ... }` が PHP エラーなく評価できる状態になっている
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7_
  - _Depends: 2.3_

---

## Task 3: Config Loader — 設定ファイル読み込みの実装

- [ ] 3.1 (P) Config::loadExcludedDates() を実装する
  - `src/Config.php` に `final class Config` と `public static function loadExcludedDates(string $path): array` を作成する
  - 拡張子 `.yaml`・`.yml` のとき `\Symfony\Component\Yaml\Yaml::parseFile()` でパースする。`class_exists` で `symfony/yaml` 未インストールを検出した場合は `ConfigException` を throw する
  - 拡張子 `.json` のとき `file_get_contents` + `json_decode` でパースする。デコード失敗（`null` 返却）時は `ConfigException` を throw する
  - 拡張子が `.yaml`・`.yml`・`.json` 以外のとき `ConfigException` を throw する
  - `file_get_contents` が失敗（ファイル非存在・権限なし）した場合は `ConfigException` を throw する
  - パース結果の `excluded_dates` 配列の各エントリを `new MonthDay($entry['month'], $entry['day'])` に変換して `MonthDay[]` として返す
  - `Config::loadExcludedDates('path/to/config.yaml')` が `MonthDay[]` を返す状態になっている
  - _Requirements: 6.3, 8.1, 8.2, 8.3, 8.4, 8.5, 9.3_
  - _Depends: 2.1, 2.2_
  - _Boundary: Config Loader_
  （Task 2.4 HolidayProvider 定義と並列可能 — 異なるバウンダリ）

---

## Task 4: Business Logic — BusinessCalendar の実装

- [ ] 4.1 BusinessCalendar を実装する
  - `src/BusinessCalendar.php` に `final class BusinessCalendar` を作成する
  - コンストラクタ `__construct(HolidayProvider $provider, array $excludedDates = [])` を実装する。`foreach ($excludedDates as $item)` で各要素が `MonthDay` でない場合は `\InvalidArgumentException` を throw する
  - `isBusinessDay(\DateTimeImmutable $t, MonthDay ...$extraExcluded): bool` を実装する。判定順: ①`(int) $t->format('N')` が 6（土）または 7（日）→ `false`、②`$this->provider->isHoliday($t)` が `true` → `false`、③`$this->isExcluded($t, $this->excludedDates)` が `true` → `false`、④`$this->isExcluded($t, $extraExcluded)` が `true` → `false`、⑤ → `true`
  - `private function isExcluded(\DateTimeImmutable $t, array $dates): bool` を実装する。`foreach` で各 `MonthDay::matches($t)` を呼び出す
  - `$provider->isHoliday()` が throw した例外は `isBusinessDay()` でキャッチせずそのまま伝播させる
  - モックプロバイダー（匿名クラス）で `isBusinessDay()` が期待通り `true`/`false` を返すことが PHP スクリプトで確認できる状態になっている
  - _Requirements: 6.1, 6.2, 6.4, 6.5, 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 9.5_
  - _Depends: 2.4, 3.1_

---

## Task 5: Tests — 単体テストの実装と動作確認

- [ ] 5.1 (P) MonthDay の単体テストを実装する
  - `tests/MonthDayTest.php` を作成する
  - 月・日ともに一致する日付で `matches()` が `true` を返すことをテストする（異なる年でも `true`）
  - 月が不一致の場合に `false` を返すことをテストする
  - 日が不一致の場合に `false` を返すことをテストする
  - 存在しない日付（月 2・日 30）で `matches()` が常に `false` を返すことをテストする
  - うるう年の 2 月 29 日で一致する年に `true`、平年に `false` を返すことをテストする
  - `docker compose run php74 vendor/bin/phpunit --filter MonthDayTest` が全テストパスする
  - _Requirements: 3.1, 3.2, 3.3, 3.4_
  - _Boundary: Core Types_

- [ ] 5.2 (P) Config の単体テストとテストフィクスチャを実装する
  - `tests/testdata/config.yaml` を作成する（`excluded_dates` に 2 件以上のエントリを含む）
  - `tests/testdata/config.json` を作成する（同様の内容）
  - `tests/ConfigTest.php` を作成し、YAML ファイルから `MonthDay[]` を正しく読み込むことをテストする
  - JSON ファイルから `MonthDay[]` を正しく読み込むことをテストする
  - 未対応拡張子（`.txt` など）で `ConfigException` を throw することをテストする
  - 不正フォーマット内容で `ConfigException` を throw することをテストする
  - 存在しないファイルパスで `ConfigException` を throw することをテストする
  - `docker compose run php74 vendor/bin/phpunit --filter ConfigTest` が全テストパスする
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 9.3_
  - _Boundary: Config Loader_

- [ ] 5.3 BusinessCalendar の単体テストを実装する
  - `tests/BusinessCalendarTest.php` を作成する。各テストメソッド内で匿名クラスを用いて `HolidayProvider` を実装する
  - 土曜日に `isBusinessDay()` が `false` を返すことをテストする
  - 日曜日に `isBusinessDay()` が `false` を返すことをテストする
  - `isHoliday()` が `true` を返す日に `false` を返すことをテストする
  - コンストラクタに渡した `$excludedDates` に一致する日に `false` を返すことをテストする
  - `Config::loadExcludedDates()` の結果と直接渡した配列をマージし、ファイル由来の除外日付でも `false` を返すことをテストする（testdata を使用）
  - `$extraExcluded` に一致する日に `false` を返し、他の呼び出しに影響しないことをテストする
  - 平日・非祝日・除外日付なしの場合に `true` を返すことをテストする
  - `isHoliday()` が例外を throw した場合に `isBusinessDay()` が同じ例外を伝播することをテストする
  - `$excludedDates` に非 `MonthDay` 要素を渡したとき `\InvalidArgumentException` が throw されることをテストする
  - `docker compose run php74 vendor/bin/phpunit --filter BusinessCalendarTest` が全テストパスする
  - _Requirements: 6.1, 6.2, 6.4, 6.5, 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 9.5_
  - _Depends: 5.2_

- [ ] 5.4 PHP 7.4 / 8.1 デュアルバージョンでのテスト実行確認
  - `docker compose run php74 vendor/bin/phpunit` を実行し、全テストスイートがパスすることを確認する
  - `docker compose run php81 vendor/bin/phpunit` を実行し、全テストスイートがパスすることを確認する
  - `docker compose run php81 vendor/bin/phpunit 2>&1 | grep -i "deprecated"` の出力が空であることを確認し、PHP 8.1 での deprecation 警告ゼロを確認する
  - 両バージョンで全テストグリーン + deprecation 警告ゼロが確認できた状態になっている
  - _Requirements: 2.1, 2.2, 2.3_
  - _Depends: 5.1, 5.2, 5.3_
