# 設計書: Step 5 — example・PHPDoc・README（ドキュメント整備）

## Overview

`php-heijitu` を利用者向けに公開できる状態にするため、サンプルコード・PHPDoc・README・APIドキュメントを整備する。

Step 1–4 でコア実装・全プロバイダーが完成しており、本ステップはコードへのロジック変更を一切行わない純粋なドキュメント整備である。変更は **PHPDoc コメントの追記**（既存 src/ ファイルへの追記のみ）と **新規ドキュメントファイルの作成**（examples/・docs/・ROOT）の 2 種類に分類できる。

**Users**: ライブラリ利用者（PHP プロジェクトでの組み込み）および php-heijitu 開発者（PHP 7.4・8.1 最終確認）が主なターゲット。

### Goals

- 全公開 API に PHPDoc を揃え、IDE の型補完を完全に機能させる
- `examples/main.php` の実行で全 API パターンを確認できる状態にする
- README を読んで初めての利用者がインストールから使用開始できる状態にする
- PHP 7.4・8.1 両環境でテストが通ることを確認する

### Non-Goals

- コア実装・プロバイダー実装へのロジック変更
- 静的解析（phpstan 等）の導入
- Packagist 公開・タグ付けなどのリリース工程
- `composer.json` の `scripts` セクション追加（要件外）

---

## Boundary Commitments

### This Spec Owns

- `src/` 既存ファイルへの PHPDoc コメント追記（コメントのみ、ロジック変更なし）
- `examples/main.php` の新規作成
- `README.md`（スケルトン全面置換）・`README-ja.md`・`CHANGELOG.md`・`CONTRIBUTING.md`・`LICENSE` の作成
- `docs/en/` および `docs/ja/` 以下の 6 ドキュメントファイルの作成
- PHP 7.4・8.1 の両 Docker 環境でのテスト実行確認

### Out of Boundary

- `src/` の既存ロジックへの変更（バグ修正・最適化を含む）
- テストコードの変更・追加（`tests/` 以下は不変）
- `composer.json`・`phpunit.xml`・`docker/` の変更
- Step 1–4 の仕様書（requirements・design・tasks）への変更

### Allowed Dependencies

- `src/` 実装（BusinessCalendar・各プロバイダー・例外クラス）を read-only で参照し、PHPDoc・examples のコンテンツソースとして使用する
- `docs/planning/design.md`・`decisions.md`・`workplan.md` を参照し、ドキュメント内容の根拠とする（※ Task 6 実行後は `.kiro/specs/initial-planning/planning/` へ移動済み）
- Docker 環境（`docker/compose.yaml`）を利用するが変更しない

### Revalidation Triggers

- `src/` の公開 API シグネチャが変更された場合（PHPDoc・docs/ の記述内容を再確認）
- `HolidayProvider` インターフェースのメソッドシグネチャが変更された場合（プロバイダー PHPDoc 再確認）
- PHP 7.4 または 8.1 の Docker 環境が変更された場合（最終確認手順の再実施）

---

## Architecture

### Existing Architecture Analysis

本ステップで変更するファイルの現状:

| ファイル | 現状の PHPDoc 充足率 | 対応 |
|---------|-------------------|------|
| `src/BusinessCalendar.php` | 100% | 変更なし |
| `src/HolidayProvider.php` | 100% | 変更なし |
| `src/Config.php` | 100% | 変更なし |
| `src/Holiday.php` | 0% | PHPDoc 追記 |
| `src/MonthDay.php` | 0% | PHPDoc 追記 |
| `src/Providers/HolidayJp/Provider.php` | 0% | PHPDoc 追記 |
| `src/Providers/CaoCsv/Provider.php` | 25% | PHPDoc 追記 |
| `src/Providers/GoogleCalendar/Provider.php` | 50% | PHPDoc 追記 |
| `src/Exception/HeijituException.php` | 0% | PHPDoc 追記 |
| `src/Exception/ConfigException.php` | 0% | PHPDoc 追記 |
| `src/Exception/ProviderException.php` | 0% | PHPDoc 追記 |

`README.md` は `# php-heijitu` の 1 行スケルトンのみ。`docs/en/`・`docs/ja/`・`examples/` は未作成。

### Architecture Pattern & Boundary Map

