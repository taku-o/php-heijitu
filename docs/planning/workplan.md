# 作業計画: php-heijitu 実装

ユーザーの問い「**どんな手順で開発するか**」「**何段階に分けて、どう作業を区切るか**」への回答。

go-heijitu は5ステップで開発された（コア → holidayjp+全API → caoCsv → googleCalendar → ドキュメント）。php-heijitu も同じ積み上げ構造を踏襲する。各ステップは独立して動作確認できる単位とし、前ステップの成果物を次ステップが利用する。

---

## 0. 開発に入る前の手順（前提）

実装着手の前提（詳細は `decisions.md`）。主要論点は**ユーザー確定済み**。

1. **確定した方針**:
   - PHP: **7.4 を基準に開発し 8.1 でもエラーにならない状態**（7.4 構文、`"php": "^7.4 || ^8.0"`。`^8.0` は 8.1 を含む）
   - デフォルトプロバイダー: **`holiday-jp/holiday_jp`**
   - 除外日付指定: **コンストラクタ引数＋設定ローダー**
   - CaoCsv の HTTP 取得: **PHP 標準関数**（guzzle 不採用・追加依存ゼロ）
   - ドキュメント: **en/ja 両方**
2. **方針継承**: go-heijitu の決定事項（キャッシュなし・任意URL不可・エラー伝播・振替休日はプロバイダー任せ）を php-heijitu でも継承する。

> ここまでが「検討フェーズ」（本資料群の作成）。以降が「実装フェーズ」。

---

## ステップ一覧

| ステップ | 内容 | 成果物 |
|---------|------|--------|
| Step 1 | プロジェクト初期化 + コア実装 | composer.json・コア型・インターフェース・`isBusinessDay` まで・設定読み込み |
| Step 2 | holidayjp プロバイダー + 全API実装 | 全API動作・テスト（holidayjpベース） |
| Step 3 | caoCsv プロバイダー実装 | 内閣府CSV対応・テスト |
| Step 4 | googleCalendar プロバイダー実装 | Google Calendar API対応・テスト |
| Step 5 | examples + ドキュメント整備 | README・使い方・API仕様・プロバイダーガイド・サンプル・最終確認 |

go-heijitu との対応: 構成は同一。Go 固有の作業（`go.mod`）は Composer 初期化に、`go test` は PHPUnit に置き換わる。

---

## Step 1: プロジェクト初期化 + コア実装

### 目的
ライブラリの骨格を作り、`isBusinessDay` の判定ロジックまでを実装する。

### 作業内容

**開発環境（Docker・案A）の用意**
- `docker/Dockerfile`（`ARG PHP_VERSION` で 7.4/8.1 切替・`mbstring` 導入・Composer 同梱）
- `docker/compose.yaml`（`php74` / `php81` の2サービス・`vendor/` 共有）
- 詳細は `dev-environment.md` を参照。以降の `composer install`・PHPUnit 実行はすべて本環境上で行う

**プロジェクト初期化**
- `composer.json` 作成（`name: taku-o/php-heijitu`・`type: library`・`license: MIT`・PSR-4 オートロード）
  - `require` は `"php": "^7.4 || ^8.0"` のみ（コア依存最小・decisions.md H。`^8.0` は 8.1 を含む）
  - プロバイダー依存（`holiday-jp/holiday_jp`・`google/apiclient:^2.16`・`symfony/yaml:^5.4`）と `ext-mbstring` は `suggest`（decisions.md H）
  - `require-dev` に `phpunit/phpunit:^9.6` と全プロバイダー依存一式
- `.gitignore` の確認（`vendor/` 等）
- PHPUnit 設定（`phpunit.xml`）
- `composer install` は `php74` サービスで実行（7.4 基準で依存固定）
- 配布は GitHub VCS（`dev-branch` ブランチ）。Packagist 登録・リリースタグ工程は行わない（decisions.md G）

**コア型の実装**
- `MonthDay.php`: `MonthDay`（month, day）+ `matches(DateTimeImmutable): bool`
- `Holiday.php`: `Holiday`（date, name）

**インターフェース定義**
- `HolidayProvider.php`: `isHoliday` / `holidayName` / `holidaysBetween`

**BusinessCalendar の骨格**
- `BusinessCalendar.php`: コンストラクタ（provider + excludedDates）
- `BusinessCalendar.php`: `isBusinessDay()`（土日判定 + プロバイダー呼び出し + 除外日付チェック + extraExcluded）

