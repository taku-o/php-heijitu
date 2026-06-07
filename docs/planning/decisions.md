# 要決定事項: php-heijitu の開発前に決めるべきこと

ユーザーの問い「**どんなことを決める必要があるか**」への回答。go-heijitu を PHP に移植するにあたり、実装開始前に決めておくべき論点を整理する。各論点に選択肢と推奨案を併記する。

> **確定状況（ユーザー確定済み）**: 主要論点はユーザーにより確定済み。各論点の「✅ 確定」を参照。
> - A-3 PHP バージョン: **PHP 7.4 を基準に開発し、8.1 でもエラーにならない状態**（7.4基準・8.1互換）
> - B-1 デフォルトプロバイダー: **`holiday-jp/holiday_jp`**
> - C-3 除外日付の指定 API: **コンストラクタ引数＋設定ローダー**
> - D-1 CaoCsv の HTTP 取得手段: **PHP 標準関数**（guzzle 等の追加依存は使わない。動作環境にバージョン縛りがあるため）
> - E-3 ドキュメント言語: **en/ja 両方**
> - G 配布方法: **GitHub の VCS 配布**（Packagist 登録なし。利用者は `repositories` 指定＋`dev-branch` ブランチで取り込む）。リリース工程（タグ付け等）は追加しない
> - H 依存方針: **コア `require` は最小・プロバイダー依存は `suggest`**（`require-dev` には全プロバイダー依存）。`ext-mbstring` も `suggest`。依存未導入時は `class_exists()`/`extension_loaded()` で検出し親切な例外を投げる（いずれも可能なら実施）
> - C-5 例外クラス粒度: **マーカー IF `HeijituException` ＋ `ConfigException` / `ProviderException`（`\RuntimeException` 基底）＋ 不正引数は標準 `\InvalidArgumentException`**
> - E-2 内部生成日付のタイムゾーン: **実行環境のデフォルト TZ を使用（`time.Local` 相当）。未設定時は `Asia/Tokyo`**

---

## A. プロジェクト基盤

### A-1. Composer パッケージ名
- 選択肢: `taku-o/php-heijitu` ／ その他
- 推奨: `taku-o/php-heijitu`（go-heijitu の命名を踏襲）

