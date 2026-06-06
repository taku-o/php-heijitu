# 設計案: php-heijitu（日本営業日計算ライブラリ・PHP版）

go-heijitu を PHP に移植するための設計案。go-heijitu の機能・仕様を等価に再現することを第一目標とし、Go 固有の概念は PHP の慣習に沿って置き換える。

> **論点は全てユーザー確定済み**（A-3 PHP 7.4基準・8.1互換 / B-1 holiday_jp / C-3 コンストラクタ引数＋設定ローダー / C-5 例外設計 / D-1 CaoCsv は PHP 標準関数 / D-4 apiclient / E-2 タイムゾーン / E-3 ドキュメント en/ja / G 配布 / H 依存方針）。実装中に確認する「可能なら実施」の留意点は、apiclient の PHP 8.1 実機検証・依存未導入時の親切な例外のみ（decisions.md 参照）。

---

## 1. 移植の基本方針

1. **機能等価**: go-heijitu の公開 API・営業日判定ロジック・プロバイダー構成を過不足なく再現する。go-heijitu に無い機能は追加しない（YAGNI）。
2. **PHP 慣習への適応**: Go の `(value, error)` 多値返却・`context.Context`・関数オプションは、PHP の例外・通常引数に置き換える。
3. **依存の分離**: go-heijitu 同様、コアは外部依存を最小化し、外部ライブラリ依存は各プロバイダーと設定読み込みに閉じ込める。
4. **PHP 7.4 基準・8.1 互換**（確定）: コードは 7.4 構文で書き、8.0/8.1 構文（union型・enum・コンストラクタプロモーション・名前付き引数・match）を使わない。さらに 8.1 で deprecation 警告も出ないようにする（「暗黙的に null 許容な型宣言」など 8.x で非推奨化された書き方を避ける）。

---

## 2. Go → PHP の概念対応

| go-heijitu | php-heijitu（推奨案） | 補足 |
|-----------|---------------------|------|
| `time.Time` | `DateTimeImmutable` | 不変。タイムゾーンは呼び出し元が統一（JST想定） |
| `time.Month`（型） | `int`（1〜12） | PHP に Month 型は無い |
| `context.Context`（第一引数） | **廃止**（確定 C-1） | PHP に非同期キャンセルの慣習なし。HTTP のタイムアウトはクライアント設定で対応 |
| `(T, error)` 多値返却 | 戻り値 `T` ＋ **例外 throw**（確定 C-2） | Go の error 伝播を PHP の例外伝播に置換 |
| `[]Holiday` / `[]time.Time` | `Holiday[]` / `DateTimeImmutable[]`（配列） | 型は PHPDoc で表現 |
| `interface HolidayProvider` | `interface HolidayProvider` | そのまま |
| 関数オプション `Option func(*BC)` | **コンストラクタ引数 ＋ 設定ローダー**（確定 C-3） | PHP に関数オプションの慣習は薄い |
| `struct MonthDay` / `Holiday` | 値オブジェクトクラス（イミュータブル） | コンストラクタで設定、getter で参照 |
| `panic`（nil プロバイダー） | **型宣言で排除**（確定 C-4） | `HolidayProvider $provider` の非 null 型宣言で防御。go の panic 相当を型で達成 |
| パッケージ `providers/<name>` | 名前空間 `Heijitu\Providers\<Name>` | PSR-4 |

---

## 3. パッケージ構成・名前空間

