# ギャップ分析: step2-holidayjp

## 分析サマリー

- **既存資産は Step 1 で完全整備済み**。インターフェース・値オブジェクト・例外型・`BusinessCalendar` のコンストラクタ＋`isBusinessDay()` はそのまま活用できる。
- **`holiday-jp/holiday_jp` は既に `require-dev ^2.3` に追加済み**（vendor インストール済み）。ライブラリ導入作業は不要。
- **主なギャップは「新規ファイル 1 件」＋「`BusinessCalendar` への 4 メソッド追加」**。アーキテクチャ上の障壁は低い。
- **`holiday-jp/holiday_jp` API は `DateTime`（mutable）受け取り**。`DateTimeImmutable` から変換する薄いラッパーが必要。
- **推奨アプローチはオプション C（ハイブリッド）**: `BusinessCalendar` を拡張 ＋ 新規の `Providers/HolidayJp/Provider.php` を作成。

---

## 1. 現在の実装状態

### 1.1 既存ファイルマップ

| ファイル | 状態 | 備考 |
|---------|------|------|
| `src/HolidayProvider.php` | ✅ 実装済み | インターフェース完成 |
| `src/Holiday.php` | ✅ 実装済み | 値オブジェクト（`getDate()` / `getName()`） |
| `src/MonthDay.php` | ✅ 実装済み | 値オブジェクト（`matches()` 実装済み） |
| `src/BusinessCalendar.php` | ⚠️ 一部実装 | コンストラクタ・`isBusinessDay()` のみ。残り 4 API は未実装 |
| `src/Config.php` | ✅ 実装済み | YAML/JSON 読み込み完成 |
| `src/Exception/HeijituException.php` | ✅ 実装済み | マーカー IF |
| `src/Exception/ConfigException.php` | ✅ 実装済み | `\RuntimeException` 継承 |
| `src/Exception/ProviderException.php` | ✅ 実装済み | `\RuntimeException` 継承 |
| `src/Providers/HolidayJp/Provider.php` | ❌ 未作成 | Step 2 で新規作成 |
| `tests/BusinessCalendarTest.php` | ⚠️ 一部 | `isBusinessDay()` のテストのみ。残り 4 API は未追記 |
| `tests/Providers/HolidayJp/ProviderTest.php` | ❌ 未作成 | Step 2 で新規作成 |

### 1.2 `composer.json` 状態

```jsonc
"require-dev": {
    "phpunit/phpunit": "^9.6",
    "symfony/yaml": "^5.4",
    "holiday-jp/holiday_jp": "^2.3"   // ← 既に追加済み・vendor インストール済み
}
```

追加の Composer 作業は不要。

### 1.3 コーディング規約（Step 1 から抽出）

