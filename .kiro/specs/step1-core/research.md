# Gap Analysis: Step 1 — プロジェクト初期化 + コア実装

## 1. 現状調査

### 既存コードベースの状態
php-heijitu は **完全な greenfield プロジェクト**。存在するファイル:

```
.claude/          # スキル定義（変更不要）
.gitignore
CLAUDE.md
.kiro/specs/initial-planning/planning/    # 設計・計画資料（5ファイル）
README.md
```

`src/`・`tests/`・`composer.json`・`phpunit.xml`・`docker/` は一切存在しない。Step 1 で **すべて新規作成** する。

### 参照実装（go-heijitu Step 1）のファイル構成

| Go ファイル | 責務 | PHP 版対応 |
|-----------|------|---------|
| `monthday.go` | `MonthDay` struct + `Matches()` | `src/MonthDay.php` |
| `holiday.go` | `Holiday` struct | `src/Holiday.php` |
| `provider.go` | `HolidayProvider` interface | `src/HolidayProvider.php` |
| `option.go` | `Option` 関数型 + `WithExcludedDates`/`WithConfig` | `BusinessCalendar` コンストラクタ＋`Config.php` に統合 |
| `config.go` | `loadConfig()` (YAML/JSON 読み込み) | `src/Config.php` |
| `calendar.go` | `BusinessCalendar` 構造体 + 全 API | `src/BusinessCalendar.php` |
| 例外型（なし） | Go は `(T, error)` で表現 | `src/Exception/HeijituException.php` 他 3 ファイル |

---

## 2. 要件充足のための技術的分析

### 要件→PHP 実装の対応

| 要件 | 技術的ニーズ | 現在の状態 | 備考 |
|------|------------|---------|------|
| Req 1: パッケージ構成 | `composer.json` | **Missing** | `require`/`suggest`/`require-dev` の分離が重要 |
| Req 2: 7.4/8.1 互換 | 構文制約・deprecation 対策 | **Missing** | 暗黙 nullable 型宣言（`Type = null`→`?Type`）が 8.1 で非推奨 |
| Req 3: MonthDay | `final class MonthDay` + `matches()` | **Missing** | `format('n')` / `format('j')` で月・日取得 |
| Req 4: Holiday | `final class Holiday` | **Missing** | getter のみ |
| Req 5: HolidayProvider IF | `interface HolidayProvider` | **Missing** | ctx 削除・例外に変換 |
| Req 6: BusinessCalendar 構築 | コンストラクタ + Config ローダー | **Missing** | Go の関数オプションを 2 つの手段に分解 |
| Req 7: isBusinessDay | `isBusinessDay(DateTimeImmutable, MonthDay ...)` | **Missing** | 曜日判定は `format('N')` が 6・7 で土日 |
| Req 8: 設定ファイル | `Config::loadExcludedDates($path)` | **Missing** | `symfony/yaml` は `require-dev` 兼 `suggest` |
| Req 9: 例外設計 | 3 クラス（IF + 2 例外クラス） | **Missing** | — |

### PHP 固有の注意点（Go との差分）

#### 曜日判定
- Go: `time.Saturday == 6`, `time.Sunday == 0`
- PHP: `format('N')` は ISO 8601（Mon=1〜Sun=7）。**土=6, 日=7**。

#### nullable 型宣言（8.1 非推奨対策）
```php
// Bad (PHP 8.1 deprecation warning):
public function foo(Foo $arg = null): void {}

// Good (7.4〜8.1 両方 OK):
public function foo(?Foo $arg = null): void {}
```
プロパティ型宣言も同様。

#### 可変長引数
PHP 7.4 でも `MonthDay ...$extra` は使用可能（`func(MonthDay...) {}` に相当）。

#### Config ローダーの設計
Go の `WithConfig()` は `(Option, error)` を返す（呼び出し元でエラー処理）。PHP 版では `Config::loadExcludedDates($path): array` として `MonthDay[]` を返し、失敗時は `ConfigException` を throw する。利用者は `array_merge` でコンストラクタ引数と結合する。

#### PHPUnit 9.6 のモック
```php
// PHPUnit 9.6 でのモック作成
$mock = $this->createMock(HolidayProvider::class);
$mock->method('isHoliday')->willReturn(false);
```
インラインモック（`getMockBuilder`）・匿名クラスの両方が使える。シンプルさのため無名クラスで `HolidayProvider` を実装するアプローチも有効。

