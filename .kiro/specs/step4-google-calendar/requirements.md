# 要件定義書: Step 4 — Google Calendar APIプロバイダー実装

## Introduction

`php-heijitu` に、Google Calendar API から日本の祝日データを取得する `GoogleCalendar` プロバイダーを追加する。

`GoogleCalendar` プロバイダーは API キー認証とサービスアカウント認証の両方をサポートし、Google の日本祝日カレンダーからリアルタイムに祝日情報を取得する。取得した祝日データは `HolidayProvider` インターフェースを通じて `BusinessCalendar` と組み合わせて使用できる。

go-heijitu の googleCalendar プロバイダーの機能を PHP で等価再現することが目的であり、`HolidayJp\Provider` や `CaoCsv\Provider` と同じインターフェースで差し替え可能な実装を提供する。

**APIキーの渡し方（go-heijitu との違い）**:
go-heijitu では環境変数からキーを読み込む設計だったが、php-heijitu では**コンストラクタ引数**で資格情報を受け取る（decisions.md C-3 のコンストラクタ引数方式を踏襲）。APIキーを環境変数で管理したい場合は、呼び出し側で `getenv('GOOGLE_API_KEY')` などを使って値を取得し、プロバイダーのコンストラクタに渡す。ライブラリ自身は環境変数を読まない。

## Boundary Context

- **In scope**:
  - `Providers/GoogleCalendar/Provider.php` — `HolidayProvider` インターフェースを実装するプロバイダークラス
  - API キー認証（`apiKey` パラメータ）
  - サービスアカウント認証（`credentialsFile` パラメータ）
  - Google の日本祝日カレンダーからの全件取得（ページング対応）
  - `isHoliday` / `holidayName` / `holidaysBetween` の実装
  - 資格情報未設定時（`apiKey`・`credentialsFile` 両方空）の `ProviderException`
  - `google/apiclient` 未導入時の `ProviderException` 検出と案内（可能なら実施）
  - `Providers/GoogleCalendar/ProviderTest` のユニットテスト（integration グループ分離を含む）
  - PHP 7.4・8.1 両環境でのオートロード・型エラーなし確認
  - （可能なら）PHP 8.1 上での `google/apiclient` 実機検証（decisions.md D-4）

- **Out of scope**:
  - `BusinessCalendar` の API・実装変更
  - `HolidayProvider` インターフェースの変更
  - `HolidayJp` / `CaoCsv` プロバイダーへの変更
  - examples・PHPDoc・README 等のドキュメント整備（Step 5）
  - オンライン取得結果のキャッシュ機能（go-heijitu 踏襲・キャッシュなし）
  - `ja.japanese.official#holiday@group.v.calendar.google.com` 以外のカレンダーIDの指定
  - OAuth2 ユーザー認証（API キー・サービスアカウントのみ対応）

- **Adjacent expectations**:
  - `HolidayProvider` インターフェースは Step 1 で定義済み。本ステップはその実装を提供する。
  - `BusinessCalendar` は Step 2 で完成済み。`GoogleCalendar\Provider` を `BusinessCalendar` コンストラクタに渡して利用する。
  - `google/apiclient:^2.16` は `composer.json` の `require-dev` に追加済み（Step 1 で方針確定）。利用者は使う場合に自分で `suggest` から取り込む。
  - PHP 8.1 上での `google/apiclient` 動作確認は「可能なら実施・無理ならスキップ可」（decisions.md D-4）。

---

## Requirements

### Requirement 1: GoogleCalendar プロバイダーの実装

**Objective:** As a ライブラリ利用者, I want Google Calendar API から日本の祝日データを取得できるプロバイダー, so that `HolidayJp` や `CaoCsv` と同じ API で最新の祝日情報を利用した `BusinessCalendar` を使えるようになる

#### Acceptance Criteria

1. The php-heijitu ライブラリ shall provide a `GoogleCalendar\Provider` class that implements the `HolidayProvider` interface.
2. When `GoogleCalendar\Provider` is instantiated, the php-heijitu ライブラリ shall connect to Google の日本祝日カレンダー（固定の Calendar ID）から祝日データを取得する。
3. The php-heijitu ライブラリ shall retrieve all holidays from the calendar without limiting to a single page of results（ページングがある場合は全件取得する）.
4. When holiday data is successfully fetched from the Google Calendar API, the php-heijitu ライブラリ shall make that data available for `isHoliday`, `holidayName`, and `holidaysBetween` queries.
5. If the Google Calendar API returns an error during data retrieval, the php-heijitu ライブラリ shall throw a `ProviderException`.

