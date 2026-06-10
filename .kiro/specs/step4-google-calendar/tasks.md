# Implementation Plan

- [x] 1. google/apiclient のインストールと PHP 7.4 互換確認
- [x] 1.1 composer install と解決バージョンの確認
  - `docker compose -f docker/compose.yaml run --rm php74 composer install` を実行する
  - `docker compose -f docker/compose.yaml run --rm php74 composer show google/apiclient` でバージョンが `2.16.x` であることを確認する（v2.17+ が解決されていないことを確認）
  - `php74` サービス上で `\Google\Client` クラスがオートロードできることを確認する（`class_exists(\Google\Client::class)` が `true`）
  - Observable: `php74` 環境で `vendor/google/apiclient` が存在し、`2.16.x` が解決されており、`Google\Client` クラスがオートロードできる
  - _Requirements: 5.4_

- [x] 2. プロバイダー骨格（コンストラクタ・依存検出・認証検証）の実装
- [x] 2.1 GoogleCalendar\Provider のコンストラクタ実装
  - `src/Providers/GoogleCalendar/Provider.php` を `declare(strict_types=1)` 付きで新規作成する（`Heijitu\Providers\GoogleCalendar` 名前空間）
  - `class_exists(\Google\Client::class)` で `google/apiclient` の導入を確認し、未導入時は `composer require google/apiclient` のインストールを案内する `ProviderException` を throw する
  - `$apiKey` と `$credentialsFile` が両方空の場合、認証情報の指定方法を案内する `ProviderException` を throw する
  - 問題なければ `$apiKey`・`$credentialsFile` をプロパティに保存してコンストラクタを完了する
  - `CALENDAR_ID` 定数（`ja.japanese.official#holiday@group.v.calendar.google.com`）を `private const` で定義する
  - Observable: `new Provider('', '')` が `ProviderException` を throw し、`new Provider('dummy_key', '')` がインスタンス生成に成功する
  - _Requirements: 1.1, 2.4, 2.5, 4.1_
  - _Depends: 1.1_

- [x] 3. API 通信ロジック（buildService・fetchEvents）の実装
- [x] 3.1 buildService の実装（認証クライアント初期化）
  - `buildService()` を実装する（戻り値型は `object`）
  - `$credentialsFile` が非空なら `setAuthConfig` + `addScope(CALENDAR_READONLY)` でサービスアカウント認証を設定し、そうでなければ `setDeveloperKey($this->apiKey)` で API キー認証を設定する
  - `Google\Service\Calendar` インスタンスを返す
  - Observable: ダミー API キーで `buildService()` が例外なく `object` 型を返す（ネットワーク接続なし）
  - _Requirements: 2.1, 2.2, 2.3_
  - _Depends: 2.1_

- [x] 3.2 fetchEvents の実装（API呼び出し・ページング・フィルタリング）
  - `fetchEvents(\DateTimeImmutable $from, \DateTimeImmutable $to): array` を実装する
  - `fetchEvents()` 内部で `buildService()` を呼んで Calendar サービスを取得する
  - `events->listEvents()` を `singleEvents=true`・`orderBy=startTime`・`maxResults=2500`・`timeMin`（`$from` の 0 時 0 分 0 秒）・`timeMax`（`$to` の翌日 0 時 0 分 0 秒）で呼び出す
  - `getNextPageToken()` が空になるまでページングループを実行し、全件を収集する
  - `start->getDate() !== null` の終日イベントのみを `['YYYY-MM-DD' => 祝日名]` の形式で返す
  - `buildService()` および `listEvents()` の両方を try/catch で囲み、例外を元例外を `$previous` とした `ProviderException` に変換する
  - Observable: `php -l src/Providers/GoogleCalendar/Provider.php` が通り、`php74`・`php81` 両環境でクラスが型エラーなくオートロードされる
  - _Requirements: 1.2, 1.3, 1.4, 1.5_
  - _Depends: 3.1_