#### PHP 7.4 で使えない構文（禁止一覧）
| 禁止 | 代替 |
|------|------|
| Union 型 `int\|string` | PHPDoc `@param int\|string` |
| Enum | クラス定数 |
| コンストラクタプロモーション `public function __construct(private int $x)` | 通常の `private $x; __construct(int $x) { $this->x = $x; }` |
| 名前付き引数 `foo(x: 1)` | 位置引数 |
| `match` 式 | `switch` |
| `readonly` プロパティ | `private` + getter のみ |

---

## 3. 実装アプローチの評価

### Option B: 新規コンポーネント全作成（推奨）

完全 greenfield であるため、Option A（既存拡張）・Option C（ハイブリッド）は選択肢に上がらない。すべて新規作成一択。

**ファイル一覧（作成順推奨）**:
```
composer.json
phpunit.xml
.gitignore（vendor/ 等の追加確認）
src/
  Exception/
    HeijituException.php    # interface（マーカー）
    ConfigException.php     # extends \RuntimeException
    ProviderException.php   # extends \RuntimeException
  MonthDay.php              # final class
  Holiday.php               # final class
  HolidayProvider.php       # interface
  Config.php                # static factory loadExcludedDates()
  BusinessCalendar.php      # final class
tests/
  MonthDayTest.php
  ConfigTest.php
  BusinessCalendarTest.php
  testdata/
    config.yaml
    config.json
docker/                     # Dockerfile + compose.yaml（dev-environment.md 参照）
```

**作成順の依存関係**:
```
Exception/* (依存なし)
  ↓
MonthDay, Holiday (依存なし、並行可)
  ↓
HolidayProvider (Holiday に依存)
  ↓
Config (MonthDay, ConfigException に依存)
  ↓
BusinessCalendar (HolidayProvider, MonthDay, ProviderException に依存)
  ↓
テスト（上記すべてに依存）
```

**トレードオフ**:
- ✅ go-heijitu の設計をほぼ 1:1 で移植できる
- ✅ 各クラスの責務が明確（1 ファイル 1 クラス）
- ✅ テストが独立して書きやすい
- ❌ ファイル数が多い（9 ファイル + テスト 3 ファイル）→ ただし go-heijitu と同規模

---

## 4. 実装複雑度とリスク評価

- **Effort: M（3〜7 日）**
  - go-heijitu の実装が完全な参照として使える
  - PHP 固有の差分（曜日判定・nullable 型・例外設計）は小さい
  - Docker 環境セットアップが Step 1 の前提として追加される

- **Risk: 低〜中**
  - **低リスク**: コアロジック（MonthDay・Holiday・HolidayProvider・isBusinessDay）は go-heijitu をほぼ直訳できる
  - **中リスク**: PHP 7.4/8.1 両対応。特に **8.1 での deprecation 警告の完全排除**が地道な確認作業を要する。`symphony/yaml ^5.4` の PHP 8.1 動作は既知だが、`composer install` 時の依存解決を実機確認する必要がある

---

## 5. 設計フェーズへの引き継ぎ事項

### 確認済み事項（設計フェーズで実装詳細に落とすもの）

1. **`Config::loadExcludedDates` の静的ファクトリ方式** — Go の `WithConfig` は `(Option, error)` を返すが、PHP では `MonthDay[]` を返す静的ファクトリが自然（exceptions で error 伝播）
2. **`BusinessCalendar` は `final class`** — go-heijitu の `BusinessCalendar` は継承を想定しないため
3. **テスト内モックの実装方法** — 匿名クラスか `createMock()` か（どちらでも成立するが統一したい）
4. **`phpunit.xml` の `bootstrap` 設定** — `vendor/autoload.php` を指定

### Research Needed（設計フェーズで要調査）

- **Docker 環境の事前構築**: Docker 環境が未作成のため `composer install` の動作確認が取れていない。Step 1 着手前に `docker/` を用意する必要がある（`.kiro/specs/initial-planning/planning/dev-environment.md` に設計あり）
- **`symfony/yaml ^5.4` の `require-dev` 追加後の PHP 7.4 動作**: `composer install` が問題なく通ることを実機確認
- **`phpunit/phpunit ^9.6` の PHP 7.4/8.1 両バージョンでのテスト実行**: Docker の `php74`/`php81` 両サービスで `vendor/bin/phpunit` が通ることを確認