---

### Requirement 2: 認証設定

**Objective:** As a ライブラリ利用者, I want APIキーまたはサービスアカウントで Google Calendar API に認証できる, so that 利用シーンに合わせた認証方式を選択して使用できる

#### Acceptance Criteria

1. When `GoogleCalendar\Provider` is instantiated with an `apiKey` parameter, the php-heijitu ライブラリ shall authenticate to the Google Calendar API using that API key.
2. When `GoogleCalendar\Provider` is instantiated with a `credentialsFile` parameter pointing to a service account JSON file, the php-heijitu ライブラリ shall authenticate using that service account.
3. When both `apiKey` and `credentialsFile` are provided, the php-heijitu ライブラリ shall use `credentialsFile`（サービスアカウント認証）を優先する.
4. If neither `apiKey` nor `credentialsFile` is provided（どちらも空または未指定）when instantiating `GoogleCalendar\Provider`, the php-heijitu ライブラリ shall throw a `ProviderException` at construction time.
5. The php-heijitu ライブラリ shall accept credentials as constructor arguments（ライブラリ自身は環境変数を直接読み込まない）. 資格情報の取得元（環境変数・設定ファイル等）は呼び出し側の責任とする.

---

### Requirement 3: 祝日判定メソッド

**Objective:** As a ライブラリ利用者, I want Google Calendar APIから取得したデータを使った祝日の確認・名前取得・一覧取得, so that `HolidayJp\Provider` や `CaoCsv\Provider` と差し替え可能に Google Calendar の祝日データを利用できる

#### Acceptance Criteria

1. When `isHoliday(DateTimeImmutable $t)` is called for a date that is a holiday in the fetched calendar data, the `GoogleCalendar\Provider` shall return `true`.
2. When `isHoliday(DateTimeImmutable $t)` is called for a date that is not a holiday in the fetched calendar data, the `GoogleCalendar\Provider` shall return `false`.
3. When `holidayName(DateTimeImmutable $t)` is called for a date that is a holiday in the fetched calendar data, the `GoogleCalendar\Provider` shall return the holiday name as a non-empty string.
4. When `holidayName(DateTimeImmutable $t)` is called for a date that is not a holiday in the fetched calendar data, the `GoogleCalendar\Provider` shall return an empty string.
5. When `holidaysBetween(DateTimeImmutable $from, DateTimeImmutable $to)` is called, the `GoogleCalendar\Provider` shall return all holidays within the `$from`–`$to` range（両端含む）as a `Holiday[]` sorted in ascending date order.
6. When `holidaysBetween` is called with a `$from` date that is after `$to`, the `GoogleCalendar\Provider` shall return an empty array.

---

### Requirement 4: google/apiclient 依存の検出と案内

**Objective:** As a ライブラリ利用者, I want `google/apiclient` が未導入のときに明確なエラーメッセージを受け取れる, so that 必要なパッケージのインストール方法をすぐに把握できる

#### Acceptance Criteria

1. If the `google/apiclient` package is not installed when `GoogleCalendar\Provider` is instantiated, the php-heijitu ライブラリ shall throw a `ProviderException` with a message that guides the user to run `composer require google/apiclient`.

---

### Requirement 5: テスト

**Objective:** As a php-heijitu 開発者, I want GoogleCalendar プロバイダーの振る舞いを確認するテスト, so that 実装の正確性を継続的に検証できる

#### Acceptance Criteria

1. The php-heijitu プロジェクト shall provide a `Providers/GoogleCalendar/ProviderTest` that verifies `GoogleCalendar\Provider` throws a `ProviderException` when instantiated without `apiKey` or `credentialsFile`（契約テスト）.
2. Tests that require actual Google Calendar API access（実 API 呼び出しを伴うテスト）shall be annotated with `@group integration` and shall not run during normal `phpunit` execution.
3. When the test suite is executed（integration グループを除く）, the php-heijitu プロジェクト shall produce no failures on PHP 7.4 and PHP 8.1.
4. The php-heijitu プロジェクト shall load `GoogleCalendar\Provider` without autoload errors or type errors on both PHP 7.4 and PHP 8.1.
5. When running integration tests, the php-heijitu プロジェクト shall read API credentials from environment variables（`GOOGLE_API_KEY` または `GOOGLE_CREDENTIALS_FILE`）and pass them to `GoogleCalendar\Provider`. 開発者はこれらの環境変数を設定することで実 API 検証テストを実行できる.
