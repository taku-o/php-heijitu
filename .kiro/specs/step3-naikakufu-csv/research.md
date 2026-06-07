# ギャップ分析レポート: Step 3 — 内閣府CSVプロバイダー

## 分析スコープ

**対象フィーチャー**: `step3-naikakufu-csv`（`CaoCsv\Provider` 実装）
**分析手法**: 既存コードベース調査（軽量調査）
**分析日**: 2026-06-07

---

## 1. 現状調査

### 1.1 既存コードベースの資産

| ファイル | 用途 | CaoCsv 実装への関連性 |
|---------|------|----------------------|
| `src/HolidayProvider.php` | `HolidayProvider` インターフェース | 実装すべき 3 メソッドのコントラクト定義 |
| `src/Providers/HolidayJp/Provider.php` | 既存プロバイダー実装 | 実装パターンの参照元 |
| `src/Exception/ProviderException.php` | プロバイダー例外型 | 依存未導入・データ取得失敗時に使用 |
| `src/Holiday.php` | 祝日値オブジェクト | `holidaysBetween` 戻り値として構築 |
| `tests/Providers/HolidayJp/ProviderTest.php` | 既存プロバイダーテスト | テスト構造・カバレッジ方針の参照元 |

### 1.2 既存 HolidayProvider インターフェースのコントラクト

```
isHoliday(DateTimeImmutable $t): bool
  - 祝日なら true、非祝日なら false
  - 失敗時は ProviderException を throw

holidayName(DateTimeImmutable $t): string
  - 祝日名を返す、非祝日なら空文字 "" (例外にしない)
  - 失敗時は ProviderException を throw

holidaysBetween(DateTimeImmutable $from, DateTimeImmutable $to): Holiday[]
  - from〜to 両端含む祝日を日付昇順で返す
  - from > to なら空配列
  - 失敗時は ProviderException を throw
```

### 1.3 既存プロバイダーの実装パターン（HolidayJp/Provider.php）

- **名前空間**: `Heijitu\Providers\{ProviderName}`、クラス名 `Provider`、`final class` 宣言
- **コンストラクタ**: 依存ライブラリ／拡張の存在確認（`class_exists` / `extension_loaded`）→ 未導入なら `ProviderException`
- **日付型変換**: `DateTimeImmutable` → `DateTime`（外部ライブラリが `DateTime` を要求する場合のみ変換）
- **ソート**: `usort()` + スペースシップ演算子 `<=>` で昇順ソート
- **Holiday 構築**: `new Holiday(DateTimeImmutable $date, string $name)`
- **`holidaysBetween` 戻り値**: `Holiday[]` 型の配列を返す

### 1.4 テスト規則

- **配置**: `tests/Providers/{ProviderName}/ProviderTest.php`
- **setUp()**: プロバイダーインスタンスを `setUp()` で初期化
- **カバレッジ**: インターフェース実装確認・各メソッドの正常系・境界値（両端含む・from > to・空範囲）
- **グループ分離**: ネットワークアクセスを伴うテストは `@group integration` で分離（通常 `phpunit` では実行しない）

---

## 2. 要件実現可能性分析

### 2.1 技術要件マッピング

| 要件 | 必要な技術 | 現在のコードベースでの状態 | タグ |
|------|-----------|--------------------------|------|
| Req 1.1 HolidayProvider 実装 | `src/HolidayProvider.php` インターフェース | 実装可能・HolidayJp が参照パターン提供 | 既存パターン利用 |
| Req 1.2 ローカルCSV読み込み | PHP 標準 `file_get_contents($path)` | PHP 標準機能のみ・実装可能 | 実装可能 |
| Req 1.3 オンライン取得 | `file_get_contents(URL)` または cURL | PHP 標準機能のみ・実装可能 | 要設計判断 |
| Req 1.4 Shift_JIS デコード | `mb_convert_encoding(, 'UTF-8', 'SJIS-win')` + `str_getcsv` | `ext-mbstring` に依存（`suggest` 済み） | 要依存検出 |
| Req 1.5/1.6 エラーハンドリング | `ProviderException` | 既存例外型で対応可能 | 既存パターン利用 |
| Req 2.1–2.4 点照合（isHoliday/holidayName） | 内部データへの日付キー照合 | 実装可能・CSV パース後に配列保持 | 実装可能 |
| Req 2.5–2.6 holidaysBetween | 範囲フィルタ + 昇順ソート | usort パターン既存（HolidayJp 参照） | 既存パターン利用 |
| Req 3.1 mbstring 依存検出 | `extension_loaded('mbstring')` | 既存 `class_exists` パターンと同一構造 | 既存パターン利用 |
| Req 4.1–4.3 テスト + integration 分離 | PHPUnit `@group integration` | phpunit.xml の設定確認が必要 | 調査済み（設定可能） |
| Req 4.4 PHP 7.4/8.1 両環境通過 | Docker 両バージョンでの実行 | Step 2 と同一手順（確立済み） | 確立済み |

### 2.2 ギャップ・不明点

**G-1: オンライン取得手段の選択**
- `file_get_contents(URL)` は `allow_url_fopen=On` が必要。`php.ini` でデフォルト ON だが、環境によって無効化されていることがある。
- cURL は `allow_url_fopen` に依存しないが、`ext-curl` が必要。多くの環境で利用可能。
- `file_get_contents` を第一選択とし、失敗時の例外制御が必要か（内閣府URL到達不能時の振る舞い）。
- **設計フェーズでの判断事項**: `file_get_contents` のみか、cURL フォールバックを追加するか。要件には「追加 Composer 依存ゼロ」のみ明記。

