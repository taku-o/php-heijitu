# プロバイダーガイド

## 比較表

| プロバイダー | 依存パッケージ | データソース | ネットワーク | 推奨用途 |
|------------|-------------|------------|------------|--------|
| `HolidayJp` | `holiday-jp/holiday_jp` | 組み込みデータ | 不要 | 開発・テスト |
| `CaoCsv` | `ext-mbstring` | 内閣府 CSV | 必要（オンラインモード） | 本番運用 |
| `GoogleCalendar` | `google/apiclient` | Google Calendar API | 必要 | 本番運用 |

---

## HolidayJp

`holiday-jp/holiday_jp` を使ったオフライン祝日プロバイダーです。実行時にネットワーク接続は不要です。

### セットアップ

```bash
composer require holiday-jp/holiday_jp
```

```php
use Heijitu\Providers\HolidayJp\Provider as HolidayJpProvider;

$provider = new HolidayJpProvider();
```

---

## CaoCsv

内閣府が公開している公式祝日 CSV を取得・解析します。Shift_JIS エンコードのファイルをデコードするために `ext-mbstring` が必要です。

### セットアップ

```bash
# ext-mbstring は PHP 標準拡張です。無効な場合は php.ini で有効にしてください。
```

### ローカルファイルモード

CSV をあらかじめダウンロードしておき、ローカルパスを指定します。実行環境からのネットワークアクセスが制限されている場合に有効です。

```php
use Heijitu\Providers\CaoCsv\Provider as CaoCsvProvider;

$provider = new CaoCsvProvider('/path/to/syukujitsu.csv');
```

### オンラインモード

コンストラクタ呼び出し時に内閣府の URL から CSV を直接取得します。

```php
use Heijitu\Providers\CaoCsv\Provider as CaoCsvProvider;

$provider = new CaoCsvProvider(); // 引数なし = オンライン取得
```

CSV はコンストラクタ実行時に取得されます。リクエストが失敗した場合は `ProviderException` が投げられます（リトライ・フォールバックなし）。

### 注意事項

- `ext-mbstring` が必要です。未導入の場合はコンストラクタが `ProviderException` を投げます。
- 内閣府 CSV の URL は固定です: `https://www8.cao.go.jp/chosei/shukujitsu/syukujitsu.csv`
- 繰り返しのネットワークリクエストを避けるため、プロバイダーインスタンスをキャッシュして再利用してください。

---

## GoogleCalendar

Google Calendar API を通じて日本の公式祝日カレンダーから祝日データを取得します。API キー認証とサービスアカウント認証の両方に対応しています。

### 事前準備

```bash
composer require google/apiclient:~2.16.0
```

### Google Calendar API キーの取得手順

1. [Google Cloud Console](https://console.cloud.google.com/) を開きます。
2. プロジェクトを作成または選択します。
3. **API とサービス > ライブラリ** から **Google Calendar API** を有効にします。
4. **API とサービス > 認証情報** で **認証情報を作成 > API キー** をクリックします。
5. 生成されたキーをコピーします。

### API キーを使ったセットアップ

API キーをコンストラクタに渡します。

```php
use Heijitu\Providers\GoogleCalendar\Provider as GoogleCalendarProvider;

$provider = new GoogleCalendarProvider('YOUR_API_KEY');
```

### 環境変数連携パターン

API キーをコードにハードコードしないために環境変数を使用します。

```php
use Heijitu\Providers\GoogleCalendar\Provider as GoogleCalendarProvider;

$provider = new GoogleCalendarProvider(getenv('GOOGLE_API_KEY'));
```

アプリケーションを実行する前に環境変数を設定します。

```bash
export GOOGLE_API_KEY=your_key_here
```

### サービスアカウントを使ったセットアップ

ユーザーの API キーを使わないサーバー間認証には、サービスアカウントの JSON 認証情報ファイルを使用します。

1. Google Cloud Console で **API とサービス > 認証情報** からサービスアカウントを作成します。
2. JSON キーファイルをダウンロードします。
3. ファイルパスを第 2 引数に渡します。

```php
use Heijitu\Providers\GoogleCalendar\Provider as GoogleCalendarProvider;

$provider = new GoogleCalendarProvider('', '/path/to/credentials.json');
```

`$credentialsFile` を指定した場合、`$apiKey` は無視されます。

### 注意事項

- `$apiKey` と `$credentialsFile` を両方空にすることはできません。その場合コンストラクタが `ProviderException` を投げます。
- `isHoliday()` と `holidayName()` はそれぞれ独立して API リクエストを発行します。範囲クエリには `holidaysBetween()` を使用して API 呼び出し回数を最小化してください。
- 使用するカレンダー ID: `ja.japanese.official#holiday@group.v.calendar.google.com`