**設定ファイル読み込み**
- `Config.php`: YAML/JSON を拡張子で自動判別して `MonthDay[]` を返す（`symfony/yaml` + 標準 `json_decode`）

**例外**
- `Heijitu\Exception\HeijituException`（マーカー IF）／ `ConfigException` ／ `ProviderException`（`\RuntimeException` 基底）。型で防げない不正引数は標準 `\InvalidArgumentException`（decisions.md C-5）

**テスト**
- `MonthDayTest`: `matches()` の確認
- `ConfigTest`: YAML・JSON 読み込みの確認
- `BusinessCalendarTest`: `isBusinessDay()`（モックプロバイダー使用）

### 完了条件（=このステップの動作確認基準）
- `composer install` が通る
- モックプロバイダーで `isBusinessDay()` のテストが全て通る
- 通常の判定（土日・除外日付・extraExcluded）が期待通り

---

## Step 2: holidayjp プロバイダー + 全API実装

### 目的
デフォルトプロバイダー（holiday_jp）を実装し、`BusinessCalendar` の全 API を完成させる。

### 作業内容

**holidayjp プロバイダー**
- `Providers/HolidayJp/Provider.php`: `holiday-jp/holiday_jp` を使った `HolidayProvider` 実装
- `composer require holiday-jp/holiday_jp`
- `holidaysBetween` は `between()` の結果を `Holiday[]` に変換し日付昇順ソート
- （可能なら）`class_exists` で `holiday-jp/holiday_jp` 未導入を検出し、案内する `ProviderException` を投げる（decisions.md H）

**BusinessCalendar 残り API**
- `nextBusinessDay()`: 翌日以降で最初の営業日
- `firstBusinessDayOfMonth()`: 指定年月の1日から営業日探索
- `firstBusinessDaysOfYear()`: 12ヶ月分を集約
- `holidays()`: プロバイダーの `holidaysBetween()` を返す

**テスト**
- `Providers/HolidayJp/ProviderTest`: isHoliday / holidayName / holidaysBetween
- `BusinessCalendarTest` 追記:
  - `nextBusinessDay()`: 金曜の翌営業日が月曜、祝日スキップ
  - `firstBusinessDayOfMonth()`: 1月1日が元日のとき翌営業日を返す
  - `firstBusinessDaysOfYear()`: 12件返る
  - `holidays()`: 期間内の祝日が正しい
  - 除外日付（コンストラクタ指定 / 設定ファイル）が機能する

### 完了条件
- `phpunit` が全て通る
- holidayjp プロバイダーで全 API が期待通りに動作する

---

## Step 3: caoCsv プロバイダー実装

### 目的
内閣府公式CSVを使った `HolidayProvider` 実装を追加する。

### 作業内容

**caoCsv プロバイダー**
- `Providers/CaoCsv/Provider.php`: `csvPath` を受け取るコンストラクタ
- ローカルモード（`csvPath` 指定時 → ファイル読み込み）
- オンラインモード（`csvPath` 空時 → 内閣府固定URLを取得）
- Shift_JIS デコード（`mb_convert_encoding(..., 'UTF-8', 'SJIS-win')`）+ `str_getcsv` でパースし内部保持
- 点照合（isHoliday/holidayName）は保持データ照合、`holidaysBetween` は範囲フィルタ＋昇順ソート
- HTTP 取得は **PHP 標準関数（`file_get_contents` または cURL）**（確定）。追加の Composer 依存は導入しない
- （可能なら）`extension_loaded('mbstring')` で未導入を検出し、`ext-mbstring` の導入を案内する `ProviderException` を投げる（decisions.md H）

**テスト**
- `Providers/CaoCsv/ProviderTest`:
  - ローカルCSV（`testdata/syukujitsu_test.csv`、Shift_JIS）での isHoliday / holidayName / holidaysBetween
  - `csvPath` 指定時にローカルが読まれる
  - オンライン取得は `@group integration` で分離（通常テストでは実行しない）

### 完了条件
- `phpunit`（integration 除く）が全て通る
- ローカルCSVモードで holidayjp と同等の結果が得られる

---

## Step 4: googleCalendar プロバイダー実装

### 目的
Google Calendar API を使った `HolidayProvider` 実装を追加する。

### 作業内容