```mermaid
graph TB
    subgraph 既存コード（変更なし）
        BC[BusinessCalendar.php]
        HP[HolidayProvider.php]
        H[Holiday.php]
        MD[MonthDay.php]
        C[Config.php]
        EX[Exception クラス群]
        PH[Providers/HolidayJp]
        PC[Providers/CaoCsv]
        PG[Providers/GoogleCalendar]
    end

    subgraph PHPDoc追記（コメントのみ）
        H --> H_DOC[Holiday PHPDoc]
        MD --> MD_DOC[MonthDay PHPDoc]
        PH --> PH_DOC[HolidayJp PHPDoc]
        PC --> PC_DOC[CaoCsv PHPDoc]
        PG --> PG_DOC[GoogleCalendar PHPDoc]
        EX --> EX_DOC[Exception PHPDoc]
    end

    subgraph 新規ドキュメント
        EXAMPLES[examples/main.php]
        README_EN[README.md]
        README_JA[README-ja.md]
        CHANGELOG[CHANGELOG.md]
        CONTRIB[CONTRIBUTING.md]
        LICENSE[LICENSE]
        DOCS_EN[docs/en 3ファイル]
        DOCS_JA[docs/ja 3ファイル]
    end

    BC -.参照.-> EXAMPLES
    HP -.参照.-> DOCS_EN
    HP -.参照.-> DOCS_JA
```

### Technology Stack

| 層 | 選択 | 役割 |
|----|------|------|
| Runtime | PHP 7.4 / 8.1 | examples/main.php の実行確認 |
| Documentation | Markdown | README・docs/ の記述形式 |
| PHP Comments | PHPDoc（標準）| IDE 補完・型ヒント |
| Test Runner | PHPUnit 9.6（既存） | 最終確認での実行 |
| Container | Docker（既存 docker/compose.yaml）| PHP 7.4/8.1 環境 |

---

## File Structure Plan

### New Files

```
examples/
└── main.php                    # 全プロバイダー・全 API パターンを示すサンプルスクリプト

README.md                       # 英語 README（スケルトンを全面置換）
README-ja.md                    # 日本語 README
CHANGELOG.md                    # Keep a Changelog 形式の変更履歴
CONTRIBUTING.md                 # コントリビューションガイド
LICENSE                         # MIT ライセンス全文

docs/
├── en/
│   ├── api-spec.md             # 英語 API リファレンス（型定義・メソッドシグネチャ・設定ファイル仕様）
│   ├── usage.md                # 英語 使い方ガイド（インストール〜ユースケース別の使い方）
│   └── providers.md            # 英語 プロバイダーガイド（選択基準・設定・注意点）
└── ja/
    ├── api-spec.md             # 日本語 API リファレンス（api-spec.md の日本語版）
    ├── usage.md                # 日本語 使い方ガイド（usage.md の日本語版）
    └── providers.md            # 日本語 プロバイダーガイド（providers.md の日本語版）
```

### Modified Files

```
src/Holiday.php                 # PHPDoc 追記: クラス説明・__construct・getDate・getName
src/MonthDay.php                # PHPDoc 追記: クラス説明・__construct・getMonth・getDay・matches
src/Providers/HolidayJp/Provider.php    # PHPDoc 追記: クラス説明・__construct・isHoliday・holidayName・holidaysBetween
src/Providers/CaoCsv/Provider.php       # PHPDoc 追記: isHoliday・holidayName への {@inheritdoc} 追記
src/Providers/GoogleCalendar/Provider.php # PHPDoc 追記: isHoliday・holidayName への {@inheritdoc} 追記
src/Exception/HeijituException.php      # PHPDoc 追記: インターフェース説明（マーカー IF の用途）
src/Exception/ConfigException.php       # PHPDoc 追記: クラス説明（設定ファイルエラー用例外）
src/Exception/ProviderException.php     # PHPDoc 追記: クラス説明（プロバイダーエラー用例外）
```

---

## Components and Interfaces

### コンポーネント一覧

