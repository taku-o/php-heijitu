# 要件定義書: Step 5 — example・PHPDoc・README（ドキュメント整備）

## Introduction

`php-heijitu` を利用者向けに公開できる状態にするため、サンプルコード・PHPDoc・README・APIドキュメントを整備する。

ライブラリのコア実装（Step 1–4）は完成しており、本ステップは利用者が初めてライブラリを使い始めるために必要なすべての成果物（サンプル・リファレンス・ガイド）を揃えることを目的とする。

ドキュメントは英語（en）と日本語（ja）の両方を提供する（decisions.md E-3 確定）。

最終確認として PHP 7.4 と 8.1 の両環境でテストが通ることを検証する。

## Boundary Context

- **In scope**:
  - `examples/main.php` — 全プロバイダー・全 API パターンのサンプル
  - 全公開クラス・メソッドへの PHPDoc（型・引数・戻り値・例外）
  - `README.md`（英語）/ `README-ja.md`（日本語）
  - `CHANGELOG.md` / `CONTRIBUTING.md` / `LICENSE`
  - `docs/en/api-spec.md` / `docs/ja/api-spec.md`
  - `docs/en/usage.md` / `docs/ja/usage.md`
  - `docs/en/providers.md` / `docs/ja/providers.md`
  - PHP 7.4・8.1 の両環境でのテスト通過確認
  - `examples/main.php` の実行確認

- **Out of scope**:
  - コア実装（`BusinessCalendar`・`HolidayProvider` 等）への機能追加・変更
  - プロバイダー実装（`HolidayJp`・`CaoCsv`・`GoogleCalendar`）への機能追加・変更
  - Packagist 公開・タグ付けなどのリリース工程（decisions.md G-2 で追加しないと確定）
  - 静的解析ツール（phpstan 等）の導入（導入可否は要決定の未確定事項）

- **Adjacent expectations**:
  - Step 1–4 の実装は完成済みであることを前提とする。本ステップはドキュメントと確認のみで実装を変更しない。
  - `examples/main.php` は既存の `src/` 実装を使って実行できることを確認する。

---

## Requirements

### Requirement 1: サンプルコード（examples/main.php）

**Objective:** As a ライブラリ利用者, I want 全プロバイダー・全 API を使ったサンプルコード, so that ライブラリの使い方をコードで確認しながらすぐに利用を開始できる

#### Acceptance Criteria

1. The php-heijitu ライブラリ shall provide `examples/main.php` that demonstrates `BusinessCalendar` usage with the `HolidayJp` provider including all public APIs: `isBusinessDay`, `nextBusinessDay`, `firstBusinessDayOfMonth`, `firstBusinessDaysOfYear`, and `holidays`.
2. The php-heijitu ライブラリ shall include in `examples/main.php` a usage pattern that passes `MonthDay[]` to the `BusinessCalendar` constructor as `$excludedDates`.
3. The php-heijitu ライブラリ shall include in `examples/main.php` a usage pattern that loads excluded dates from a configuration file via `Config::loadExcludedDates()` and merges them with constructor arguments using `array_merge`.
4. The php-heijitu ライブラリ shall include in `examples/main.php` a usage pattern for the `CaoCsv` provider in local file mode（`csvPath` 指定）.
5. The php-heijitu ライブラリ shall include in `examples/main.php` a usage pattern for the `CaoCsv` provider in online fetch mode（`csvPath` 空）.
6. The php-heijitu ライブラリ shall include in `examples/main.php` a usage pattern that passes `MonthDay` variadic arguments as `$extraExcluded` to `isBusinessDay`.
7. When `php examples/main.php` is executed, the php-heijitu ライブラリ shall produce the expected output without errors or exceptions.

---

### Requirement 2: PHPDoc（全公開 API）

**Objective:** As a ライブラリ利用者, I want 全公開クラス・メソッドに PHPDoc が付いている, so that IDE の補完・型チェックを利用しながら迷わずライブラリを使える

#### Acceptance Criteria

1. The php-heijitu ライブラリ shall provide PHPDoc on all public classes in `src/` including `BusinessCalendar`, `HolidayProvider`, `Holiday`, `MonthDay`, and `Config`.
2. The php-heijitu ライブラリ shall provide PHPDoc on all public methods including `@param` annotations for each parameter, `@return` annotation for the return type, and `@throws` annotation when the method throws an exception.
3. Where a method returns an array of typed objects（例: `Holiday[]`、`DateTimeImmutable[]`、`MonthDay[]`）, the php-heijitu ライブラリ shall express the element type in `@return` using PHPDoc array notation（例: `@return Holiday[]`）.
4. The php-heijitu ライブラリ shall provide PHPDoc on all public classes and methods in provider classes: `Providers/HolidayJp/Provider`, `Providers/CaoCsv/Provider`, and `Providers/GoogleCalendar/Provider`.
5. The php-heijitu ライブラリ shall provide PHPDoc on all public classes and methods in exception classes: `Exception/HeijituException`, `Exception/ConfigException`, and `Exception/ProviderException`.