- `declare(strict_types=1)` が全ファイルに付く
- PHP 7.4 構文（union 型・enum・named argument は使わない）
- `final class` で値オブジェクト・プロバイダーを定義
- PHPDoc で `@return Type[]` を明示
- テスト: `// Given / When / Then` コメント構造
- テストの namespace は `Heijitu\Tests\` + サブパスをそのまま使う（例: `Heijitu\Tests\Providers\HolidayJp`）
- モックプロバイダーは無名クラスで定義

---

## 2. 要件フィージビリティ分析

### 2.1 `holiday-jp/holiday_jp` API 調査

**`HolidayJp` クラスの公開メソッド（vendor から確認済み）:**

```php
// src/HolidayJp.php（ライブラリ側）
class HolidayJp {
    // DateTime（mutable）を受け取る点に注意
    public static function isHoliday(DateTime $date): bool
    public static function between(DateTime $start, DateTime $last): array
}
```

**`between()` の戻り値構造:**

```php
[
    [
        'date'    => DateTime $d,   // DateTime インスタンス（mutable）
        'week'    => '木',
        'week_en' => 'Thursday',
        'name'    => '元日',
        'name_en' => "New Year's Day",
    ],
    // ...
]
```

**`Holidays::$holidays` の構造:**

```php
// キー: 'Y-m-d' 文字列
public static $holidays = [
    '1970-01-01' => ['date' => '1970-01-01', 'week' => '木', 'name' => '元日', ...],
    // ...
];
```

データは生成時（2020-12-06）に日付昇順で格納されている。`between()` は `foreach` で順番に走査するため、**結果は日付昇順で返る**（設計どおり）。ただし、要件の確実性のため昇順ソートを明示しておく価値がある。

### 2.2 ギャップ一覧

| 要件 | ギャップ種別 | 対応方針 |
|------|------------|---------|
| `HolidayJp\Provider::isHoliday()` | 変換必要 | `DateTimeImmutable` → `new DateTime($t->format('Y-m-d'))` に変換して `HolidayJp::isHoliday()` に渡す |
| `HolidayJp\Provider::holidayName()` | 変換必要 | `Holidays::$holidays[$key]['name']` を直接参照（`between()` より効率的） |
| `HolidayJp\Provider::holidaysBetween()` | 変換必要 | `HolidayJp::between()` の結果を `Holiday[]` に変換・昇順ソート確認 |
| `BusinessCalendar::nextBusinessDay()` | 新規追加 | `$from->modify('+1 day')` から `isBusinessDay()` を使ってループ |
| `BusinessCalendar::firstBusinessDayOfMonth()` | 新規追加 | 月初から `isBusinessDay()` をループ。内部日付生成にデフォルト TZ を使用 |
| `BusinessCalendar::firstBusinessDaysOfYear()` | 新規追加 | `firstBusinessDayOfMonth()` を 12 回呼び出す |
| `BusinessCalendar::holidays()` | 新規追加 | `$this->provider->holidaysBetween()` を返すだけ |
| 依存未導入時の `ProviderException` | オプション | `class_exists(\HolidayJp\HolidayJp::class)` を確認して投げる |
| タイムゾーン: 内部生成日付 | 制約 | `date_default_timezone_get()` の空文字判定 → `'Asia/Tokyo'` フォールバック |

### 2.3 タイムゾーン処理の詳細

`nextBusinessDay` では `$from->modify('+1 day')` が `$from` 自身の TZ を引き継ぐ（追加処理不要）。

`firstBusinessDayOfMonth` では年・月から日付を内部生成するため、デフォルト TZ を取得して `DateTimeZone` を指定する必要がある:

```php
$tz = date_default_timezone_get() ?: 'Asia/Tokyo';
$date = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), new \DateTimeZone($tz));
```

### 2.4 `DateTime` / `DateTimeImmutable` 変換

`holiday-jp/holiday_jp` は `DateTime`（mutable）を要求する。変換パターン:

```php
// DateTimeImmutable → DateTime
$mutable = \DateTime::createFromFormat('Y-m-d', $t->format('Y-m-d'));
// または
$mutable = new \DateTime($t->format('Y-m-d'));
```

---

## 3. 実装アプローチ評価

### オプション A: `BusinessCalendar` のみ拡張（プロバイダーなし）

- ❌ 前提: `HolidayJp\Provider` は必須要件。このオプションは選択不可。

### オプション B: 新規ファイルのみ作成

- `BusinessCalendar` への 4 メソッド追加は「拡張」であり「新規ファイル作成」ではない。
- ❌ `BusinessCalendar.php` への変更を避けることはできない。

### オプション C: ハイブリッド（推奨）

**既存ファイルを拡張:**
- `src/BusinessCalendar.php` — 4 メソッドを追加（`nextBusinessDay` / `firstBusinessDayOfMonth` / `firstBusinessDaysOfYear` / `holidays`）
- `tests/BusinessCalendarTest.php` — 新テストを追記（ユーザー承認が必要）

**新規ファイルを作成:**
- `src/Providers/HolidayJp/Provider.php` — `HolidayProvider` 実装
- `tests/Providers/HolidayJp/ProviderTest.php` — プロバイダーのユニットテスト

**根拠:**
- `BusinessCalendar` は責務（営業日計算）に集中しており、4 メソッドの追加はその延長。既存インターフェースを壊さない。
- `HolidayJp\Provider` は独自の依存（`holiday-jp/holiday_jp`）と責務を持つため、別ファイルが適切。
- テスト追記には **ユーザー承認が必要**（開発ルールに従う）。

**トレードオフ:**
- ✅ Step 1 の実装パターンを一貫して踏襲する
- ✅ ファイル構成が go-heijitu の `providers/holidayjp/provider.go` に対応
- ✅ `BusinessCalendar` への変更量は小さく、既存テストに影響しない
- ❌ `BusinessCalendar.php` が若干大きくなる（許容範囲）

---

## 4. 実装複雑度とリスク

| 項目 | 見積もり | 根拠 |
|------|---------|------|
| **実装工数** | **S（1〜3 日）** | アルゴリズムは go-heijitu から確定済み。ライブラリ API 変換は薄いラッパー程度 |
| **リスク** | **Low** | 全依存・インターフェース・パターンが確定済み。外部 API 呼び出しなし（埋め込みデータ）。タイムゾーン処理は既知パターン |

**リスク要因（軽微）:**
- `holiday-jp/holiday_jp` のデータが 2020 年で止まっているため、2021 年以降の祝日はカバーされない → 既知の弱点（go-heijitu と同等）。テストは 2020 年以前の確定データで記述することで回避可能。
- `between()` 戻り値のソート順：静的データの挿入順に依存している。要件上は「昇順」を保証するため、Provider 実装で `usort()` を追加しておくのが安全。

---

## 5. 設計フェーズへの推奨事項

1. **推奨アプローチ**: オプション C（ハイブリッド）を採用。
2. **ディレクトリ構成**: `src/Providers/HolidayJp/Provider.php` を新規作成。`Heijitu\Providers\HolidayJp\Provider` 名前空間。
3. **`DateTime` 変換**: `new \DateTime($t->format('Y-m-d'))` パターンを `HolidayJp\Provider` 内に閉じ込める。
4. **`holidaysBetween()` のソート**: `between()` の結果は静的データの挿入順（昇順）だが、確実性のため `usort()` でソートを明示する。
5. **TZ フォールバック**: `firstBusinessDayOfMonth` の内部日付生成で `date_default_timezone_get() ?: 'Asia/Tokyo'` を使用。
6. **テスト日付の選択**: 2020 年以前の既知祝日（例: `2020-01-01` 元日）を使用し、ライブラリのデータ範囲外を避ける。
7. **依存未導入検出**: `class_exists(\HolidayJp\HolidayJp::class)` をコンストラクタで確認して `ProviderException` を投げる実装を「可能なら実施」として設計に含める。

**設計フェーズへのキャリーオーバー不要の確認済み項目:**
- `HolidayProvider` インターフェース設計 → 確定済み（Step 1）
- 例外設計 → 確定済み（Step 1）
- `composer.json` 更新 → 既に完了