- **Composer パッケージ名**: `taku-o/php-heijitu`（go-heijitu の `github.com/taku-o/go-heijitu` を踏襲）
- **ルート名前空間**: `Heijitu\`（PSR-4、`src/` にマップ）

```
php-heijitu/
├── composer.json
├── src/
│   ├── BusinessCalendar.php        # 本体・全公開API
│   ├── HolidayProvider.php         # インターフェース
│   ├── Holiday.php                 # 値オブジェクト（date, name）
│   ├── MonthDay.php                # 値オブジェクト（month, day）+ matches()
│   ├── Config.php                  # 設定ファイル読み込み（YAML/JSON判別）
│   ├── Exception/                  # 例外型（HeijituException / ConfigException / ProviderException）
│   │   └── ...
│   └── Providers/
│       ├── HolidayJp/Provider.php       # 埋め込みデータ（holiday-jp/holiday_jp）
│       ├── CaoCsv/Provider.php          # 内閣府CSV（自前実装）
│       └── GoogleCalendar/Provider.php  # Google Calendar API
├── examples/
│   └── main.php                    # 利用サンプル
├── tests/
│   └── ...                         # PHPUnit
└── docs/
    ├── ja/ ...                     # 日本語ドキュメント
    └── en/ ...                     # 英語ドキュメント
```

（Docker 開発環境 `docker/` は dev-environment.md を参照）

go-heijitu の「1ファイル1責務」「コアは標準機能中心」「外部依存はプロバイダーに閉じ込める」を踏襲する。

### composer.json 設計（依存方針 H・配布方法 G を反映）

コア `require` は最小、プロバイダー依存は `suggest`（decisions.md H）。利用者は使うプロバイダーの依存だけを自分で追加する。

```jsonc
{
  "name": "taku-o/php-heijitu",
  "type": "library",
  "license": "MIT",
  "require": {
    "php": "^7.4 || ^8.0"
  },
  "require-dev": {
    "phpunit/phpunit": "^9.6",
    "symfony/yaml": "^5.4",
    "holiday-jp/holiday_jp": "...",
    "google/apiclient": "^2.16"
  },
  "suggest": {
    "ext-mbstring": "CaoCsv プロバイダーの Shift_JIS デコードに必要",
    "symfony/yaml": "YAML 設定ファイルを使う場合に必要（^5.4）。JSON のみなら不要",
    "holiday-jp/holiday_jp": "HolidayJp プロバイダー（埋め込み祝日データ）を使う場合に必要",
    "google/apiclient": "GoogleCalendar プロバイダーを使う場合に必要（^2.16）"
  },
  "autoload":     { "psr-4": { "Heijitu\\":        "src/" } },
  "autoload-dev": { "psr-4": { "Heijitu\\Tests\\": "tests/" } }
}
```

- `new` したプロバイダークラスだけが PSR-4 でロードされるため、未使用プロバイダーの `suggest` 依存が無くてもエラーにならない（go-heijitu の「使うものだけ取り込む」を再現）。
- `ext-mbstring`（caoCsv 用）は `suggest`（確定 H）。
- 依存ライブラリ／拡張が未導入のままプロバイダーを使った場合は（可能なら）`class_exists()` / `extension_loaded()` で検出し、`composer require ...` を案内する親切な例外を投げる（確定 H）。例外型は C-5 の `ProviderException` / `ConfigException` を流用し、新たな例外クラスは追加しない。

### 配布方法（G）

GitHub の VCS 配布。Packagist 登録は行わない。利用者は `repositories` に GitHub を登録し、`dev-branch` ブランチを `dev-dev-branch` として取り込む。

```jsonc
// 利用者側 composer.json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/taku-o/php-heijitu" }
  ],
  "require": {
    "taku-o/php-heijitu": "dev-dev-branch",
    "holiday-jp/holiday_jp": "..."
  }
}
```

---

## 4. 型定義

### MonthDay（値オブジェクト）

年をまたいで有効な月日。会社独自休業日の指定に使用。

```php
namespace Heijitu;

final class MonthDay
{
    private $month; // int 1..12
    private $day;   // int 1..31

    public function __construct(int $month, int $day) { /* バリデーションなし（go-heijitu踏襲） */ }
    public function getMonth(): int { ... }
    public function getDay(): int { ... }

    // t の月・日が一致すれば true（年は無視）
    public function matches(DateTimeImmutable $t): bool { ... }
}
```

- go-heijitu の `MonthDay` はバリデーションしない（2/30 等はどの日付にも一致しない）。同挙動とする。

### Holiday（値オブジェクト）

```php
namespace Heijitu;

final class Holiday
{
    private $date; // DateTimeImmutable
    private $name; // string

