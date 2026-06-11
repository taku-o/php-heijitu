# 調査資料: go-heijitu の解析と PHP 移植のための技術調査

本資料は、Go 製の営業日計算ライブラリ `go-heijitu`（`~/Documents/workspaces/go-heijitu`）を解析し、その PHP 版（`php-heijitu`）を作成するための一次調査をまとめたものである。

---

## 1. go-heijitu とは何か

日本の営業日を計算する Go ライブラリ。祝日判定の実装を `HolidayProvider` インターフェースで差し替え可能にし、会社独自の休業日を設定ファイルまたはパラメータで指定できる。

### コア機能

- **営業日判定** (`IsBusinessDay`): 指定日が営業日か（土日・祝日・除外日付でない）を判定
- **営業日の探索**:
  - `NextBusinessDay`: 翌日以降で最初の営業日
  - `FirstBusinessDayOfMonth`: 指定年月の最初の営業日
  - `FirstBusinessDaysOfYear`: 指定年の各月の最初の営業日リスト（12件）
- **祝日一覧** (`Holidays`): 指定期間の祝日リスト取得
- **祝日データソースの切り替え**: `HolidayProvider` 実装の差し替え（埋め込みデータ / 内閣府CSV / Google Calendar）
- **会社独自休業日の指定**: パラメータ（`WithExcludedDates`）と設定ファイル（`WithConfig`、YAML/JSON）の併用

### アーキテクチャ（go-heijitu）

- ルートパッケージ `heijitu` がコア（公開 API・型・`HolidayProvider` インターフェース）を提供。
- `providers/<name>/` 配下の各パッケージが祝日データソースごとの `HolidayProvider` 実装を担う。
- コアは `HolidayProvider` インターフェースにのみ依存し、具体実装は利用者が呼び出し時に注入する。
- 外部ライブラリへの依存はプロバイダーと設定ファイル読み込みに閉じ込め、コアの計算ロジックは標準ライブラリのみで実装する。

---

## 2. go-heijitu のコード構成と責務

| ファイル | 責務 |
|---------|------|
| `calendar.go` | `BusinessCalendar` 本体・全公開 API。土日・祝日・除外日付の判定統合 |
| `provider.go` | `HolidayProvider` インターフェース定義（IsHoliday / HolidayName / HolidaysBetween） |
| `holiday.go` | `Holiday` 値オブジェクト（Date, Name） |
| `monthday.go` | `MonthDay` 値オブジェクト（Month, Day）+ `Matches(t)` |
| `option.go` | `Option` 関数オプション型 + `WithExcludedDates` / `WithConfig` |
| `config.go` | 設定ファイル（YAML/JSON）読み込み。拡張子で自動判別 |
| `providers/holidayjp/provider.go` | 埋め込み祝日データ実装（`holiday-jp/holiday_jp-go`） |
| `providers/caoCsv/provider.go` | 内閣府CSV実装（`mikan/syukujitsu-go`、ローカル/オンライン） |
| `providers/googleCalendar/provider.go` | Google Calendar API実装 |

### 公開 API シグネチャ（Go）

```go
// コア
type HolidayProvider interface {
    IsHoliday(ctx context.Context, t time.Time) (bool, error)
    HolidayName(ctx context.Context, t time.Time) (string, error)
    HolidaysBetween(ctx context.Context, from, to time.Time) ([]Holiday, error)
}

func New(provider HolidayProvider, opts ...Option) *BusinessCalendar
func (bc *BusinessCalendar) IsBusinessDay(ctx, t, extraExcluded ...MonthDay) (bool, error)
func (bc *BusinessCalendar) NextBusinessDay(ctx, from) (time.Time, error)
func (bc *BusinessCalendar) FirstBusinessDayOfMonth(ctx, year, month) (time.Time, error)
func (bc *BusinessCalendar) FirstBusinessDaysOfYear(ctx, year) ([]time.Time, error)
func (bc *BusinessCalendar) Holidays(ctx, from, to) ([]Holiday, error)

// オプション
func WithExcludedDates(dates []MonthDay) Option
func WithConfig(path string) (Option, error)
```

