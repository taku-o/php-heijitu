# Provider Guide

## Comparison

| Provider | Dependency | Data source | Network required | Recommended for |
|----------|-----------|-------------|-----------------|----------------|
| `HolidayJp` | `holiday-jp/holiday_jp` | Bundled data | No | Development / testing |
| `CaoCsv` | `ext-mbstring` | Cabinet Office CSV | Yes (online mode) | Production use |
| `GoogleCalendar` | `google/apiclient` | Google Calendar API | Yes | Production use |

---

## HolidayJp

Uses `holiday-jp/holiday_jp` for offline holiday data. No network access required at runtime.

### Setup

```bash
composer require holiday-jp/holiday_jp
```

```php
use Heijitu\Providers\HolidayJp\Provider as HolidayJpProvider;

$provider = new HolidayJpProvider();
```

### Choosing a provider

`HolidayJp` is an offline provider that requires no network access. Comparison with the Cabinet Office CSV confirms no practical differences in holiday data, making it suitable for most use cases.

Use the `CaoCsv` or `GoogleCalendar` provider if you want to fetch holiday data from an external source in real time.

---

## CaoCsv

Downloads and parses the official Japanese holiday CSV published by the Cabinet Office. Requires `ext-mbstring` to decode the Shift_JIS encoded file.

### Setup

```bash
# ext-mbstring is a PHP built-in extension; enable it in your php.ini if not already active
```

### Local file mode

Download the CSV once and use a local path. Useful when network access from the runtime environment is restricted.

```php
use Heijitu\Providers\CaoCsv\Provider as CaoCsvProvider;

$provider = new CaoCsvProvider('/path/to/syukujitsu.csv');
```

### Online mode

Fetches the CSV directly from the Cabinet Office URL each time the constructor is called.

```php
use Heijitu\Providers\CaoCsv\Provider as CaoCsvProvider;

$provider = new CaoCsvProvider(); // empty string = online fetch
```

The CSV is fetched at construction time. If the request fails, a `ProviderException` is thrown — there is no retry or fallback.

### Notes

- `ext-mbstring` must be installed; the constructor throws `ProviderException` otherwise.
- The Cabinet Office CSV URL is fixed: `https://www8.cao.go.jp/chosei/shukujitsu/syukujitsu.csv`
- Cache the provider instance to avoid repeated network requests.

---

## GoogleCalendar

Fetches holiday data from the public Japanese holiday calendar via the Google Calendar API. Supports both API key authentication and service account credentials.

### Prerequisites

```bash
composer require google/apiclient:~2.16.0
```

### Obtaining a Google Calendar API key

1. Go to the [Google Cloud Console](https://console.cloud.google.com/).
2. Create or select a project.
3. Navigate to **APIs & Services > Library** and enable the **Google Calendar API**.
4. Navigate to **APIs & Services > Credentials** and click **Create credentials > API key**.
5. Copy the generated key.

### Setup with API key

Pass the API key to the constructor.

```php
use Heijitu\Providers\GoogleCalendar\Provider as GoogleCalendarProvider;

$provider = new GoogleCalendarProvider('YOUR_API_KEY');
```

### Using an environment variable

Store the key in an environment variable to avoid hardcoding it.

```php
use Heijitu\Providers\GoogleCalendar\Provider as GoogleCalendarProvider;

$provider = new GoogleCalendarProvider(getenv('GOOGLE_API_KEY'));
```

Set the variable before running your application:

```bash
export GOOGLE_API_KEY=your_key_here
```

### Setup with service account

For server-to-server authentication without a user API key, use a service account JSON credentials file.

1. In the Google Cloud Console, go to **APIs & Services > Credentials** and create a **Service account**.
2. Download the JSON key file.
3. Pass the file path as the second constructor argument.

```php
use Heijitu\Providers\GoogleCalendar\Provider as GoogleCalendarProvider;

$provider = new GoogleCalendarProvider('', '/path/to/credentials.json');
```

When `$credentialsFile` is specified, `$apiKey` is ignored.

### Notes

- Both `$apiKey` and `$credentialsFile` cannot be empty at the same time; the constructor throws `ProviderException`.
- Each call to `isHoliday()` or `holidayName()` makes a separate API request. Use `holidaysBetween()` for range queries to minimize API calls.
- The calendar ID used is `ja.japanese.official#holiday@group.v.calendar.google.com`.
