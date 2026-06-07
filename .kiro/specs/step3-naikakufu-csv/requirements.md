# 要件定義書: Step 3 — 内閣府CSVプロバイダー実装

## Introduction

`php-heijitu` に、内閣府が公開する「国民の祝日」CSVデータを祝日ソースとして使用する `CaoCsv` プロバイダーを追加する。

`CaoCsv` プロバイダーはローカルCSVファイル読み込みとオンライン取得の2つのモードをサポートし、Shift_JIS エンコードのCSVを PHP 標準機能でデコード・パースする。追加の Composer 依存はゼロ。

go-heijitu の caoCsv プロバイダーの機能を PHP 標準機能で等価再現することが目的であり、`HolidayJp\Provider` と同じ `HolidayProvider` インターフェースを通じて `BusinessCalendar` と組み合わせて使用できる。

## Boundary Context

- **In scope**:
  - `Providers/CaoCsv/Provider.php` — `HolidayProvider` インターフェースを実装するプロバイダークラス
  - ローカルCSVモード：`csvPath` 指定時にローカルファイルからCSVを読み込む
  - オンライン取得モード：`csvPath` 未指定時に内閣府固定URLからCSVを取得する
  - Shift_JIS エンコードデータの UTF-8 変換とCSVパース（PHP 標準機能のみ使用）
  - `isHoliday` / `holidayName` / `holidaysBetween` の実装
  - `ext-mbstring` 未導入時の `ProviderException` 検出と案内
  - テスト用 Shift_JIS CSVフィクスチャ（`tests/Providers/CaoCsv/testdata/syukujitsu_test.csv`）
  - `Providers/CaoCsv/ProviderTest` のユニットテスト（integration グループ分離を含む）
  - Docker 両バージョン（PHP 7.4・8.1）での全テスト通過確認

- **Out of scope**:
  - `BusinessCalendar` の API・実装変更
  - `HolidayProvider` インターフェースの変更
  - `GoogleCalendar` プロバイダー実装（Step 4）
  - examples・PHPDoc・README 等のドキュメント整備（Step 5）
  - オンライン取得時のキャッシュ機能（go-heijitu 踏襲・キャッシュなし）
  - 内閣府以外の任意URLからのCSV取得（go-heijitu 踏襲・任意URL不可）
  - オンライン取得手段として追加の Composer パッケージを導入すること

- **Adjacent expectations**:
  - `HolidayProvider` インターフェースは Step 1 で定義済み。本ステップはその実装を提供する。
  - `BusinessCalendar` は Step 2 で完成済み。`CaoCsv\Provider` を `BusinessCalendar` コンストラクタに渡して利用する。
  - 内閣府CSV（内閣府固定URL）のフォーマットは「YYYY/MM/DD,祝日名」の2列 Shift_JIS CSV（1行目はヘッダー行）であることを前提とする。フォーマット変更はライブラリの制御外。
  - `ext-mbstring` は Shift_JIS デコードに必要な PHP 拡張であり、`composer.json` の `suggest` に記載済み（Step 1 で確定）。

---

## Requirements

### Requirement 1: CaoCsv プロバイダーの実装

**Objective:** As a ライブラリ利用者, I want 内閣府公式CSVを使った祝日判定プロバイダー, so that 追加の Composer 依存なしに公式データを使って `BusinessCalendar` を利用できる

#### Acceptance Criteria

1. The php-heijitu ライブラリ shall provide a `CaoCsv\Provider` class that implements the `HolidayProvider` interface.
2. When `CaoCsv\Provider` is instantiated with a `csvPath` option pointing to a local file, the php-heijitu ライブラリ shall read and parse the CSV data from that local file path.
3. When `CaoCsv\Provider` is instantiated without a `csvPath` option (またはcsvPathが空文字), the php-heijitu ライブラリ shall fetch CSV data from the Cabinet Office fixed URL without requiring any additional Composer packages.
4. When the loaded CSV data is Shift_JIS encoded, the php-heijitu ライブラリ shall correctly interpret Japanese characters in holiday names and dates (Shift_JIS を UTF-8 に変換して内部保持する).
5. If the local CSV file specified by `csvPath` cannot be read, the php-heijitu ライブラリ shall throw a `ProviderException`.
6. If fetching the CSV from the Cabinet Office URL fails, the php-heijitu ライブラリ shall throw a `ProviderException`.

---

### Requirement 2: 祝日判定メソッド

**Objective:** As a ライブラリ利用者, I want CSVデータを使った祝日の確認・名前取得・一覧取得, so that `HolidayJp\Provider` と同等の祝日判定を内閣府公式データで行える

#### Acceptance Criteria

1. When `isHoliday(DateTimeImmutable $t)` is called for a date present in the loaded CSV data, the `CaoCsv\Provider` shall return `true`.
2. When `isHoliday(DateTimeImmutable $t)` is called for a date not present in the loaded CSV data, the `CaoCsv\Provider` shall return `false`.
3. When `holidayName(DateTimeImmutable $t)` is called for a date present in the loaded CSV data, the `CaoCsv\Provider` shall return the holiday name as a non-empty string.
4. When `holidayName(DateTimeImmutable $t)` is called for a date not present in the loaded CSV data, the `CaoCsv\Provider` shall return an empty string.
5. When `holidaysBetween(DateTimeImmutable $from, DateTimeImmutable $to)` is called, the `CaoCsv\Provider` shall return all holidays within the `$from`–`$to` range (両端含む) as a `Holiday[]` sorted in ascending date order.
6. When `holidaysBetween` is called with a `$from` date that is after `$to`, the `CaoCsv\Provider` shall return an empty array.

---

### Requirement 3: mbstring 依存の検出と案内

**Objective:** As a ライブラリ利用者, I want `ext-mbstring` が未導入のときに明確なエラーメッセージを受け取れる, so that 必要な拡張のインストール方法をすぐに把握できる

#### Acceptance Criteria

1. If the `mbstring` PHP extension is not loaded when `CaoCsv\Provider` is used, the php-heijitu ライブラリ shall throw a `ProviderException` with a message that guides the user to install `ext-mbstring`.

---

### Requirement 4: テスト

**Objective:** As a php-heijitu 開発者, I want CaoCsv プロバイダーの振る舞いを確認するテスト, so that 実装の正確性を継続的に検証できる

#### Acceptance Criteria

1. The php-heijitu プロジェクト shall provide a `Providers/CaoCsv/ProviderTest` that verifies `isHoliday`, `holidayName`, and `holidaysBetween` using a local Shift_JIS CSV fixture at `tests/Providers/CaoCsv/testdata/syukujitsu_test.csv`.
2. The php-heijitu プロジェクト shall include tests that verify the local CSV mode reads data from the `csvPath`-specified file.
3. Tests that require network access (内閣府CSVのオンライン取得を伴うテスト) shall be annotated with `@group integration` and shall not run during normal `phpunit` execution.
4. When the test suite is executed (integration グループを除く), the php-heijitu プロジェクト shall produce no failures on PHP 7.4 and PHP 8.1.