### 重要な実装上の挙動（移植時に厳守すべき仕様）

- **営業日の判定条件**（全て満たすとき営業日）: ①土日でない ②プロバイダーの `IsHoliday` が false ③固定の除外日付に含まれない ④呼び出し時の追加除外日付に含まれない。
- `New` に nil プロバイダーを渡すと **panic**（プログラマエラーのみ panic、実行時エラーは error 返却）。
- `NextBusinessDay` は `from` 当日を含まず、翌日から1日ずつ前進して最初の営業日を返す。
- `FirstBusinessDayOfMonth` は当月1日から当月内を1日ずつ前進。当月内に営業日が無ければゼロ値を返す（実装上は到達しない想定）。
- `FirstBusinessDaysOfYear` は index 0 が1月、index 11 が12月の必ず12要素。
- `Holidays` は除外日付でフィルタせず、プロバイダーの `HolidaysBetween` をそのまま返す。
- `HolidaysBetween` は `from`・`to` 両端を含み、**日付昇順**で返す。
- `HolidayName` は非祝日のとき `("", nil)`（空文字、エラーにしない）。
- `MonthDay.Matches` は年を無視し月・日のみ照合。バリデーションなし（存在しない 2/30 等はどの日付とも一致しない）。
- 時刻の Location（タイムゾーン）は呼び出し元が統一する前提。`FirstBusinessDayOfMonth` は `time.Local` で日付を生成している。

### 各プロバイダーの実装の要点

- **holidayjp**: `holiday-jp/holiday_jp-go` に委譲。`HolidaysBetween` は `holiday.Between` の戻り（map）を `Holiday` に変換し昇順ソート。エラーは常に nil。
- **caoCsv**: `Options.CSVPath` 非空ならローカル（`LoadAndParse`）、空ならオンライン（`FetchAndParse`、内閣府固定URL）。点照合は mikan の `Find` に委譲、`HolidaysBetween` のみ保持エントリを自前で範囲フィルタ＋昇順ソート。キャッシュなし（オンライン時は `New` 毎に fetch）。
- **googleCalendar**: Calendar ID `ja.japanese.official#holiday@group.v.calendar.google.com`。`CredentialsFile` 非空ならサービスアカウント、空かつ `APIKey` 非空なら APIキー、両方空ならエラー。`Events.List` を全ページ取得し終日イベントを変換。

### go-heijitu の外部依存

| ライブラリ | 用途 | 導入ステップ |
|-----------|------|------------|
| `gopkg.in/yaml.v3` | YAML設定ファイル読み込み（コア） | Step 1 |
| `github.com/holiday-jp/holiday_jp-go` | holidayjp プロバイダー | Step 2 |
| `github.com/mikan/syukujitsu-go`（+ `golang.org/x/text` 推移） | caoCsv プロバイダー | Step 3 |
| `google.golang.org/api`・`golang.org/x/oauth2` | googleCalendar プロバイダー | Step 4 |

### go-heijitu の決定事項（移植時の前提として継承）

| 項目 | 決定内容 |
|------|---------|
| キャッシュ | オンライン取得型はキャッシュなし。取得のたびに fetch |
| caoCsv データソース | ローカルパス指定 or 内閣府公式データのオンライン取得のみ。任意URLは受け付けない |
| エラーハンドリング | プロバイダーのエラーは握りつぶさず呼び出し元へ伝播 |
| 振替休日 | ライブラリ側は関与せず、各プロバイダーの祝日データに委ねる |
| 設定ファイル | YAML優先、JSONも対応。拡張子で自動判別 |
| ライセンス | MIT |

---

## 3. PHP エコシステム調査（go-heijitu の各依存に対応する PHP 版の選択肢）

> 2026年6月時点で Packagist / GitHub にて実在を確認したもの。**PHP 7.4 と 8.1 の両対応**を前提にバージョンを精査した。

### 3-1. 日本の祝日・埋め込みデータ型ライブラリ