| コンポーネント | 種別 | 概要 | 要件 |
|-------------|------|------|------|
| PHPDoc 追記（値オブジェクト） | src 変更 | Holiday・MonthDay へのコメント追記 | 2.1–2.3 |
| PHPDoc 追記（プロバイダー） | src 変更 | 3プロバイダーへのコメント追記 | 2.4 |
| PHPDoc 追記（例外クラス） | src 変更 | 3例外クラスへのコメント追記 | 2.5 |
| examples/main.php | 新規作成 | 全パターンのサンプルスクリプト | 1.1–1.7 |
| README（en/ja）| 新規作成 | ルートドキュメント | 3.1–3.4 |
| ルートドキュメント | 新規作成 | CHANGELOG・CONTRIBUTING・LICENSE | 3.5–3.7 |
| docs/en・docs/ja | 新規作成 | API仕様・使い方・プロバイダーガイド | 4.1–4.6 |
| PHP 7.4・8.1 最終確認 | 運用手順 | Docker 環境での検証 | 5.1–5.5 |

---

### PHPDoc 追記コンポーネント

#### Holiday.php PHPDoc

| Field | Detail |
|-------|--------|
| Intent | 祝日の日付と名称を保持する不変値オブジェクト |
| Requirements | 2.1, 2.2, 2.3 |

**追記内容**:

```php
/**
 * 祝日を表す値オブジェクト
 *
 * 日付（DateTimeImmutable）と祝日名（string）を保持する不変クラス。
 * HolidayProvider::holidaysBetween() の戻り値要素として使用される。
 */
final class Holiday
{
    /**
     * @param \DateTimeImmutable $date 祝日の日付
     * @param string $name 祝日名
     */
    public function __construct(\DateTimeImmutable $date, string $name) { ... }

    /** @return \DateTimeImmutable 祝日の日付 */
    public function getDate(): \DateTimeImmutable { ... }

    /** @return string 祝日名 */
    public function getName(): string { ... }
}
```

#### MonthDay.php PHPDoc

| Field | Detail |
|-------|--------|
| Intent | 年をまたいで有効な月日を表す不変値オブジェクト |
| Requirements | 2.1, 2.2, 2.3 |

**追記内容**:

```php
/**
 * 月日を表す値オブジェクト
 *
 * 特定の月・日を表し、年には依存しない。BusinessCalendar の除外日付指定に使用される。
 * バリデーションは行わない（2/30 等は如何なる日付にも一致しない）。
 */
final class MonthDay
{
    /**
     * @param int $month 月（1〜12）
     * @param int $day   日（1〜31）
     */
    public function __construct(int $month, int $day) { ... }

    /** @return int 月（1〜12） */
    public function getMonth(): int { ... }

    /** @return int 日（1〜31） */
    public function getDay(): int { ... }

    /**
     * 指定日の月・日がこのオブジェクトと一致するかどうかを返す（年は無視）
     *
     * @param \DateTimeImmutable $t 判定対象日
     * @return bool 月・日が一致すれば true
     */
    public function matches(\DateTimeImmutable $t): bool { ... }
}
```

#### プロバイダー PHPDoc パターン

| Field | Detail |
|-------|--------|
| Intent | 3プロバイダーの公開 API に PHPDoc を揃える |
| Requirements | 2.4 |

**HolidayJp/Provider.php 追記内容**（クラス説明 + コンストラクタ + インターフェースメソッドへの `{@inheritdoc}`）:

```php
/**
 * holiday-jp/holiday_jp を使った埋め込みデータ祝日プロバイダー
 *
 * 外部ネットワーク接続不要。データは 2020 年までの情報を含む。
 * 2021 年以降の祝日変更（山の日等）は反映されないため、本番用途では
 * CaoCsv または GoogleCalendar プロバイダーを推奨する。
 *
 * @throws \Heijitu\Exception\ProviderException holiday-jp/holiday_jp 未導入時
 */
final class Provider implements HolidayProvider
{
    /**
     * @throws \Heijitu\Exception\ProviderException holiday-jp/holiday_jp 未導入時
     */
    public function __construct() { ... }

    /** {@inheritdoc} */
    public function isHoliday(\DateTimeImmutable $t): bool { ... }

    /** {@inheritdoc} */
    public function holidayName(\DateTimeImmutable $t): string { ... }

    /** {@inheritdoc} */
    public function holidaysBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array { ... }
}
```

**CaoCsv/Provider.php 追記内容**（`isHoliday`・`holidayName` に `{@inheritdoc}` 追記のみ。コンストラクタ・`holidaysBetween` は既に PHPDoc あり）:

```php
/** {@inheritdoc} */
public function isHoliday(\DateTimeImmutable $t): bool { ... }

/** {@inheritdoc} */
public function holidayName(\DateTimeImmutable $t): string { ... }
```

**GoogleCalendar/Provider.php 追記内容**（同上）:

```php
/** {@inheritdoc} */
public function isHoliday(\DateTimeImmutable $t): bool { ... }

/** {@inheritdoc} */
public function holidayName(\DateTimeImmutable $t): string { ... }
```

#### 例外クラス PHPDoc

| Field | Detail |
|-------|--------|
| Intent | 例外クラスの用途をクラス説明として追記 |
| Requirements | 2.5 |

**HeijituException.php 追記内容**:
```php
/**
 * php-heijitu が投げる例外の共通マーカーインターフェース
 *
 * catch (HeijituException $e) で本ライブラリが投げる全例外を一括捕捉できる。
 */
interface HeijituException { }
```

**ConfigException.php 追記内容**:
```php
/**
 * 設定ファイルの読み込み・パース失敗時の例外
 *
 * 未対応拡張子・YAML/JSON パース失敗・ファイル読み込み失敗の際に投げられる。
 */
class ConfigException extends \RuntimeException implements HeijituException { }
```

**ProviderException.php 追記内容**:
```php
/**
 * プロバイダーのデータ取得・API 呼び出し失敗時の例外
 *
 * 依存パッケージ未導入・認証情報不備・外部 API エラーの際に投げられる。
 */
class ProviderException extends \RuntimeException implements HeijituException { }
```

---

### examples/main.php コンポーネント

| Field | Detail |
|-------|--------|
| Intent | 全プロバイダー・全 API パターンを示す実行可能なサンプルスクリプト |
| Requirements | 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7 |

**ファイル構成**（セクション別）:

| セクション | 内容 | 対応要件 |
|-----------|------|---------|
| require autoload | `require __DIR__ . '/../vendor/autoload.php'` | — |
| Section 1: HolidayJp 全 API | `isBusinessDay`・`nextBusinessDay`・`firstBusinessDayOfMonth`・`firstBusinessDaysOfYear`・`holidays` | 1.1 |
| Section 2: 除外日付（コンストラクタ） | `new BusinessCalendar($provider, [new MonthDay(8,15), new MonthDay(12,31)])` | 1.2 |
| Section 3: 設定ファイル + マージ | `Config::loadExcludedDates()` + `array_merge` | 1.3 |
| Section 4: CaoCsv ローカル | `new CaoCsv\Provider('/path/to/local.csv')` | 1.4 |
| Section 5: CaoCsv オンライン | `new CaoCsv\Provider()` — エラーは伝播させる（try/catch なし） | 1.5 |
| Section 6: extraExcluded | `$cal->isBusinessDay($t, new MonthDay(m, d))` | 1.6 |

**出力形式**（go-heijitu の examples/main.go に準拠。echo で stdout 出力）:

```
=== HolidayJp Provider ===
2024-01-01 is business day: false
Next business day after 2024-01-01: 2024-01-04
First business day of 2024-01: 2024-01-04
First business days of 2024:
  2024-01: 2024-01-04
  ...
Holidays 2024-01-01 to 2024-03-31:
  2024-01-01 元日
  ...

=== Excluded Dates (constructor) ===
2024-08-15 is business day (with 8/15 excluded): false
2024-08-15 is business day (without exclusion): true

=== Config File ===
Loaded excluded dates from config: 2 entries

=== CaoCsv Provider (local) ===
2024-01-01 is holiday (CaoCsv): true

=== extraExcluded ===
2024-08-12 is business day (no extra): true
2024-08-12 is business day (8/12 excluded): false
```

**設定ファイルサンプルの扱い**（Synthesis での決定通り）:
- `examples/main.php` 内でインラインの heredoc または一時ファイル生成パターンで示す
- `examples/config/` ディレクトリは作成しない（ファイル構造を増やさない）

---

### README・ルートドキュメント コンポーネント

| Field | Detail |
|-------|--------|
| Intent | 初めての利用者がインストールから使用開始できる導線を提供する |
| Requirements | 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7 |

**README.md / README-ja.md の構成**:

| セクション | 内容 |
|-----------|------|
| タイトル・バッジ | パッケージ名・ライセンスバッジ |
| インストール | GitHub VCS 登録 + composer require（プロバイダー依存も案内） |
| クイックスタート | HolidayJp を使った最小サンプル |
| 除外日付の指定 | コンストラクタ引数・設定ファイルの両方 |
| プロバイダー | 3プロバイダーへのリンク |
| データの注意 | `holiday-jp/holiday_jp` の陳腐化注意（decisions.md E-1）+ 代替案内 |
| タイムゾーン | JST 運用推奨 + `date_default_timezone_set('Asia/Tokyo')` 例（decisions.md E-2） |
| ドキュメント | docs/ へのリンク |
| ライセンス | MIT |

