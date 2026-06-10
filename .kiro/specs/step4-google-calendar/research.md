# ギャップ分析: Step 4 — Google Calendar APIプロバイダー実装

## 分析サマリー

- **新規ファイル1本のみ**（`src/Providers/GoogleCalendar/Provider.php`）で要件を満たせる。既存の HolidayJp / CaoCsv プロバイダーのパターンをほぼそのまま踏襲できる。
- **最重要制約**: `google/apiclient` の `^2.16` 指定は v2.17+ に解決されると PHP 7.4 互換が壊れる。`require-dev` と `suggest` を `~2.16.0` に固定する必要がある。
- **PHP 8.1 deprecation 警告**: v2.16.x は PHP 8.1 で deprecated notice が出る（暗黙 nullable 等）。decisions.md A-3 の「deprecation 警告も出ないようにする」要件と衝突するため、対処方針を設計フェーズで決定する。
- **設計上の決定事項**: CaoCsv はコンストラクタで全データをロードするが、GoogleCalendar はコンストラクタ時点で日付範囲が不明なため、同一方式が使えない。取得タイミング（コンストラクタ vs 遅延）の設計が必要。
- 規模 **S〜M**、リスク **中**（バージョン固定と deprecation 対処が主なリスク）。

---

## 1. 現状コードベースの調査

### 1.1 既存プロバイダーの実装パターン

両プロバイダーとも以下のパターンに従っている：

```php
final class Provider implements HolidayProvider
{
    public function __construct(/* 認証情報 or オプション */)
    {
        // 1. 依存ライブラリの存在確認 (class_exists / extension_loaded)
        //    → 未導入なら ProviderException で案内メッセージ
        // 2. データの取得・ロード（CaoCsv はコンストラクタで完了）
    }

    public function isHoliday(\DateTimeImmutable $t): bool { ... }
    public function holidayName(\DateTimeImmutable $t): string { ... }
    public function holidaysBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array { ... }
}
```

**HolidayJp**: コンストラクタで `class_exists(\HolidayJp\HolidayJp::class)` を確認し、各メソッドで都度静的メソッドを呼ぶ（データ保持なし）。

**CaoCsv**: コンストラクタで CSV 全件を読み込んで内部配列に保持、メソッドはその配列を参照する。

### 1.2 テストパターン

| 要素 | 現在のパターン |
|------|--------------|
| テストクラス配置 | `tests/Providers/{Name}/ProviderTest.php` |
| `HolidayProvider` 実装確認 | `assertInstanceOf(HolidayProvider::class, $provider)` |
| 祝日アサーション | `assertHoliday($holiday, '2024-01-01', '元日')` ヘルパー |
| from > to | 空配列を返すことを検証 |
| 実 API テスト | `@group integration` でアノテーションし、phpunit.xml のデフォルト実行から除外 |

### 1.3 composer.json の現状

```json
"require-dev": {
    ...,
    "google/apiclient": "~2.16.0"   // Step 4 で修正済み（^2.16 → ~2.16.0）
},
"suggest": {
    "google/apiclient": "GoogleCalendar用 (~2.16.0)"  // Step 4 で修正済み
}
```

### 1.4 phpunit.xml の integration 除外設定

```xml
<groups>
  <exclude><group>integration</group></exclude>
</groups>
```

GoogleCalendar の integration テストは設定変更なしで自動的に除外される。

---

## 2. 要件対応ギャップ分析

### Requirement 1: GoogleCalendar プロバイダーの実装

| 技術的ニーズ | 現状 | ギャップ |
|------------|------|--------|
| `HolidayProvider` を実装するクラス | インターフェース定義済み | **MISSING**: `src/Providers/GoogleCalendar/Provider.php` |
| Google Calendar API 接続 | なし | **MISSING**: `google/apiclient` を使った API 呼び出し |
| 全件取得（ページング） | なし | **MISSING**: `pageToken` ループ実装 |
| 終日イベント判別 | なし | **MISSING**: `start->getDate()` / `start->getDateTime()` 判別 |