    public function __construct(DateTimeImmutable $date, string $name) { ... }
    public function getDate(): DateTimeImmutable { ... }
    public function getName(): string { ... }
}
```

### Config（設定ファイル内容）

```php
// excluded_dates: MonthDay[] を保持
```

---

## 5. HolidayProvider インターフェース

```php
namespace Heijitu;

interface HolidayProvider
{
    // 指定日が祝日か。失敗時は例外を throw（握りつぶさない）
    public function isHoliday(DateTimeImmutable $t): bool;

    // 指定日の祝日名。非祝日なら空文字 ""（例外にしない）
    public function holidayName(DateTimeImmutable $t): string;

    // from〜to（両端含む）の祝日リストを日付昇順で返す
    // @return Holiday[]
    public function holidaysBetween(DateTimeImmutable $from, DateTimeImmutable $to): array;
}
```

- go-heijitu の `HolidayProvider` から `ctx` を除いた等価。エラーは例外で伝播。

---

## 6. BusinessCalendar（本体）

```php
namespace Heijitu;

final class BusinessCalendar
{
    private $provider;       // HolidayProvider
    private $excludedDates;  // MonthDay[]

    // コンストラクタ引数＋設定ローダー方式（確定 C-3）
    // @param MonthDay[] $excludedDates
    public function __construct(HolidayProvider $provider, array $excludedDates = []) { ... }

    // 営業日判定。extraExcluded はこの呼び出し限りの追加除外日付
    public function isBusinessDay(DateTimeImmutable $t, MonthDay ...$extraExcluded): bool { ... }

    // from の翌日以降で最初の営業日（from 当日は含まない）
    public function nextBusinessDay(DateTimeImmutable $from): DateTimeImmutable { ... }

    // 指定年月の最初の営業日
    public function firstBusinessDayOfMonth(int $year, int $month): DateTimeImmutable { ... }

    // 指定年の各月の最初の営業日（index 0=1月 .. 11=12月、必ず12件）
    // @return DateTimeImmutable[]
    public function firstBusinessDaysOfYear(int $year): array { ... }

    // 指定期間の祝日リスト（プロバイダーに委譲、除外日付フィルタなし）
    // @return Holiday[]
    public function holidays(DateTimeImmutable $from, DateTimeImmutable $to): array { ... }
}
```

### 営業日の判定条件（go-heijitu と同一）

全て満たすとき営業日:
1. 土日でない（`$t->format('N')` が 6・7 でない）
2. プロバイダーの `isHoliday()` が false
3. 固定の除外日付（`$excludedDates`）に含まれない
4. 呼び出し時の追加除外日付（`$extraExcluded`）に含まれない

### 探索ロジック（go-heijitu と同一）

- `nextBusinessDay`: `from->modify('+1 day')` から1日ずつ前進し最初の営業日を返す。
- `firstBusinessDayOfMonth`: 当月1日から当月内を前進。
- `firstBusinessDaysOfYear`: 12ヶ月分を `firstBusinessDayOfMonth` で集約。
- `holidays`: `provider->holidaysBetween` をそのまま返す。

---

## 7. 除外日付の指定（go-heijitu の WithExcludedDates / WithConfig 相当）

go-heijitu の関数オプション（`WithExcludedDates` / `WithConfig`）の PHP 表現は、**コンストラクタ引数＋設定ローダー方式に確定**（decisions.md C-3）。

- **パラメータ指定**: コンストラクタ第2引数に `MonthDay[]` を渡す。
- **設定ファイル指定**: `Config` の静的ファクトリでファイルから `MonthDay[]` を読み込み、コンストラクタに渡す。

```php
// パラメータ指定
$cal = new BusinessCalendar($provider, [
    new MonthDay(8, 15),
    new MonthDay(12, 31),
]);