- [x] 4. 祝日判定メソッドの実装
- [x] 4.1 isHoliday / holidayName / holidaysBetween の実装
  - `isHoliday(\DateTimeImmutable $t): bool` を実装する（`fetchEvents($t, $t)` を呼び、`$t->format('Y-m-d')` がキーとして存在するか確認）
  - `holidayName(\DateTimeImmutable $t): string` を実装する（`fetchEvents($t, $t)` を呼び、該当キーの値を返す。非祝日は `''`）
  - `holidaysBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array` を実装する（`$from > $to` のとき API を呼ばず空配列を早期リターン。`fetchEvents($from, $to)` の結果から `Holiday[]` を構築し `usort()` で昇順ソートして返す。CaoCsv\Provider と同一のソートパターン）
  - Observable: `php -l` が通り、`$from > $to` の `holidaysBetween` が例外なく空配列を返す（integration テストで追加検証）
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6_
  - _Depends: 3.2_

- [x] 5. テストの実装

- [x] 5.1 ユニットテスト（契約テスト）の実装
  - `tests/Providers/GoogleCalendar/ProviderTest.php` を `Heijitu\Tests\Providers\GoogleCalendar` 名前空間で新規作成する
  - 資格情報なし（`apiKey` と `credentialsFile` 両方空）でコンストラクタが `ProviderException` を throw することを検証する（契約テスト）
  - `new Provider('dummy_key', '')` が `HolidayProvider` インターフェースを実装していることを `assertInstanceOf(HolidayProvider::class, ...)` で検証する
  - `holidaysBetween` に `$from > $to` を渡すと空配列が返ることを検証する（ダミー API キーでインスタンス生成、API 呼び出しは発生しない）
  - Observable: `vendor/bin/phpunit`（integration グループ除く）でこれら 3 件のテストが PASS し、0 failures・0 errors が出力される
  - _Requirements: 1.1, 3.6, 5.1, 5.2_
  - _Depends: 4.1_

- [x] 5.2 インテグレーションテストの実装
  - `@group integration` アノテーション付きで実 API 呼び出しテストを `ProviderTest.php` に追加する
  - `getenv('GOOGLE_API_KEY')` / `getenv('GOOGLE_CREDENTIALS_FILE')` で資格情報を取得し `Provider` コンストラクタに渡す（どちらも空のとき `markTestSkipped` でスキップ）
  - API キー認証で `isHoliday` が既知祝日で `true`・非祝日で `false` を返すことを検証する
  - `holidayName` が祝日で非空文字列・非祝日で空文字を返すことを検証する
  - `holidaysBetween` が指定期間内の祝日を `Holiday[]` 昇順で返すことを検証する
  - `Holiday::getDate()` が `DateTimeImmutable` であることを検証する
  - Observable: `GOOGLE_API_KEY` 環境変数を設定した状態で `vendor/bin/phpunit --group integration` が全件 PASS する
  - _Requirements: 2.3, 3.1, 3.2, 3.3, 3.4, 3.5, 5.2, 5.5_
  - _Depends: 5.1, 4.1_

- [ ] 6. PHP 7.4・8.1 両環境での検証
- [ ] 6.1 Docker 両バージョンでのテスト通過確認
  - `docker compose -f docker/compose.yaml run --rm php74 vendor/bin/phpunit` を実行し全テスト（integration 除く）PASS を確認する
  - `docker compose -f docker/compose.yaml run --rm php81 vendor/bin/phpunit` を実行し全テスト（integration 除く）PASS を確認する
  - PHP 8.1 での `google/apiclient` v2.16.x が出す Deprecated Notice は既知制約として許容する。fatal error・型エラーがないことを確認する
  - Observable: `php74`・`php81` 両環境で全テスト PASS し、fatal error・型エラーがゼロである
  - _Requirements: 5.3, 5.4_
  - _Depends: 5.1_

## Implementation Notes

- `composer.json` の `google/apiclient` は `~2.16.0` に固定すること。`^2.16` に変更すると v2.17+ が解決され PHP 7.4 互換が壊れる（research.md 制約 1 参照）。
- PHP 8.1 環境で `google/apiclient` v2.16.x が出す Deprecated Notice（暗黙的 nullable 等）は既知制約として許容する。fatal error・型エラーがなければ合格とする（decisions.md D-4 参照）。