**CHANGELOG.md** — Keep a Changelog 形式（`[Unreleased]` セクションのみ初期作成）

**CONTRIBUTING.md** — PR の送り方・コーディング規約（PHP 7.4 基準・PHPUnit 実行方法）

**LICENSE** — MIT ライセンス全文（year: 2024–現在、author: taku-o）

---

### docs/ コンポーネント

| Field | Detail |
|-------|--------|
| Intent | 利用者がリファレンス・ユースケース・プロバイダー設定を参照できる詳細ドキュメント |
| Requirements | 4.1, 4.2, 4.3, 4.4, 4.5, 4.6 |

**api-spec.md（en/ja）の構成**（シグネチャ集中・コード例は usage.md に委譲）:

| セクション | 内容 |
|-----------|------|
| BusinessCalendar | コンストラクタ・全5メソッドのシグネチャ・例外 |
| HolidayProvider interface | 3メソッドのシグネチャ・例外 |
| MonthDay | コンストラクタ・3メソッドのシグネチャ |
| Holiday | コンストラクタ・2メソッドのシグネチャ |
| Config | `loadExcludedDates` シグネチャ |
| 例外クラス | HeijituException・ConfigException・ProviderException の階層と発生条件 |
| 設定ファイル形式 | YAML・JSON 両フォーマットの仕様表 |

**usage.md（en/ja）の構成**（コード例中心・api-spec.md とのリンクで重複排除）:

| セクション | 内容 |
|-----------|------|
| インストール | GitHub VCS 登録コマンド |
| 基本的な使い方 | HolidayJp で isBusinessDay |
| 除外日付の指定 | コンストラクタ引数・Config::loadExcludedDates・array_merge |
| API ユースケース | isBusinessDay・nextBusinessDay・firstBusinessDayOfMonth・firstBusinessDaysOfYear・holidays の各コード例 |
| extraExcluded | isBusinessDay の可変長引数パターン |
| タイムゾーン | JST 設定方法 |

**providers.md（en/ja）の構成**:

| セクション | 内容 |
|-----------|------|
| 比較表 | HolidayJp・CaoCsv・GoogleCalendar の特徴・依存・推奨ユースケース |
| HolidayJp | セットアップ・データ陳腐化注意（2021年以降の祝日変更） |
| CaoCsv | ローカル / オンラインモードのセットアップ・mbstring 要件 |
| GoogleCalendar | API キー取得（GCP コンソール手順）・コンストラクタ引数の渡し方・環境変数連携パターン（`getenv('GOOGLE_API_KEY')`）|

---

### 最終確認コンポーネント

| Field | Detail |
|-------|--------|
| Intent | PHP 7.4・8.1 両環境でのテスト通過と examples/main.php の動作を確認する |
| Requirements | 5.1, 5.2, 5.3, 5.4, 5.5 |

**確認手順**（Docker 環境使用）:

```bash
# PHP 7.4 でテスト実行
docker compose -f docker/compose.yaml run --rm php74 vendor/bin/phpunit

# PHP 8.1 でテスト実行
docker compose -f docker/compose.yaml run --rm php81 vendor/bin/phpunit

# PHP 7.4 で examples 実行
docker compose -f docker/compose.yaml run --rm php74 php examples/main.php

# PHP 8.1 で examples 実行
docker compose -f docker/compose.yaml run --rm php81 php examples/main.php
```

**確認観点**:
- PHP 7.4 / 8.1 ともに `phpunit` 出力に FAILURE / ERROR なし（integration グループは除外）
- PHP 8.1 で `phpunit` 実行時に Deprecated 警告なし
- `examples/main.php` が両バージョンで期待通りの出力を返す

---

## Requirements Traceability