### A-2. ルート名前空間（PSR-4）
- 選択肢: `Heijitu\` ／ `TakuO\Heijitu\` ／ その他
- 推奨: `Heijitu\`（短く、go-heijitu のパッケージ名 `heijitu` に対応）

### A-3. 対応 PHP バージョン
- ユーザー希望: 「php7.4 / php8.1 あたりで動くと嬉しい」
- 論点: 7.4・8.1 **両対応**を必須とするか、8.1 のみで良いか。
  - 両対応にすると、依存ライブラリのバージョンが古い系列に固定される（yasumi 2.6.0、symfony/yaml 5.4、phpunit 9.6、google/apiclient 2.16）。union型・enum・コンストラクタプロモーション等の新構文も使えない。
  - 8.1 のみなら新しい依存・構文が使える。
- 推奨: **7.4・8.1 両対応**（希望に沿う）。`composer.json` は `"php": "^7.4 || ^8.0"`（`^8.0` は 8.1 を含む）。
- ✅ **確定**: **PHP 7.4 を基準に開発し、8.1 でもエラーにならない状態**にする。コードは 7.4 構文で書き、8.1 では deprecation 警告も含めてエラーが出ないことを確認する（特に「暗黙的に null 許容な型宣言」の非推奨化など 8.x で警告対象になる書き方を避ける）。`composer.json` は `"php": "^7.4 || ^8.0"`（`^8.0` は 8.1 を含む）。

### A-4. ライセンス
- 推奨: MIT（go-heijitu と同じ）

---

## B. デフォルト祝日プロバイダーの選定（最重要）

### B-1. holidayjp プロバイダーの実装ライブラリ
go-heijitu の「埋め込みデータ・外部接続不要」のデフォルトを PHP で何で実現するか。

| 案 | ライブラリ | 長所 | 短所 |
|----|-----------|------|------|
| 案1（推奨） | `holiday-jp/holiday_jp` | go-heijitu と同設計（埋め込み）。7.4/8.1両対応。振替休日込み | 最終更新2020年。将来年のデータが古くなる |
| 案2 | `azuyalabs/yasumi` v2.6.0 | 計算駆動で将来年も算出。多機能 | 7.4両対応だと 2.6.0 固定。API が go 版と形が異なる |

- 推奨: **案1（`holiday-jp/holiday_jp`）**。go-heijitu の設計思想に最も忠実。
- ✅ **確定**: **案1（`holiday-jp/holiday_jp`）** を採用。
- 関連論点: 祝日データの陳腐化（→ E-1）。

---

## C. Go 固有概念の PHP 表現

### C-1. `context.Context` の扱い
- 選択肢: 廃止 ／ 形だけ残す（PHP には実体がない）
- 推奨: **廃止**。全 API から第一引数の ctx を削除。HTTP のタイムアウト等はプロバイダーのクライアント設定で対応。

### C-2. エラー伝播の方式（`(T, error)` の置換）
- 選択肢: 例外 throw ／ 戻り値での疑似 error 返却
- 推奨: **例外 throw**（PHP の標準的な慣習）。go-heijitu の「握りつぶさず伝播」方針を例外で継承。

### C-3. 除外日付の指定 API（`WithExcludedDates` / `WithConfig` の置換）
- 選択肢:
  - 案A（推奨）: コンストラクタ引数（`MonthDay[]`）＋ 設定ローダー（`Config::loadExcludedDates($path): MonthDay[]`）。複数ソースは `array_merge`。
  - 案B: 静的ファクトリ＋ wither（`BusinessCalendar::create($p)->withExcludedDates(...)->withConfig(...)`）。go 版の関数オプションに近い見た目。
- 推奨: **案A**（PHP として素直）。案B が良ければ切り替え可。
- ✅ **確定**: **案A（コンストラクタ引数＋設定ローダー）** を採用。

### C-4. nil/null プロバイダーの扱い（go の panic 相当）
- go-heijitu は nil プロバイダーで panic。
- 選択肢: 型宣言（`HolidayProvider $provider`）のみで null を排除し例外を別途投げない ／ 明示的に `InvalidArgumentException`
- 推奨: **型宣言で排除**（PHP では null 不可型宣言で十分。go の panic 相当の防御は型で達成）。

### C-5. 例外クラスの粒度
- 選択肢: 単一の `HeijituException` ／ 用途別（設定読み込み・プロバイダー取得・認証不備など）に分割
- 推奨: 共通基底（インターフェース or 抽象基底）＋ 必要最小限の派生。go-heijitu に無い細分化はしない（YAGNI）。
- ✅ **確定**（判断を一任された）: 以下の最小構成とする。
  - `Heijitu\Exception\HeijituException`: マーカーインターフェース（本ライブラリが投げる例外の共通型。利用者は `catch (HeijituException $e)` で一括捕捉できる）
  - `Heijitu\Exception\ConfigException`（`\RuntimeException` を継承し `HeijituException` を実装）: 設定ファイルの読み込み・パース失敗・未対応拡張子
  - `Heijitu\Exception\ProviderException`（`\RuntimeException` を継承し `HeijituException` を実装）: プロバイダーのデータ取得・API 呼び出し失敗・認証情報不備
  - 型宣言で防げない不正引数（プログラマエラー）は標準 `\InvalidArgumentException` を使用

---

## D. プロバイダー実装の詳細

### D-1. CaoCsv の HTTP 取得手段
- 選択肢: `guzzlehttp/guzzle`（依存追加）／ PHP 標準 `file_get_contents`・cURL（依存ゼロ）
- 推奨: go-heijitu は外部パーサー（mikan）に委譲していた。PHP では専用パッケージが無いため、依存を増やさない標準関数 or 取り回しの良い guzzle のどちらかを選ぶ。
- ✅ **確定**: **PHP 標準関数**（`file_get_contents` または cURL）を使用。guzzle は動作環境にバージョン縛り問題があるため採用しない。これにより caoCsv プロバイダーは追加の Composer 依存ゼロ（取得・SJISデコード・パースをすべて PHP 標準機能で完結）。

### D-2. CaoCsv のテスト用フィクスチャ
- go-heijitu は `testdata/` に Shift_JIS の最小CSVを置いた。PHP 版も同様に `tests/.../testdata/` へ Shift_JIS CSV を用意するか。
- 推奨: 同様に用意（オンライン取得に依存しないテストのため）。

### D-3. オンライン取得型プロバイダー（CaoCsv オンライン・GoogleCalendar）のテスト方針
- go-heijitu は Google Calendar をビルドタグ `integration` で分離し、通常テストでは実 API を叩かない。
- PHP では `integration` テストグループ（PHPUnit の `@group integration`）で分離するか。
- 推奨: **`@group integration` で分離**し、通常の `phpunit` では実ネットワークを叩かない。

### D-4. GoogleCalendar の `google/apiclient` バージョン
- 推奨: `^2.16`（7.4/8.1両対応）。`google/apiclient-services` も最低PHPがズレないようバージョン確認・固定。
- ✅ **確定**: PHP 8.1 上での実機検証は **可能なら Step 4 で実施**（`composer install` 可否・クラスのロード・deprecation 警告の有無）。実装上の支障で無理ならスキップ可。

---

## E. 仕様・運用方針

### E-1. 祝日データ陳腐化への対応
- `holiday-jp/holiday_jp`（PHP版）は2020年最終更新。将来年のデータ不足が実害になるか。
- 選択肢: 何もしない（go-heijitu と同じ弱点を受容）／ caoCsv・googleCalendar を本番推奨として案内 ／ デフォルトを yasumi（計算駆動）にする
- 推奨: **go-heijitu と同方針**（デフォルトは埋め込み、最新性が必要なら caoCsv/googleCalendar を案内）。ドキュメントで明記。
- ✅ **確定（既知の弱点として受け入れ済み）**: `holiday-jp/holiday_jp` は **動作確認・開発用途には使えるが、運用では別のプロバイダーを使うべき**。祝日データは 2020 年で更新停止しており、2021 年以降の祝日（山の日の日付変更など）はカバーされない。この弱点は go-heijitu と同じ設計上の判断として受け入れる。最終ドキュメント（Step 5）でこの制限を明記し、本番用途には `CaoCsv`（内閣府CSV）または `GoogleCalendar` プロバイダーを案内すること。

### E-2. タイムゾーンの扱い
- 選択肢: 利用者任せ（go-heijitu と同じ）／ ライブラリ内で JST に固定
- 推奨: **利用者任せ＋ドキュメントで JST 明示推奨**（go-heijitu 踏襲）。
- ✅ **確定**:
  - 利用者が渡す日付（`isBusinessDay($t)` 等の引数）は、その `DateTimeImmutable` が持つタイムゾーンを尊重する。
  - ライブラリが内部生成する日付（`firstBusinessDayOfMonth` / `firstBusinessDaysOfYear` の年月からの生成、`nextBusinessDay` の探索起点）は、**実行環境のデフォルトタイムゾーン**（`date_default_timezone_get()`）に従う（go 版の `time.Local` 相当）。
  - **実行環境のタイムゾーンが未設定の場合（`ini_get('date.timezone')` が空）は `Asia/Tokyo` を使用**する（ユーザー要望: 未指定時は可能なら日本時間）。
  - ドキュメントで JST 運用（実行環境のタイムゾーンを `Asia/Tokyo` にする / JST の日付を渡す）を案内する。

### E-3. ドキュメントの言語と構成
- go-heijitu は en/ja 両対応（README.md / README-en.md、docs/ja、docs/en、API仕様・使い方・プロバイダーガイド）。
- 選択肢: go-heijitu と同等（en/ja 両方）／ ja のみ
- 推奨: 最低限 ja。go-heijitu と揃えるなら en/ja 両方。
- ✅ **確定**: **en/ja 両方**（go-heijitu と同等の構成）。

---

## G. 配布方法

### G-1. 配布チャネル
- 選択肢: Packagist 公開（`composer require taku-o/php-heijitu` を直接成立）／ GitHub の VCS 配布（利用者が `repositories` 指定）／ その他
- ✅ **確定**: **GitHub の VCS 配布**。Packagist 登録は行わない。
- 利用者は自身の `composer.json` に GitHub リポジトリを VCS リポジトリとして登録し、**`dev-branch` ブランチを指定**して取り込む。Composer のブランチ参照記法では `dev-branch` ブランチは `dev-dev-branch` と表記する。

```jsonc
// 利用者側 composer.json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/taku-o/php-heijitu" }
  ],
  "require": {
    "taku-o/php-heijitu": "dev-dev-branch",
    "holiday-jp/holiday_jp": "..."   // 使うプロバイダーの依存は利用者が追加（H 参照）
  }
}
```

### G-2. リリース工程
- ✅ **確定**: タグ付け・Packagist 登録などの**リリース工程は追加しない**（workplan に Step 6 を新設しない）。GitHub のブランチ配布で利用可能になるため。

---

## H. 依存方針（require / suggest）

go-heijitu の「使うプロバイダーの依存だけが取り込まれる」構造を、PHP では **PSR-4 オートロード（`new` したクラスだけロード）＋ `suggest`** で再現する。

- ✅ **確定**: **コア `require` は最小、プロバイダー依存は `suggest`**。利用者は使うプロバイダーの依存だけを自分で `require` する。

| パッケージ / 拡張 | composer.json 上の区分 | 理由 |
|---|---|---|
| `php`（`^7.4 \|\| ^8.0 \|\| ^8.1`） | `require` | コア必須 |
| `holiday-jp/holiday_jp` | `suggest` | HolidayJp プロバイダー使用時のみ |
| `google/apiclient`（`^2.16`） | `suggest` | GoogleCalendar プロバイダー使用時のみ |
| `symfony/yaml`（`^5.4`） | `suggest` | YAML 設定ファイル使用時のみ（JSON のみなら不要） |
| `ext-mbstring`（拡張） | `suggest` | caoCsv の Shift_JIS デコード（`mb_convert_encoding`）に使用 |
| caoCsv の Composer 依存 | — | なし（PHP 標準関数のみ。D-1） |
| `phpunit/phpunit` ＋ 上記プロバイダー依存一式 | `require-dev` | php-heijitu 自身の開発・テスト用（利用者には入らない） |

- ✅ **確定**（可能なら実施）: `ext-mbstring` は `suggest`（`ext-mbstring`）とする。
- ✅ **確定**（可能なら実施）: 依存ライブラリ／拡張が未導入のままプロバイダーを使った場合に、`class_exists()` / `extension_loaded()` で検出し、**「`composer require ...` が必要」という親切なメッセージの例外**を投げる。例外型は依存の種類に応じて C-5 の `ProviderException`（プロバイダー依存）/ `ConfigException`（YAML 依存）を用い、新たな例外クラスは追加しない。
  - 「可能なら」= 実装上の支障がなければ実施する。素の挙動（`Error: Class "..." not found`）でも機能上は成立するため、必須ではない。

---

## F. 決定事項サマリ（早見表）

| # | 論点 | 決定（✅=ユーザー確定 / ○=推奨で確定扱い） |
|---|------|--------|
| A-1 | パッケージ名 | ○ `taku-o/php-heijitu` |
| A-2 | 名前空間 | ○ `Heijitu\` |
| A-3 | PHP バージョン | ✅ **7.4 基準・8.1 互換**（7.4 構文で書き、8.1 で警告も出ない） |
| A-4 | ライセンス | ○ MIT |
| B-1 | デフォルトプロバイダー | ✅ `holiday-jp/holiday_jp` |
| C-1 | context | ○ 廃止 |
| C-2 | エラー伝播 | ○ 例外 throw |
| C-3 | 除外日付指定 | ✅ コンストラクタ引数＋設定ローダー |
| C-4 | null プロバイダー | ○ 型宣言で排除 |
| C-5 | 例外粒度 | ✅ マーカー IF `HeijituException` ＋ `ConfigException`/`ProviderException`（`\RuntimeException` 基底）＋ 不正引数は `\InvalidArgumentException` |
| D-1 | CaoCsv HTTP取得 | ✅ **PHP 標準関数**（guzzle 不採用・追加依存ゼロ） |
| D-3 | オンライン系テスト | ○ `@group integration` で分離 |
| D-4 | apiclient | ✅ `^2.16`・8.1 実機検証は可能なら Step 4 で実施（無理ならスキップ可） |
| E-1 | データ陳腐化 | ○ go-heijitu と同方針 |
| E-2 | 内部生成日付の TZ | ✅ 実行環境デフォルト TZ（`time.Local` 相当）。未設定なら `Asia/Tokyo` |
| E-3 | ドキュメント言語 | ✅ **en/ja 両方** |
| G-1 | 配布チャネル | ✅ **GitHub VCS 配布**（Packagist なし・`dev-branch` 取り込み） |
| G-2 | リリース工程 | ✅ 追加しない（Step 6 を新設しない） |
| H | 依存方針 | ✅ **コア `require` 最小・プロバイダー依存は `suggest`** |

---

_ユーザー確定済み: A-3 / B-1 / C-3 / C-5 / D-1 / D-4 / E-2 / E-3 / G-1 / G-2 / H。残る推奨確定扱いの項目（A-1/A-2/A-4/C-1/C-2/C-4/D-3/E-1）も方針として確定とみなし、`workplan.md` の手順に沿って実装に進める。「可能なら実施」の留意点: D-4（PHP 8.1 上の apiclient 実機検証）、H（`ext-mbstring` を `suggest`・依存未導入時の親切な例外）。いずれも実装上の支障がなければ実施する。_
