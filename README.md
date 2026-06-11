# php-heijitu

Japanese business day calculation library for PHP — a PHP port of go-heijitu.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## Requirements

- PHP 7.4 or 8.x

## Installation

This package is distributed via GitHub (not Packagist). Add a VCS repository entry to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/taku-o/php-heijitu"
        }
    ]
}
```

Then install the library:

```bash
composer require taku-o/php-heijitu
```

### Optional provider dependencies

| Provider | Package | Install command |
|----------|---------|----------------|
| HolidayJp | `holiday-jp/holiday_jp` | `composer require holiday-jp/holiday_jp` |
| GoogleCalendar | `google/apiclient` | `composer require google/apiclient:~2.16.0` |
| CaoCsv (SJIS decode) | `ext-mbstring` | PHP extension (built-in on most installations) |
| Config YAML support | `symfony/yaml` | `composer require symfony/yaml` |

## Quick Start

```php
<?php
require 'vendor/autoload.php';

date_default_timezone_set('Asia/Tokyo');

use Heijitu\BusinessCalendar;
use Heijitu\Providers\HolidayJp\Provider as HolidayJpProvider;

$provider = new HolidayJpProvider();
$cal      = new BusinessCalendar($provider);

$date = new DateTimeImmutable('2024-01-01');

echo ($cal->isBusinessDay($date) ? 'true' : 'false') . PHP_EOL; // false
echo $cal->nextBusinessDay($date)->format('Y-m-d'); // 2024-01-04
```

## Excluding Specific Dates

### Constructor argument

```php
use Heijitu\MonthDay;

$cal = new BusinessCalendar($provider, [
    new MonthDay(8, 15),  // Obon (mid-August)
    new MonthDay(12, 31), // New Year's Eve
]);
```

### Configuration file (JSON / YAML)

```php
use Heijitu\Config;

$excluded = Config::loadExcludedDates('/path/to/config.json');
$merged   = array_merge($excluded, [new MonthDay(12, 31)]);
$cal      = new BusinessCalendar($provider, $merged);
```

**JSON format:**
```json
{
    "excluded_dates": [
        { "month": 8, "day": 15 },
        { "month": 12, "day": 31 }
    ]
}
```

**YAML format** (requires `symfony/yaml`):
```yaml
excluded_dates:
  - { month: 8, day: 15 }
  - { month: 12, day: 31 }
```

## Providers

See [`docs/en/providers.md`](docs/en/providers.md) for setup instructions for each provider.

| Provider | Dependency | Data source |
|----------|-----------|-------------|
| `HolidayJp` | `holiday-jp/holiday_jp` | Bundled (offline, no network required) |
| `CaoCsv` | `ext-mbstring` | Cabinet Office CSV (online fetch or local file) |
| `GoogleCalendar` | `google/apiclient` | Google Calendar API |

## Data Currency Warning

The `HolidayJp` provider relies on `holiday-jp/holiday_jp`, whose bundled data **has not been updated since 2020**. Holiday changes after 2021 (such as adjustments to Mountain Day) are **not reflected**.

For production use, prefer the **`CaoCsv`** or **`GoogleCalendar`** provider, which fetches up-to-date holiday data.

## Timezone

This library compares dates using PHP's default timezone. Always set the timezone to `Asia/Tokyo` before using the library:

```php
date_default_timezone_set('Asia/Tokyo');
```

Failing to set the timezone may cause off-by-one date errors around midnight JST.

## Documentation

- [API Reference](docs/en/api-spec.md)
- [Usage Guide](docs/en/usage.md)
- [Provider Guide](docs/en/providers.md)
- [日本語 README](README-ja.md)

## License

MIT — see [LICENSE](LICENSE) for details.