| 要件 | 概要 | コンポーネント |
|------|------|--------------|
| 1.1 | HolidayJp 全 API サンプル | examples/main.php Section 1 |
| 1.2 | コンストラクタ除外日付サンプル | examples/main.php Section 2 |
| 1.3 | Config ファイル + array_merge サンプル | examples/main.php Section 3 |
| 1.4 | CaoCsv ローカルモードサンプル | examples/main.php Section 4 |
| 1.5 | CaoCsv オンラインモードサンプル | examples/main.php Section 5 |
| 1.6 | extraExcluded サンプル | examples/main.php Section 6 |
| 1.7 | php examples/main.php 正常実行 | 最終確認コンポーネント |
| 2.1 | 全公開クラスに PHPDoc | Holiday・MonthDay・プロバイダー・例外 PHPDoc 追記 |
| 2.2 | 全公開メソッドに @param/@return/@throws | 各 PHPDoc 追記の仕様 |
| 2.3 | 配列型は Holiday[] 等で表現 | Holiday・MonthDay PHPDoc |
| 2.4 | プロバイダークラスに PHPDoc | 3プロバイダー PHPDoc 追記 |
| 2.5 | 例外クラスに PHPDoc | 例外クラス PHPDoc 追記 |
| 3.1 | README.md / README-ja.md | README コンポーネント |
| 3.2 | README: インストール・使用例・ライセンス | README コンポーネント |
| 3.3 | README: holiday-jp データ陳腐化注意 | README コンポーネント |
| 3.4 | README: JST 運用案内 | README コンポーネント |
| 3.5 | CHANGELOG.md | ルートドキュメントコンポーネント |
| 3.6 | CONTRIBUTING.md | ルートドキュメントコンポーネント |
| 3.7 | LICENSE | ルートドキュメントコンポーネント |
| 4.1 | docs/en・docs/ja の api-spec.md | docs/ コンポーネント |
| 4.2 | api-spec.md: 設定ファイル仕様を含む | docs/ コンポーネント |
| 4.3 | docs/en・docs/ja の usage.md | docs/ コンポーネント |
| 4.4 | docs/en・docs/ja の providers.md | docs/ コンポーネント |
| 4.5 | providers.md: Google Calendar API キー手順 | docs/ コンポーネント |
| 4.6 | providers.md: holiday-jp データ陳腐化注意 | docs/ コンポーネント |
| 5.1 | PHP 7.4 でテスト通過 | 最終確認コンポーネント |
| 5.2 | PHP 8.1 でテスト通過 | 最終確認コンポーネント |
| 5.3 | PHP 8.1 で Deprecated 警告なし | 最終確認コンポーネント |
| 5.4 | PHP 7.4 で examples/main.php 正常実行 | 最終確認コンポーネント |
| 5.5 | PHP 8.1 で examples/main.php 正常実行 | 最終確認コンポーネント |

---

## Testing Strategy

本ステップの「テスト」は、Step 5 で作成した成果物の動作確認であり、テストコードの追加は行わない。

### 実行確認（Req 5）

| 確認項目 | コマンド | 合格基準 |
|---------|---------|---------|
| PHP 7.4 PHPUnit | `docker compose run --rm php74 vendor/bin/phpunit` | FAILURES: 0, ERRORS: 0 |
| PHP 8.1 PHPUnit | `docker compose run --rm php81 vendor/bin/phpunit` | FAILURES: 0, ERRORS: 0, Deprecated: 0 |
| PHP 7.4 examples 実行 | `docker compose run --rm php74 php examples/main.php` | exit code 0・期待出力と一致 |
| PHP 8.1 examples 実行 | `docker compose run --rm php81 php examples/main.php` | exit code 0・期待出力と一致 |

### PHPDoc の確認

自動テストではなく目視確認:
- 対象 IDE（VSCode + PHP Intelephense 等）で `Holiday`・`MonthDay` のメソッド補完が型情報付きで表示される

### examples/main.php の確認

- `php examples/main.php` を PHP 7.4・8.1 の両方で実行し、Fatal error / Exception なしで完走する（Req 1.7）
- CaoCsv オンラインモードは実行可能なコードとして含める。ネットワーク失敗時は ProviderException をそのまま伝播させる（try/catch で握りつぶさない）
- 実行確認はネットワーク接続が利用できる環境で行う

---

## Error Handling

本ステップはドキュメント追記・ファイル作成のみであり、新たなエラー処理ロジックは追加しない。

`examples/main.php` では各セクションで API 呼び出しパターンを示す。Section 5（CaoCsv オンラインモード）はネットワーク失敗時に `ProviderException` をそのまま伝播させる（`try/catch` しない）。エラーを握りつぶすフォールバックは行わない。