#### holiday-jp/holiday_jp（PHP版・go-heijitu の holiday_jp-go に最も近い）
- **Composer**: `holiday-jp/holiday_jp`（GitHub: `holiday-jp/holiday_jp-php`、最新 v2.3.0 / 2020-12）
- **PHP要件**: `>= 5.3.3` → **7.4・8.1 の両方で動作**、本番依存ゼロ。
- **データ埋め込み**: あり（1970年〜の静的配列を埋め込み、**外部接続不要**）。go-heijitu の holidayjp と同じ設計思想。
- **振替休日**: あり（生成データに「振替休日」エントリを含む）。
- **API**: `HolidayJp::isHoliday(DateTime $date): bool` / `HolidayJp::between(DateTime $start, DateTime $last): array`（要素に date/name/name_en/week 等）。すべて static、名前空間 `HolidayJp\`、PSR-4。
- **弱点**: 最終リリース2020年のため、年が進むとデータが古くなる（go-heijitu の holidayjp と同じ弱点）。
- **注意**: 個別の「祝日名取得」専用メソッドは無く、`between()` の結果から名前を引く形。

#### azuyalabs/yasumi（計算駆動・多国対応／代替候補）
- **Composer**: `azuyalabs/yasumi`
- **PHP要件**: 最新 2.11.0 は `>=8.2`。**PHP 7.4対応の最終版は v2.6.0（`php >=7.4`）**。v2.9.0 で 8.1、v2.11.0 で 8.2 に引き上げ。
- **7.4・8.1 両対応にするなら v2.6.0 に固定が必要**（8.1のみで良ければ v2.9/2.10）。
- **日本対応**: あり（`Yasumi\Provider\Japan`、振替休日対応）。`isHoliday` / `getHolidayName` / `between` あり。計算駆動なので将来年も算出可能。

#### その他（参考・7.4 非対応が多い）
- `nojimage/holiday-jp`（国立天文台データ）: PHP 8.2以上要求 → 7.4不可。
- `sakai/jp-holiday`: PHP 7.1+。`spatie/holidays`: 多国対応の新興（詳細未精査）。

### 3-2. 内閣府CSV（syukujitsu.csv, Shift_JIS）

- **専用 Composer パッケージ**: go-heijitu の `syukujitsu-go` に直接相当するものは**確認できず**。PHP では自前パースが一般的。
- **標準的手段（実在確認）**:
  - HTTP取得: **PHP標準 `file_get_contents` / cURL を使用**（ユーザー確定。guzzle は動作環境のバージョン縛り問題のため不採用）。
  - Shift_JIS デコード: PHP標準 **mbstring** `mb_convert_encoding($csv, 'UTF-8', 'SJIS-win')`（表記揺れ対策に `SJIS-win` 推奨）。
  - CSVパース: PHP標準 `str_getcsv()` / `fgetcsv()`。フォーマットは「YYYY/MM/DD,祝日名」の2列。
- **結論**: **追加の Composer 依存はゼロ**。取得・デコード・パースをすべて PHP標準機能で完結できる。

### 3-3. Google Calendar API（公式 PHP クライアント）

- **Composer**: `google/apiclient`（GitHub: `googleapis/google-api-php-client`）
- **PHP要件**: **7.4対応の最終版は v2.16.1（`php ^7.4 | ^8.0`）**。`^8.0` 制約は 8.1 も含む（Composer のキャレットは `>=8.0.0 <9.0.0`）ため、**`^2.16` は 7.4・8.1 双方で動作**。v2.19.0 以降は `php ^8.1`（7.4不可）。
- **Calendar API**: あり（`Google\Service\Calendar`）。APIキー認証（`setDeveloperKey`）／サービスアカウント認証（`setAuthConfig`）の両対応。
- **注意**: `google/apiclient-services` のバージョンで最低 PHP が変わるため、`apiclient-services` も明示的にバージョン固定するのが推奨（公式 Issue で言及）。

### 3-4. YAML / JSON 設定

- **YAML**: `symfony/yaml`。**7.4・8.1両対応は v5.4系（`php >=7.2.5`）**。6.x 以降は 8.1 必須で 7.4 不可。→ `^5.4` を選択。
- **JSON**: PHP標準 `json_decode` / `json_encode` で十分。外部依存不要。

### 3-5. PHP 7.4 / 8.1 両対応の言語制約（重要）

両バージョンで動かすため、**8.0/8.1 で追加された構文は使えない**。

| 機能 | 導入バージョン | 7.4両対応で使えるか |
|------|--------------|-------------------|
| 引数・戻り値の型宣言、nullable型 `?T`、プロパティ型宣言 | 7.4 | ✓ 使える |
| アロー関数 `fn()`、`??=`、可変長引数 `...$args`、interface | 7.4 | ✓ 使える |
| union 型 `int\|string` | 8.0 | ✗ 使えない（PHPDocで代替） |
| 名前付き引数 | 8.0 | ✗ 使えない |
| コンストラクタプロモーション、`match` | 8.0 | ✗ 使えない |
| enum、`readonly` プロパティ | 8.1 | ✗ 使えない（クラス定数/イミュータブル設計で代替） |

- **日付**: `DateTimeImmutable` 推奨（不変）。タイムゾーンは `new DateTimeZone('Asia/Tokyo')` を明示するのが安全。
- **テスト**: **PHPUnit 9.6系が 7.4・8.1 両対応の唯一の系列**（10以降は 8.1 必須で 7.4 不可）。→ `phpunit/phpunit:^9.6`。
- **オートロード**: PSR-4。`composer.json` の `require` に `"php": "^7.4 || ^8.0"` のように両対応制約を記述（`^8.0` は 8.1 を含む）。

---

## 4. Go版3プロバイダーを PHP で再現する推奨ライブラリ構成

PHP 7.4・8.1 両対応を前提とした対応表。

| go-heijitu の依存 | 役割 | php-heijitu 推奨 | Composer | 7.4/8.1両対応バージョン | 備考 |
|---|---|---|---|---|---|
| `holiday-jp/holiday_jp-go` | holidayjp（埋め込み・外部接続不要） | `holiday-jp/holiday_jp` | `holiday-jp/holiday_jp` | v2.3.0（`php >=5.3.3`） | Go版と同設計。データが古くなる弱点も同じ |
| （代替） | 計算駆動の祝日 | `azuyalabs/yasumi` | `azuyalabs/yasumi` | **v2.6.0固定**（`php >=7.4`） | 7.4両対応なら 2.6.0 必須 |
| `mikan/syukujitsu-go` | caoCsv（内閣府CSV取得・SJISパース） | 自前実装 + 標準機能 | **追加依存なし**（PHP標準 `file_get_contents`/cURL + mbstring + str_getcsv） | — | 専用パッケージは確認できず。guzzle は不採用（環境のバージョン縛り） |
| `google.golang.org/api/calendar/v3` | googleCalendar | `google/apiclient` | `google/apiclient` | **^2.16**（`php ^7.4 \| ^8.0`） | Calendar API・APIキー・サービスアカウント対応 |
| `gopkg.in/yaml.v3` | YAML設定 | `symfony/yaml` | `symfony/yaml` | **^5.4**（`php >=7.2.5`） | 6.x以降は8.1必須で7.4不可 |
| （JSON設定） | JSONパース | PHP標準 | — | `json_decode` | 外部依存不要 |
| Go `testing` | 単体テスト | `phpunit/phpunit` | `phpunit/phpunit` | **^9.6**（`php >=7.3`） | 10以降は8.1必須で7.4不可 |

---

## 5. 調査で確認できなかった事項・要確認

- 内閣府CSV専用の Composer パッケージ（`syukujitsu-go` の直接相当）は確認できなかった → caoCsv プロバイダーは自前実装になる。
- `holiday-jp/holiday_jp`（PHP版）の `between()` が返す要素の正確なキー構成・祝日名フォーマットは、採用決定後に実物で確認する必要がある。
- `google/apiclient` + `apiclient-services` の組み合わせで PHP 8.1 上の実挙動は、採用決定後に実機検証する必要がある。

---

_本資料は go-heijitu のコード・設計資料（`.kiro/specs/initial-planning/planning/`）の精読と、PHP エコシステムの Web 調査に基づく。設計方針・要決定事項は `design.md` / `decisions.md`、開発手順は `workplan.md` を参照。_