**G-2: テストフィクスチャ（Shift_JIS CSV）の作成**
- `tests/Providers/CaoCsv/testdata/syukujitsu_test.csv` を Shift_JIS エンコードで作成する必要がある。
- 最小限の内容（ヘッダー行 + 既知の祝日数件）でよい。
- ビルド・コミット時に文字コードが壊れないよう注意が必要（git の `core.autocrlf` や `text` 属性）。

**G-3: 内部データ保持構造**
- CSV パース後のデータをどの構造で保持するか（例: `['YYYY-MM-DD' => '祝日名']` の連想配列）。
- 点照合（isHoliday/holidayName）のパフォーマンスに影響するが、要件では性能要件未定義。
- **設計フェーズでの決定事項**: 連想配列か `Holiday[]` か。

**G-4: `csvPath` の渡し方（コンストラクタシグネチャ）**
- `design.md`（計画資料）では `new Provider(['csvPath' => '...'])` の形（配列オプション）が示されている。
- HolidayJp は引数なしの `new Provider()` だった。
- **設計フェーズでの確定事項**: 配列オプション方式か、直接引数方式か。

**G-5: CSVフォーマット前提の明示**
- 内閣府CSVは「YYYY/MM/DD,祝日名」の2列・1行目ヘッダー・Shift_JIS。
- 日付フォーマットが `YYYY/MM/DD` → `DateTimeImmutable` への変換が必要（`DateTimeImmutable::createFromFormat('Y/m/d', ...)`）。
- フォーマット変更時のエラーハンドリング（不正な日付行のスキップか例外か）を設計で決める。

---

## 3. 実装アプローチ評価

### Option A: 既存コンポーネントの拡張
**該当なし**: CaoCsv は完全に新しいプロバイダーで、既存のプロバイダーを拡張する必然性がない。

### Option B: 新規コンポーネント作成（推奨）

**新規作成が適切な理由**:
- `CaoCsv\Provider` は `HolidayJp\Provider` と同じ `HolidayProvider` インターフェースを実装するが、データソースが完全に異なる（ネットワーク or ローカルファイル vs 埋め込みデータ）。
- 責務が明確で独立している（CSV の読み込み・デコード・パース・照合は CaoCsv のみの責任）。
- 既存プロバイダーへの影響ゼロ。

**新規作成ファイル**:
- `src/Providers/CaoCsv/Provider.php` — プロバイダー本体
- `tests/Providers/CaoCsv/ProviderTest.php` — ユニットテスト
- `tests/Providers/CaoCsv/testdata/syukujitsu_test.csv` — Shift_JIS テストフィクスチャ

**既存との統合点**:
- `HolidayProvider` インターフェースの実装
- `ProviderException` の使用（依存検出・取得失敗・パース失敗）
- `Holiday` 値オブジェクトの構築と返却
- `usort()` 昇順ソートパターンの踏襲

**トレードオフ**:
- ✅ `HolidayJp\Provider` への影響ゼロ
- ✅ 既存パターンと統一性が取れる
- ✅ テストが独立して書ける
- ❌ 新規ファイルとテストデータが必要

### Option C: ハイブリッドアプローチ
**不要**: 単純な新規コンポーネント追加で要件を満たせる。

---

## 4. 実装複雑度とリスク

| 観点 | 評価 | 理由 |
|------|------|------|
| **工数** | S（1〜3日） | 既存パターン踏襲・PHP標準機能のみ・依存ゼロ。主要な実装パターンはすべて確立済み。 |
| **リスク** | Low | 技術的未知要素なし。`file_get_contents` / `mb_convert_encoding` / `str_getcsv` は PHP 7.4 から利用可能。唯一の懸念は Shift_JIS テストフィクスチャの文字コード保持（git 設定で対処可能）。 |

---

## 5. 設計フェーズへの推奨事項

### 優先決定事項

1. **コンストラクタシグネチャ**: 配列オプション `['csvPath' => '...']` vs 直接引数 `string $csvPath = ''`
   - 計画資料は配列オプション方式を示す。PHP の慣習的には直接引数も一般的。
   - 設計で確定させる。

2. **オンライン取得実装**: `file_get_contents` のみ vs cURL フォールバック
   - `allow_url_fopen=Off` 環境での動作を保証するなら cURL が安全。
   - 要件には「PHP標準関数のみ」とあり、どちらも標準関数。
   - 設計で選択し、エラーハンドリング方針を決める。

3. **CSV パース後のデータ保持構造**
   - 連想配列 `['YYYY-MM-DD' => '祝日名']` が点照合・範囲フィルタの両方に対応しやすい。

4. **Shift_JIS フィクスチャの作成方法**
   - `tests/Providers/CaoCsv/testdata/syukujitsu_test.csv` を Shift_JIS で用意する具体的な手順を設計で示す。

### 繰り越し調査事項（設計フェーズで解決）

- `allow_url_fopen` の PHP デフォルト値と Docker 環境（php74/php81 サービス）での設定状態
- 内閣府 CSV の実際の行構造（ヘッダー行の正確な内容・区切り文字の確認）
- git の `core.autocrlf` / `.gitattributes` によるバイナリ扱い設定の有無（Shift_JIS ファイル保護）