**googleCalendar プロバイダー**
- `Providers/GoogleCalendar/Provider.php`: `apiKey` / `credentialsFile` を受け取るコンストラクタ
- APIキー認証・サービスアカウント認証の両対応（`credentialsFile` 優先、両方空なら例外）
- Calendar ID `ja.japanese.official#holiday@group.v.calendar.google.com` から祝日取得（全ページ）
- `composer require google/apiclient:^2.16`（`apiclient-services` のPHP要件も確認）

**テスト**
- `Providers/GoogleCalendar/ProviderTest`:
  - apiKey/credentialsFile 両方空でコンストラクタが例外を投げる（契約テスト）
  - 実 API 呼び出しは `@group integration` で分離

### 完了条件
- オートロード・型エラーなくクラスが読める
- integration を除く `phpunit` が全て通る
- PHP 8.1 上で `google/apiclient` が動作することの実機確認（**可能なら実施・無理ならスキップ可**。`composer install` 可否・クラスのロード・deprecation 警告の有無。decisions.md D-4）
- （可能なら）`class_exists` で `google/apiclient` 未導入を検出し、案内する `ProviderException` を投げる（decisions.md H）

---

## Step 5: examples + ドキュメント整備

### 目的
利用者向けサンプルとドキュメントを整備し、ライブラリとして公開できる状態にする。

### 作業内容

**examples**
- `examples/main.php`: 全パターンのサンプル
  - holidayjp で全 API
  - コンストラクタ指定 + 設定ファイル併用
  - caoCsv（ローカル/オンライン）
  - `isBusinessDay` に extraExcluded を渡す

**PHPDoc**
- 全公開クラス・メソッドに PHPDoc（型・引数・戻り値・例外）

**README・ルートドキュメント**（en/ja 両方・確定）
- `README.md`（英語） / `README-ja.md`（日本語）（go-heijitu の en/ja 構成に準拠）
- `CHANGELOG.md` / `CONTRIBUTING.md` / `LICENSE`

**API仕様・使い方・プロバイダーガイド**（en/ja 両方・確定）
- `docs/en/api-spec.md` / `docs/ja/api-spec.md`: 全公開型・全 API・設定ファイル仕様
- `docs/en/usage.md` / `docs/ja/usage.md`: インストール〜ユースケース別の使い方
- `docs/en/providers.md` / `docs/ja/providers.md`: 3プロバイダーの選択基準・設定・注意点（Google Calendar APIキー取得手順・コンストラクタへの渡し方・環境変数連携パターンを含む）

**最終確認**
- `phpunit` が全て通る
- 静的解析（`composer` で導入する場合は `phpstan` 等。導入可否は要決定）
- `php examples/main.php` が実行でき期待通りの出力
- ⚠️ PHP 7.4 と 8.1 の**両方**でテストが通ることを確認（CI またはローカルの両バージョン実行）

### 完了条件
- `phpunit` がエラーなし（7.4・8.1 両方）
- `examples/main.php` が実行でき期待通り
- README を読んで初めての利用者が使い始められる

---

## 依存ライブラリ導入タイミング

| ライブラリ | 導入ステップ |
|-----------|------------|
| `symfony/yaml:^5.4`（設定 YAML） | Step 1 |
| `phpunit/phpunit:^9.6`（テスト, require-dev） | Step 1 |
| `holiday-jp/holiday_jp`（holidayjp） | Step 2 |
| （caoCsv は追加依存なし。PHP 標準の `file_get_contents`/cURL + mbstring + str_getcsv） | Step 3 |
| `google/apiclient:^2.16`（googleCalendar） | Step 4 |

---

## 各ステップの依存関係

```
Step 1（コア）
  └── Step 2（holidayjp + 全API）
        ├── Step 3（caoCsv）
        ├── Step 4（googleCalendar）
        └── Step 5（examples + ドキュメント）
```

- Step 3 と Step 4 は Step 2 完了後なら並行可能。
- Step 5 は Step 3・4 の完了を待って実施。

---

## go-heijitu の開発実績との対応

go-heijitu は同じ5ステップを Kiro 流の spec 駆動（各ステップに requirements / design / tasks）で実施し、`.kiro/specs/step1-core` 〜 `step5-docs` として記録されている。php-heijitu も同様に、各ステップを spec 駆動で進めることができる（`/kiro-spec-*` ワークフロー）。本資料群（`docs/planning/`）は go-heijitu の `docs/planning/`（investigation / design / structure / workplan / api-spec）に対応する検討フェーズの成果物にあたる。

---

_要決定事項は `decisions.md`、設計詳細は `design.md`、go-heijitu 解析と PHP ライブラリ調査は `investigation.md` を参照。_