**設計決定事項（Research Needed）**: データ取得タイミングの設計

CaoCsv はコンストラクタで全件ロードするが、GoogleCalendar はコンストラクタ時点で日付範囲が不明。以下のオプションがある：

| オプション | 説明 | 問題点 |
|-----------|------|------|
| **A: `holidaysBetween` 呼び出し時に取得** | 引数の `$from`〜`$to` で API クエリ | `isHoliday` で毎回 API 呼び出しが発生しうる |
| **B: インスタンス内キャッシュ** | 取得済み範囲をインスタンス変数に保持し、範囲外なら追加取得 | 実装が複雑 |
| **C: 年単位での遅延取得＋インスタンスキャッシュ** | `isHoliday($t)` 時に対象年が未取得なら年全体を取得してキャッシュ | go-heijitu の方針（「キャッシュなし」）との兼ね合い |
| **D: コンストラクタで `timeMin`/`timeMax` を受け取る** | 取得対象期間をコンストラクタで指定 | 他プロバイダーと API が異なる。`isHoliday` で範囲外の日付に対応できない |

go-heijitu の実装は年単位の遅延取得（オプション C に近い）を採用していた可能性が高い。設計フェーズで確定する。

### Requirement 2: 認証設定

| 技術的ニーズ | 現状 | ギャップ |
|------------|------|--------|
| API キー認証 | なし | **MISSING**: `$client->setDeveloperKey($apiKey)` |
| サービスアカウント認証 | なし | **MISSING**: `$client->setAuthConfig($credentialsFile)` + `addScope()` |
| `credentialsFile` 優先ロジック | なし | **MISSING**: 両方指定時の分岐 |
| 両方空での ProviderException | なし | **MISSING**: コンストラクタでの検証 |

**調査結果**: 祝日カレンダー（`ja.japanese.official#holiday@group.v.calendar.google.com`）は**公開カレンダー**のため、API キー認証で読み取り可能。サービスアカウントに `CALENDAR_READONLY` スコープを追加すれば同様に動作する。

### Requirement 3: 祝日判定メソッド

| 技術的ニーズ | 現状 | ギャップ |
|------------|------|--------|
| `isHoliday` / `holidayName` / `holidaysBetween` | HolidayProvider インターフェース定義済み | **MISSING**: 実装本体 |

パターンは CaoCsv / HolidayJp から直接移植可能。

### Requirement 4: google/apiclient 依存検出

| 技術的ニーズ | 現状 | ギャップ |
|------------|------|--------|
| `class_exists` による存在確認 | HolidayJp で `class_exists(\HolidayJp\HolidayJp::class)` パターン確立 | **MISSING**: GoogleCalendar 版（`\Google\Client::class`） |
| **バージョン固定** | `require-dev` が `^2.16` | **CONSTRAINT**: v2.17+ で PHP 7.4 互換破壊。`~2.16.0` への修正が必要 |

### Requirement 5: テスト

| 技術的ニーズ | 現状 | ギャップ |
|------------|------|--------|
| 契約テスト（資格情報なし→例外） | なし | **MISSING**: `ProviderTest::testThrowsWhenNoCredentials()` |
| `@group integration` テスト | phpunit.xml 設定済み | **MISSING**: 実 API 呼び出しテスト本体 |
| 環境変数からの資格情報読み取り | なし | **MISSING**: `getenv('GOOGLE_API_KEY')` / `getenv('GOOGLE_CREDENTIALS_FILE')` を使うテストセットアップ |

---

## 3. 実装アプローチ評価

### Option A: 新規コンポーネント作成（推奨）

- **新規ファイル**: `src/Providers/GoogleCalendar/Provider.php`、`tests/Providers/GoogleCalendar/ProviderTest.php`
- **変更ファイル**: `composer.json`（`^2.16` → `~2.16.0`）
- 既存のプロバイダーパターンと一致し、責務の分離が明確

