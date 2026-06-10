# ギャップ分析: Step 5 — example・PHPDoc・README（ドキュメント整備）

**分析日**: 2026-06-10
**対象仕様**: `.kiro/specs/step5-docs/requirements.md`

---

## 1. 現状調査サマリ

### 1.1 既存ドキュメント状況

| 成果物 | 状況 |
|-------|------|
| `README.md` | ⚠️ スケルトンのみ（14バイト、`# php-heijitu` の1行のみ） |
| `README-ja.md` | ❌ 未作成 |
| `CHANGELOG.md` | ❌ 未作成 |
| `CONTRIBUTING.md` | ❌ 未作成 |
| `LICENSE` | ❌ 未作成 |
| `examples/` | ❌ ディレクトリ未作成 |
| `docs/en/` | ❌ 未作成（`docs/planning/` のみ存在） |
| `docs/ja/` | ❌ 未作成 |

### 1.2 既存 PHPDoc 状況（ファイル別）

| ファイル | 公開メソッド数 | PHPDoc あり | ギャップ |
|---------|-------------|------------|---------|
| `src/BusinessCalendar.php` | 6 | 6 | なし ✅ |
| `src/HolidayProvider.php`（IF） | 3 | 3 | なし ✅ |
| `src/Config.php` | 1 | 1 | なし ✅ |
| `src/Holiday.php` | 3 | 0 | **3メソッド不足** ❌ |
| `src/MonthDay.php` | 4 | 0 | **4メソッド不足** ❌ |
| `src/Providers/HolidayJp/Provider.php` | 4 | 0 | **4メソッド不足（IF実装）** ❌ |
| `src/Providers/CaoCsv/Provider.php` | 4 | 1 | **3メソッド不足（isHoliday/holidayName等）** ⚠️ |
| `src/Providers/GoogleCalendar/Provider.php` | 4 | 2 | **2メソッド不足（isHoliday/holidayName）** ⚠️ |
| `src/Exception/HeijituException.php` | — | 0 | クラス説明不足 ❌ |
| `src/Exception/ConfigException.php` | — | 0 | クラス説明不足 ❌ |
| `src/Exception/ProviderException.php` | — | 0 | クラス説明不足 ❌ |

**推定充足率**: 約 55%（公開メソッド 24 件中 13 件に PHPDoc あり）

### 1.3 テスト環境状況

| 項目 | 状況 |
|------|------|
| PHPUnit 設定（`phpunit.xml`）| ✅ 設定済み（`integration` グループ除外設定あり） |
| `@group integration` 分離 | ✅ `CaoCsv/ProviderTest`・`GoogleCalendar/ProviderTest` に適用済み |
| Docker 環境（PHP 7.4）| ✅ `docker/compose.yaml` の `php74` サービスで構成済み |
| Docker 環境（PHP 8.1）| ✅ `docker/compose.yaml` の `php81` サービスで構成済み |
| `composer.json` の `scripts` セクション | ❌ 未定義（テスト実行のショートカットなし） |

---

## 2. 要件フィージビリティ分析

### 2.1 要件→既存資産マッピング

| 要件 | 関連既存資産 | ギャップ分類 |
|-----|------------|------------|
| Req 1: `examples/main.php` | なし（`examples/` ディレクトリ未作成） | **Missing** |
| Req 2: PHPDoc（全公開API） | 部分的に実装済み（BusinessCalendar 等） | **Partial / Missing** |
| Req 3: README.md / README-ja.md / ルートドキュメント | `README.md` スケルトンのみ | **Missing（実質未作成）** |
| Req 4: docs/en/ / docs/ja/ の api-spec・usage・providers | なし | **Missing** |
| Req 5: PHP 7.4・8.1 テスト通過確認 | Docker 環境あり・未実施 | **Constraint（実行手順が未整備）** |

### 2.2 既存コードからの情報抽出（ドキュメント執筆に必要）

`examples/main.php` と各ドキュメントの執筆に必要な情報はすでに実装済みコードから取得可能:

- **全公開 API シグネチャ**: `src/BusinessCalendar.php`（165行）に完全実装済み
- **プロバイダー使用パターン**: `src/Providers/HolidayJp/Provider.php`・`CaoCsv/Provider.php`・`GoogleCalendar/Provider.php` に実装済み
- **設定ファイル形式**: `src/Config.php` に YAML/JSON 両対応実装済み
- **除外日付パターン**: `src/MonthDay.php`・`BusinessCalendar::__construct()` に実装済み
- **例外設計**: `src/Exception/` に 3クラス実装済み
- **タイムゾーン処理**: `src/BusinessCalendar.php` の `defaultTimezone()` に `Asia/Tokyo` フォールバック実装済み
- **go-heijitu との差分**: `context.Context` 廃止・例外によるエラー伝播（既実装）

### 2.3 ギャップの詳細

**ギャップ 1: PHPDoc 不足**
- `Holiday.php`、`MonthDay.php`: クラス説明・全メソッドの `@param`/`@return` が未記述
- 各プロバイダーの `isHoliday()`・`holidayName()` メソッド: インターフェース実装のため PHPDoc を省略している
- 例外クラス 3 件: クラス説明（用途の記述）が未記述

**ギャップ 2: examples/ ディレクトリ不在**
- `examples/main.php` を作成するためのディレクトリ自体が未存在
- サンプルから参照できる設定ファイルサンプル（YAML/JSON）も必要

**ギャップ 3: docs/en/ / docs/ja/ ディレクトリ不在**
- `docs/planning/` は存在するが、利用者向けの `docs/en/`・`docs/ja/` は未作成
- 6 つのドキュメントファイル（各言語 3 ファイル）を新規作成する必要がある

**ギャップ 4: ルートドキュメント不在**
- `README.md` は実質未整備（スケルトンのみ）
- `README-ja.md`・`CHANGELOG.md`・`CONTRIBUTING.md`・`LICENSE` が未作成

**ギャップ 5: 最終確認手順の未整備**
- PHP 7.4・8.1 の両環境でのテスト実行は Docker 環境で可能だが、手順がドキュメント化されていない
- `composer.json` に `scripts` セクションがなく、テスト実行コマンドのショートカットが未定義

---

## 3. 実装アプローチ案

### Option A: 既存ファイルへの追記のみ

既存の `src/` ファイルへの PHPDoc 追記を行い、新規ドキュメントファイルは最小限にとどめる。

- **対象**: PHPDoc のみを既存ファイルに追記
- **ドキュメント**: README.md を拡充するのみ、詳細ドキュメントは作成しない

**トレードオフ**:
- ✅ 変更範囲が小さく、既存コードへの影響リスクが低い
- ❌ docs/ ディレクトリが不在のままで、Req 4 を満たせない
- ❌ requirements.md の要件を満たせない（Out of scope）

### Option B: 全成果物の新規作成（推奨）

PHPDoc の追記（既存ファイルへの変更）と、全ドキュメントファイルの新規作成を実施する。

**変更対象**:

*PHPDoc 追記（既存ファイルへの追記のみ。ロジック変更なし）*:
- `src/Holiday.php`: クラス説明 + 3メソッドの `@param`/`@return` 追記
- `src/MonthDay.php`: クラス説明 + 4メソッドの `@param`/`@return` 追記
- `src/Providers/HolidayJp/Provider.php`: クラス説明 + 4メソッドの `@param`/`@return`/`@throws` 追記
- `src/Providers/CaoCsv/Provider.php`: 3メソッドの `@param`/`@return`/`@throws` 追記
- `src/Providers/GoogleCalendar/Provider.php`: 2メソッドの `@param`/`@return`/`@throws` 追記
- `src/Exception/HeijituException.php`: クラス説明追記
- `src/Exception/ConfigException.php`: クラス説明追記
- `src/Exception/ProviderException.php`: クラス説明追記

