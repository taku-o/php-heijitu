# Implementation Plan

- [x] 1. PHPDoc 追記
- [x] 1.1 (P) 値オブジェクト（Holiday・MonthDay）への PHPDoc 追記
  - `src/Holiday.php` にクラス説明・`__construct`・`getDate`・`getName` の PHPDoc を追記する
  - `src/MonthDay.php` にクラス説明・`__construct`・`getMonth`・`getDay`・`matches` の PHPDoc を追記する
  - `Holiday` クラスのクラス説明: 「祝日の日付と名称を保持する不変値オブジェクト」
  - `MonthDay` クラスのクラス説明: 「年をまたいで有効な月日を表す不変値オブジェクト。バリデーションなし」
  - 配列型は `Holiday[]`・`DateTimeImmutable[]` 等 PHPDoc array notation で表現する（`@return Holiday[]`）
  - IDE で `$holiday->getDate()` の戻り値型が `DateTimeImmutable` として補完されること
  - _Requirements: 2.1, 2.2, 2.3_
  - _Boundary: src/Holiday.php, src/MonthDay.php_

- [x] 1.2 (P) プロバイダークラスへの PHPDoc 追記
  - `src/Providers/HolidayJp/Provider.php` にクラス説明・`__construct` の `@throws` PHPDoc・`isHoliday`/`holidayName`/`holidaysBetween` への `/** {@inheritdoc} */` を追記する
  - `src/Providers/CaoCsv/Provider.php` の `isHoliday`・`holidayName` に `/** {@inheritdoc} */` を追記する
  - `src/Providers/GoogleCalendar/Provider.php` の `isHoliday`・`holidayName` に `/** {@inheritdoc} */` を追記する
  - HolidayJp クラス説明: データが 2020 年で更新停止している旨・本番用途では CaoCsv/GoogleCalendar 推奨の注意を含む
  - IDE で各プロバイダーのメソッドにホバーすると型情報と説明が表示されること
  - _Requirements: 2.1, 2.2, 2.4_
  - _Boundary: src/Providers/_

- [x] 1.3 (P) 例外クラスへの PHPDoc 追記
  - `src/Exception/HeijituException.php` に「php-heijitu が投げる例外の共通マーカーインターフェース。`catch (HeijituException $e)` で全例外を一括捕捉できる」説明を追記する
  - `src/Exception/ConfigException.php` に「設定ファイルの読み込み・パース失敗時の例外」説明を追記する
  - `src/Exception/ProviderException.php` に「プロバイダーのデータ取得・API 呼び出し失敗時の例外」説明を追記する
  - 3ファイルすべてにクラス説明が追記されていること
  - _Requirements: 2.1, 2.5_
  - _Boundary: src/Exception/_

- [x] 2. examples/main.php の作成
- [x] 2.1 全プロバイダー・全APIパターンのサンプルスクリプトを作成する
  - `examples/` ディレクトリを作成し `examples/main.php` を新規作成する
  - Section 1（HolidayJp 全 API）: `isBusinessDay`・`nextBusinessDay`・`firstBusinessDayOfMonth`・`firstBusinessDaysOfYear`・`holidays` の呼び出しと echo 出力を含む
  - Section 2（コンストラクタ除外日付）: `new BusinessCalendar($provider, [new MonthDay(8, 15), new MonthDay(12, 31)])` パターンを含む
  - Section 3（設定ファイル + array_merge）: `Config::loadExcludedDates()` でファイルから読み込み、コンストラクタ引数と `array_merge` するパターンを含む
  - Section 4（CaoCsv ローカル）: `new CaoCsv\Provider('/path/to/local.csv')` パターンを含む
  - Section 5（CaoCsv オンライン）: `new CaoCsv\Provider()` パターンを含む。ネットワーク失敗時は `ProviderException` をそのまま伝播させる（`try/catch` しない）
  - Section 6（extraExcluded）: `$cal->isBusinessDay($t, new MonthDay(8, 12))` の可変長引数パターンを含む
  - `php examples/main.php` を実行すると各セクションの結果が stdout に出力され、exit code 0 で完走すること（ネットワーク接続が必要）
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6_

- [x] 3. README・ルートドキュメントの作成
- [x] 3.1 README.md（英語）の作成
  - `README.md` を新規作成する（既存の 1 行スケルトンを全面置換）
  - インストール手順: GitHub VCS 配布の `repositories` 設定・`composer require` コマンド（プロバイダー依存も案内）を含む
  - クイックスタート: HolidayJp を使った最小サンプルコードを含む
  - 除外日付の指定方法: コンストラクタ引数・設定ファイルの両方を含む
  - `holiday-jp/holiday_jp` のデータが 2020 年で更新停止しており 2021 年以降の祝日変更に未対応であること・`CaoCsv`/`GoogleCalendar` プロバイダーの使用を推奨する旨を記載する
  - タイムゾーン: `date_default_timezone_set('Asia/Tokyo')` を使った JST 運用案内を含む
  - README.md を読んだ初めての利用者がインストールから基本的な使い方を把握できる内容であること
  - _Requirements: 3.1, 3.2, 3.3, 3.4_