// 設定ファイル + パラメータ併用
$fromFile = Config::loadExcludedDates('heijitu.yaml'); // MonthDay[]
$cal = new BusinessCalendar($provider, array_merge($fromFile, [new MonthDay(5, 1)]));
```

go-heijitu では `WithExcludedDates` と `WithConfig` が**マージ**される。PHP 版でも複数ソースのマージを可能にする（上記は `array_merge` で表現）。

> （不採用の別案: 「静的ファクトリ＋ wither メソッド」`BusinessCalendar::create($provider)->withExcludedDates(...)->withConfig(...)`。decisions.md C-3 で案A を採用済み。）

---

## 8. 設定ファイル仕様（go-heijitu と同一フォーマット）

拡張子で自動判別（`.yaml` / `.yml` → YAML、`.json` → JSON）。サポート外拡張子は例外。

### YAML

```yaml
excluded_dates:
  - month: 8
    day: 15
  - month: 12
    day: 31
```

### JSON

```json
{
  "excluded_dates": [
    {"month": 8, "day": 15},
    {"month": 12, "day": 31}
  ]
}
```

- YAML 読み込みは `symfony/yaml` ^5.4、JSON は標準 `json_decode`。

---

## 9. プロバイダー実装

### 9-1. HolidayJp プロバイダー（デフォルト）

- `holiday-jp/holiday_jp`（埋め込みデータ）に委譲。外部接続不要。
- `isHoliday` / `holidayName` / `holidaysBetween` を `HolidayJp::isHoliday` / `HolidayJp::between` でブリッジ。
- `holidaysBetween` は `between()` の結果を `Holiday[]` に変換し日付昇順でソート。祝日名は結果配列のキーから取得。
- デフォルトプロバイダーは `holiday-jp/holiday_jp` に確定（B-1）。`azuyalabs/yasumi` は不採用（API 形が異なる）。
- （可能なら）コンストラクタで `class_exists(\HolidayJp\HolidayJp::class)` を確認し、未導入なら `composer require holiday-jp/holiday_jp` を案内する `ProviderException` を投げる（H）。

```php
$provider = new \Heijitu\Providers\HolidayJp\Provider();
```

### 9-2. CaoCsv プロバイダー（内閣府CSV・自前実装）

- `Options` 相当: `csvPath`（指定時ローカル、空時オンライン取得）。
- オンライン取得は内閣府固定 URL（`https://www8.cao.go.jp/chosei/shukujitsu/syukujitsu.csv`）。任意 URL は受け付けない（go-heijitu 踏襲）。
- 取得後 `mb_convert_encoding(..., 'UTF-8', 'SJIS-win')` でデコード、`str_getcsv` でパースし内部に保持。
- 点照合（`isHoliday`/`holidayName`）は保持データの照合、`holidaysBetween` は範囲フィルタ＋昇順ソート。
- キャッシュなし（オンライン時は生成のたびに fetch）。
- HTTP 取得手段は **PHP 標準関数（`file_get_contents` または cURL）**（確定）。guzzle 等の追加依存は使わない → **caoCsv は追加の Composer 依存ゼロ**。
- （可能なら）`extension_loaded('mbstring')` を確認し、未導入なら `ext-mbstring` の導入を案内する `ProviderException` を投げる（H）。

```php
$provider = new \Heijitu\Providers\CaoCsv\Provider(['csvPath' => 'data/syukujitsu.csv']); // ローカル
$provider = new \Heijitu\Providers\CaoCsv\Provider([]);                                   // オンライン
```

### 9-3. GoogleCalendar プロバイダー

- `google/apiclient` ^2.16 を使用。Calendar ID は go-heijitu と同一の固定値。
- `Options` 相当: `apiKey` / `credentialsFile`。`credentialsFile` 優先、両方空なら例外（go-heijitu 踏襲）。
- `Events.List` を全ページ取得し終日イベントを `Holiday` に変換。
- （可能なら）コンストラクタで `class_exists` により `google/apiclient` の導入を確認し、未導入なら `composer require google/apiclient` を案内する `ProviderException` を投げる（H）。

```php
$provider = new \Heijitu\Providers\GoogleCalendar\Provider(['apiKey' => '...']);
```

---

## 10. 例外設計（確定 C-5）

go-heijitu の error 伝播を PHP の例外に置き換える。最小構成:

- `Heijitu\Exception\HeijituException`: マーカーインターフェース（本ライブラリが投げる例外の共通型。利用者は `catch (HeijituException $e)` で一括捕捉できる）。
- `Heijitu\Exception\ConfigException`（`\RuntimeException` を継承し `HeijituException` を実装）: 設定ファイルの読み込み・パース失敗・未対応拡張子。
- `Heijitu\Exception\ProviderException`（`\RuntimeException` を継承し `HeijituException` を実装）: プロバイダーのデータ取得・API 呼び出し失敗・認証情報不備。
- 型宣言で防げない不正引数（プログラマエラー）は標準 `\InvalidArgumentException`。
- プロバイダー内部のエラーは握りつぶさず上位へ伝播（go-heijitu の方針を継承）。
- 依存ライブラリ／拡張が未導入のままプロバイダーを使った場合は（可能なら）`class_exists()` / `extension_loaded()` で検出し、`composer require ...` を案内する親切なメッセージで `ProviderException`（プロバイダー依存）/ `ConfigException`（YAML 依存）を投げる（確定 H）。新たな例外クラスは追加しない。

---

## 11. 使用例（イメージ）

### インストール（GitHub VCS 配布・holidayjp 利用例）

```bash
# 利用者の composer.json に repositories（GitHub VCS）を登録のうえ
composer require taku-o/php-heijitu:dev-dev-branch holiday-jp/holiday_jp
```

### コード

```php
use Heijitu\BusinessCalendar;
use Heijitu\MonthDay;
use Heijitu\Providers\HolidayJp\Provider as HolidayJpProvider;

$cal = new BusinessCalendar(new HolidayJpProvider(), [
    new MonthDay(8, 15),
    new MonthDay(12, 31),
]);

$today = new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo'));

// 営業日判定
$ok = $cal->isBusinessDay($today);

// 次の営業日
$next = $cal->nextBusinessDay($today);
echo $next->format('Y-m-d (D)'), PHP_EOL;

// 指定月の最初の営業日
$first = $cal->firstBusinessDayOfMonth(2026, 4);

// 指定年の各月初営業日
$list = $cal->firstBusinessDaysOfYear(2026);

// 期間内の祝日
$holidays = $cal->holidays(
    new DateTimeImmutable('2026-01-01'),
    new DateTimeImmutable('2026-03-31')
);
```

---

## 12. go-heijitu から変わる点・移植の注意

- **`context.Context` の削除**（確定）: 全 API から第一引数の ctx が消える。利用感が Go 版と変わる。
- **多値返却 → 例外**（確定）: `(bool, error)` 等が `bool` ＋例外に変わる。`isBusinessDay` 等の戻り値がシンプルになる一方、エラーは try/catch で扱う。
- **タイムゾーンの扱い（確定 E-2）**: 利用者が渡す日付はその `DateTimeImmutable` のタイムゾーンを尊重する。ライブラリが内部生成する日付（`firstBusinessDayOfMonth` / `firstBusinessDaysOfYear` / `nextBusinessDay` の探索起点）は、実行環境のデフォルトタイムゾーン（`date_default_timezone_get()`、go 版の `time.Local` 相当）を使用する。実行環境のタイムゾーンが未設定（`ini_get('date.timezone')` が空）の場合は `Asia/Tokyo` を使用する。ドキュメントで JST 運用を案内する。
- **曜日判定**: Go の `time.Weekday`（Sunday=0）と PHP の `format('N')`（Monday=1〜Sunday=7）でインデックスが異なる。土日は `N` が 6・7。
- **祝日データの陳腐化**: `holiday-jp/holiday_jp`（PHP版）は2020年が最終更新。将来年のデータが不足しうる（go-heijitu の holidayjp と同じ弱点）。対応は go-heijitu と同方針（確定 E-1）= デフォルトは埋め込み、最新性が必要なら caoCsv/googleCalendar を案内し、ドキュメントで明記する。

---

_要決定事項の一覧は `decisions.md`、開発手順とステップ分割は `workplan.md` を参照。_