**Trade-offs**:
- ✅ 既存プロバイダーパターンを踏襲、実装・テスト設計が明確
- ✅ 既存コンポーネントへの影響なし
- ❌ google/apiclient の PHP 8.1 deprecation 警告への対処が必要

---

## 4. 重要制約と調査結果

### 制約 1: `google/apiclient` のバージョン固定（必須）

**問題**: `^2.16` は Composer の解決で v2.17.0+ に更新される場合がある。v2.17.0 から PHP 要件が `^8.1` に変更されており、PHP 7.4 環境で `composer install` が失敗する。

**対処**: `require-dev` と `suggest` を `^2.16` から `~2.16.0` に変更する（`~` = マイナーバージョン固定、パッチのみ更新許容）。

### 制約 2: PHP 8.1 の deprecation 警告（要設計判断）

`google/apiclient` v2.16.x は PHP 8.1 で以下の Deprecated Notice を出す：
- 暗黙的 nullable パラメータ（`Type $param = null`）
- `ArrayAccess` の戻り値型宣言なし

decisions.md A-3 は「8.1 で deprecation 警告も含めてエラーが出ないことを確認する」としている。機能は動作するが、テスト出力が汚染される。

**設計フェーズで決定が必要な対処オプション**:
1. PHPUnit 設定で `E_DEPRECATED` を抑制（テスト実行時のみ）
2. PHP 7.4 環境での実行のみでテストし、8.1 は型エラーがないことのみ確認
3. 許容範囲として記録し、ドキュメントで注記（go-heijitu も同様の弱点を持つ）

### 制約 3: データ取得タイミング（設計決定事項）

CaoCsv との相違点（セクション 2 Requirement 1 参照）。設計フェーズで go-heijitu の実装を参照しながら確定する。

### 制約 4: `google/apiclient` が `guzzlehttp/guzzle` を引き込む

transitive dependency として guzzle が入る。これは `require-dev` 経由であるため利用者には影響しない（コア `require` は変更なし）。

---

## 5. 規模とリスク評価

| 項目 | 評価 | 根拠 |
|------|------|------|
| 規模 | **S〜M**（2〜4日） | 新規クラス1本、既存パターン移植可能。バージョン固定と deprecation 対処に追加作業 |
| リスク | **中** | `^2.16` バージョン固定は必須修正。PHP 8.1 deprecation の扱いは判断が必要。API パターン自体は調査済みで明確 |

---

## 6. 設計フェーズへの引き継ぎ事項

### 確定済み（設計で仕様化できるもの）
- `final class Provider implements HolidayProvider` パターン
- `class_exists(\Google\Client::class)` による依存確認
- API キー: `$client->setDeveloperKey($apiKey)`
- サービスアカウント: `$client->setAuthConfig($file)` + `addScope(CALENDAR_READONLY)`
- 終日イベント判別: `$event->getStart()->getDate() !== null`
- ページング: `getNextPageToken()` ループ
- `composer.json` の `~2.16.0` 固定

### 設計フェーズで決定が必要なもの
1. ~~**データ取得タイミング**~~ → ✅ **確定: クエリ都度取得**（`isHoliday`/`holidayName` は単一日取得、`holidaysBetween` は `$from`〜`$to` 範囲取得）
2. ~~**PHP 8.1 deprecation 警告の対処方針**~~ → ✅ **確定: 警告は許容**（動作することを優先。deprecation notice は v2.16.x の既知制約として受け入れる）
3. ~~**`timeMin`/`timeMax` の扱い**~~ → ✅ **確定: メソッド引数の日付範囲をそのままクエリ範囲とする**。`isHoliday($t)` / `holidayName($t)` は `$t` の当日1日分、`holidaysBetween($from, $to)` は `$from`〜`$to` をそのまま `timeMin`/`timeMax` に渡す。APIコール頻度の最適化は呼び出し側の責任（キャッシュ・`holidaysBetween` への切り替え等）とし、ライブラリは関与しない。