- [x] 3.2 (P) README-ja.md（日本語）の作成
  - `README-ja.md` を新規作成する
  - README.md と同等の内容（インストール・クイックスタート・除外日付・データ陳腐化注意・タイムゾーン案内）を日本語で記述する
  - `README-ja.md` がプロジェクトルートに存在すること
  - _Requirements: 3.1, 3.2, 3.3, 3.4_
  - _Boundary: README-ja.md_

- [x] 3.3 (P) CHANGELOG.md・CONTRIBUTING.md・LICENSE の作成
  - `CHANGELOG.md` を Keep a Changelog 形式で作成する（`[Unreleased]` セクションのみ。`Added`・`Changed`・`Fixed` サブセクションを用意）
  - `CONTRIBUTING.md` を作成する（PR の送り方・PHP 7.4 基準コーディング規約・PHPUnit 実行方法・Docker 環境の使い方を含む）
  - `LICENSE` に MIT ライセンス全文を作成する（copyright holder: taku-o）
  - 3ファイルがプロジェクトルートに存在すること
  - _Requirements: 3.5, 3.6, 3.7_
  - _Boundary: CHANGELOG.md, CONTRIBUTING.md, LICENSE_

- [x] 4. docs/ ドキュメントの作成
- [x] 4.1 docs/en・docs/ja の api-spec.md を作成する
  - `docs/en/` および `docs/ja/` ディレクトリを作成する
  - `docs/en/api-spec.md` を作成する: `BusinessCalendar`（コンストラクタ＋全5メソッドのシグネチャ・例外）・`HolidayProvider` インターフェース（3メソッド）・`MonthDay`・`Holiday`・`Config`・例外クラス階層・設定ファイル仕様（YAML/JSON フォーマット表）を記載する
  - `docs/ja/api-spec.md` を同等の内容で日本語にて作成する
  - 2ファイルが作成されており全公開クラス・全メソッドのシグネチャと設定ファイル仕様が記載されていること
  - _Requirements: 4.1, 4.2_

- [x] 4.2 (P) docs/en・docs/ja の usage.md を作成する
  - `docs/en/usage.md` を作成する: インストール手順・基本的な使い方・除外日付指定（コンストラクタ引数・設定ファイル・array_merge）・全 API 別コード例（`isBusinessDay`・`nextBusinessDay`・`firstBusinessDayOfMonth`・`firstBusinessDaysOfYear`・`holidays`）・`extraExcluded`・タイムゾーン設定方法を含む
  - `docs/ja/usage.md` を同等の内容で日本語にて作成する
  - 2ファイルが作成されており各 API のコード例が含まれていること
  - _Requirements: 4.3_
  - _Boundary: docs/en/usage.md, docs/ja/usage.md_

- [x] 4.3 (P) docs/en・docs/ja の providers.md を作成する
  - `docs/en/providers.md` を作成する: 3プロバイダー（HolidayJp・CaoCsv・GoogleCalendar）の比較表（特徴・依存・推奨ユースケース）・各プロバイダーのセットアップ方法・Google Calendar API キー取得手順（GCP コンソール）・コンストラクタへの渡し方・`getenv('GOOGLE_API_KEY')` 環境変数連携パターン・holiday-jp データ陳腐化注意（2021 年以降の祝日変更に未対応）を含む
  - `docs/ja/providers.md` を同等の内容で日本語にて作成する
  - 2ファイルが作成されており Google Calendar API キー取得手順が含まれていること
  - _Requirements: 4.4, 4.5, 4.6_
  - _Boundary: docs/en/providers.md, docs/ja/providers.md_

- [ ] 5. PHP 7.4・8.1 最終確認
- [ ] 5.1 PHPUnit テストを PHP 7.4・8.1 の両環境で実行する
  - `docker compose -f docker/compose.yaml run --rm php74 vendor/bin/phpunit` を実行する
  - `docker compose -f docker/compose.yaml run --rm php81 vendor/bin/phpunit` を実行する
  - PHP 7.4 で FAILURES: 0, ERRORS: 0 を確認する
  - PHP 8.1 で FAILURES: 0, ERRORS: 0 かつ Deprecated 警告なしを確認する
  - PHP 7.4・8.1 両環境での PHPUnit 全テスト（integration グループ除く）がエラーなしで通過していること
  - _Requirements: 5.1, 5.2, 5.3_

- [ ] 5.2 examples/main.php を PHP 7.4・8.1 の両環境で実行する
  - `docker compose -f docker/compose.yaml run --rm php74 php examples/main.php` を実行する
  - `docker compose -f docker/compose.yaml run --rm php81 php examples/main.php` を実行する
  - 両環境で期待通りの出力と exit code 0 を確認する（実行にはネットワーク接続が必要）
  - PHP 7.4・8.1 両環境で examples/main.php が期待出力を返し正常終了すること
  - _Depends: 2.1_
  - _Requirements: 1.7, 5.4, 5.5_

- [ ] 6. 計画ドキュメントの移動
- [ ] 6.1 docs/planning/ を .kiro/specs/initial-planning/planning/ へ移動する
  - `docs/planning/` 配下の全ファイルを `.kiro/specs/initial-planning/planning/` へ移動する
  - 移動後、`docs/planning/` ディレクトリが存在しないこと
  - `.kiro/specs/initial-planning/planning/` 配下に全ファイルが存在することを確認する