---

### Requirement 3: README・ルートドキュメント（en/ja 両方）

**Objective:** As a ライブラリ利用者, I want README を読んでインストールから使い始めまでの手順がわかる, so that ドキュメントを参照するだけで初期セットアップを完了できる

#### Acceptance Criteria

1. The php-heijitu ライブラリ shall provide `README.md`（英語）and `README-ja.md`（日本語）at the repository root.
2. The php-heijitu ライブラリ shall include in each README: インストール手順（GitHub VCS 配布・`repositories` 設定・`composer require`）、基本的な使用例、ライセンス情報への参照.
3. The php-heijitu ライブラリ shall document in each README that `holiday-jp/holiday_jp` のデータは 2020 年で更新停止しており、2021 年以降の祝日については `CaoCsv` または `GoogleCalendar` プロバイダーを使うことを案内する（decisions.md E-1 の制限明記）.
4. The php-heijitu ライブラリ shall document in each README that JST 運用を推奨し、実行環境のタイムゾーンを `Asia/Tokyo` に設定する手順を案内する（decisions.md E-2）.
5. The php-heijitu ライブラリ shall provide `CHANGELOG.md` at the repository root.
6. The php-heijitu ライブラリ shall provide `CONTRIBUTING.md` at the repository root.
7. The php-heijitu ライブラリ shall provide `LICENSE` file at the repository root containing the MIT license.

---

### Requirement 4: API仕様・使い方・プロバイダーガイド（en/ja 両方）

**Objective:** As a ライブラリ利用者, I want 詳細なAPIリファレンス・使い方ガイド・プロバイダー選択ガイドを参照できる, so that 具体的なユースケースや各プロバイダーの設定方法を調べながら利用できる

#### Acceptance Criteria

1. The php-heijitu ライブラリ shall provide `docs/en/api-spec.md` and `docs/ja/api-spec.md` that document all public types（`BusinessCalendar`、`HolidayProvider`、`Holiday`、`MonthDay`、`Config`、例外クラス）and all public methods with signatures, parameters, return types, and exceptions.
2. The php-heijitu ライブラリ shall include in `api-spec.md` the configuration file specification（YAML・JSON フォーマット・`excluded_dates` の構造）.
3. The php-heijitu ライブラリ shall provide `docs/en/usage.md` and `docs/ja/usage.md` covering: インストール手順、`BusinessCalendar` の基本的な使い方、コンストラクタ引数による除外日付指定、設定ファイルによる除外日付指定、各 API（`isBusinessDay`・`nextBusinessDay`・`firstBusinessDayOfMonth`・`firstBusinessDaysOfYear`・`holidays`）のユースケース別の使い方.
4. The php-heijitu ライブラリ shall provide `docs/en/providers.md` and `docs/ja/providers.md` covering: 3プロバイダー（`HolidayJp`・`CaoCsv`・`GoogleCalendar`）の選択基準・設定方法・注意点.
5. The php-heijitu ライブラリ shall include in `providers.md` the Google Calendar API キーの取得手順、コンストラクタへの渡し方、環境変数連携パターン（`getenv('GOOGLE_API_KEY')` での取得例）.
6. The php-heijitu ライブラリ shall document in `providers.md` that `holiday-jp/holiday_jp` データは 2020 年で更新停止しており、本番運用には `CaoCsv` または `GoogleCalendar` を推奨する旨を明記する.

---

### Requirement 5: 最終確認（PHP 7.4・8.1 両環境でのテスト通過）

**Objective:** As a php-heijitu 開発者, I want PHP 7.4 と 8.1 の両環境でテストが全て通ることを確認できる, so that ライブラリが対象とする全 PHP バージョンで動作することを保証できる

#### Acceptance Criteria

1. When the PHPUnit test suite is executed（integration グループを除く）on PHP 7.4, the php-heijitu プロジェクト shall produce no test failures.
2. When the PHPUnit test suite is executed（integration グループを除く）on PHP 8.1, the php-heijitu プロジェクト shall produce no test failures.
3. When the PHPUnit test suite is executed on PHP 8.1, the php-heijitu プロジェクト shall produce no deprecation warnings for PHP 8.x で非推奨化された書き方（「暗黙的に null 許容な型宣言」等）.
4. When `php examples/main.php` is executed on PHP 7.4, the php-heijitu ライブラリ shall produce the expected output without errors or exceptions.
5. When `php examples/main.php` is executed on PHP 8.1, the php-heijitu ライブラリ shall produce the expected output without errors or exceptions.