*新規作成（ドキュメントのみ）*:
- `examples/main.php`
- `examples/config/excluded_dates.yaml`（サンプル設定ファイル）
- `README.md`（スケルトンを全面置換）
- `README-ja.md`
- `CHANGELOG.md`
- `CONTRIBUTING.md`
- `LICENSE`
- `docs/en/api-spec.md`
- `docs/ja/api-spec.md`
- `docs/en/usage.md`
- `docs/ja/usage.md`
- `docs/en/providers.md`
- `docs/ja/providers.md`

**トレードオフ**:
- ✅ 全要件を満たせる
- ✅ PHPDoc は既存コードへのコメント追記のみでロジック変更なし
- ✅ 新規ドキュメントはコードには無影響
- ❌ 作成ファイル数が多い（13 ファイル新規、8 ファイル変更）

### Option C: タスク分割での段階的実施

Option B の内容を、機能領域ごとにタスクに分割して段階的に実施する（tasks.md での管理）。

**タスク分割案**:
1. PHPDoc 追記（既存 8 ファイルへのコメント追記）
2. `examples/main.php` + サンプル設定ファイル作成
3. ルートドキュメント（README 英日・CHANGELOG・CONTRIBUTING・LICENSE）
4. `docs/en/` ドキュメント 3 ファイル
5. `docs/ja/` ドキュメント 3 ファイル
6. PHP 7.4・8.1 最終確認

**トレードオフ**:
- ✅ Option B と同じ成果物を、進捗管理しながら実施できる
- ✅ タスク単位でレビュー・確認が可能
- ❌ Option B との実質的な違いはなく、tasks.md の構成の違いのみ

---

## 4. 実装複雑度・リスク評価

| 作業 | 工数 | リスク | 理由 |
|------|------|--------|------|
| PHPDoc 追記 | S | Low | 既存ロジック不変・コメント追記のみ |
| `examples/main.php` 作成 | S | Low | 既存 API の呼び出しのみ・実行確認で完結 |
| README（英日）作成 | S | Low | 定型フォーマット・参照元（design.md等）が充実 |
| CHANGELOG・CONTRIBUTING・LICENSE | S | Low | テンプレートに沿った定型文書 |
| `docs/en/` 3 ファイル | M | Low | 参照元（design.md・decisions.md）が充実しており内容は確定済み |
| `docs/ja/` 3 ファイル | M | Low | `docs/en/` の翻訳・並行作成可能 |
| PHP 7.4・8.1 最終確認 | S | Low | Docker 環境整備済み・既存テストをそのまま実行 |

**総合工数**: M（3〜5日）
**総合リスク**: Low（ロジック変更なし・参照情報充実・実行環境整備済み）

---

## 5. 設計フェーズへの推奨事項

### 推奨アプローチ

**Option B（推奨）**をベースに、tasks.md でのタスク分割は **Option C** の構成を採用する。

### 設計フェーズで決定すべき事項

1. **`examples/main.php` のサンプル出力形式**:
   - 各 API の呼び出し結果を `echo` でどのように表示するか（フォーマット）
   - CaoCsv オンラインモードのサンプルは `@comment` で注意書きを付けるか

2. **`examples/config/excluded_dates.yaml` の内容**:
   - サンプル用の除外日付をどの月日にするか（8/15 と 12/31 を使う想定）

3. **docs ドキュメントの構成**:
   - `api-spec.md` に全シグネチャを載せるか、または BusinessCalendar・各プロバイダー・型別にセクション分けするか
   - `usage.md` と `api-spec.md` の内容重複をどう整理するか

4. **CHANGELOG.md の形式**:
   - Keep a Changelog 形式か、独自形式か

5. **`composer.json scripts` の追加可否**:
   - `"test": "phpunit"` 等を追加するかどうか（要件定義書には含まれていないが利便性向上につながる）
   - 追加する場合は設計フェーズで判断し、要件変更として扱う

### 引き継ぎ事項（Research Needed）

- `google/apiclient` APIキー取得手順の最新情報（docs/providers.md 執筆に必要）
  - ドキュメント内の URL が現在も有効かどうかは確認済みでない
- `holiday-jp/holiday_jp` の最終データ更新年（「2020年」の出典を再確認）
  - `docs/providers.md` に記載する注意事項として使用
