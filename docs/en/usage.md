# Usage Guide

## Installation

Add a VCS repository entry to your `composer.json` (this package is not on Packagist):

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

Then install:

```bash
composer require taku-o/php-heijitu
```

For provider-specific dependencies, see [Provider Guide](providers.md).

---

## Basic Usage

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
echo $cal->nextBusinessDay($date)->format('Y-m-d') . PHP_EOL;   // 2024-01-04
```

---

## Excluding Specific Dates

### Constructor argument

Pass a `MonthDay[]` array to the constructor to exclude recurring dates (company holidays, etc.) from all business-day calculations.

```php
use Heijitu\MonthDay;

$cal = new BusinessCalendar($provider, [
    new MonthDay(8, 15),  // Obon
    new MonthDay(12, 31), // New Year's Eve
]);

$obon = new DateTimeImmutable('2024-08-15');
echo ($cal->isBusinessDay($obon) ? 'true' : 'false') . PHP_EOL; // false
```

### Configuration file

Use `Config::loadExcludedDates()` to read excluded dates from a JSON or YAML file. For YAML files, `symfony/yaml` must be installed:

```bash
composer require symfony/yaml
```

**config.json:**
```json
{
    "excluded_dates": [
        { "month": 8, "day": 15 },
        { "month": 12, "day": 31 }
    ]
}
```

```php
use Heijitu\BusinessCalendar;
use Heijitu\Config;

$excluded = Config::loadExcludedDates('/path/to/config.json');
$cal      = new BusinessCalendar($provider, $excluded);
```

### Merging constructor and file sources

```php
use Heijitu\BusinessCalendar;
use Heijitu\Config;
use Heijitu\MonthDay;

$fromFile   = Config::loadExcludedDates('/path/to/config.json');
$additional = [new MonthDay(5, 1)]; // extra date added at runtime
$excluded   = array_merge($fromFile, $additional);

$cal = new BusinessCalendar($provider, $excluded);
```

For the full field reference and exception details, see [Configuration File Specification](api-spec.md#configuration-file-specification) in the API reference.

---

## API Examples

### `isBusinessDay`

Check whether a date is a business day.

```php
$date = new DateTimeImmutable('2024-01-04');

echo ($cal->isBusinessDay($date) ? 'true' : 'false') . PHP_EOL; // true
```

For per-call exclusions, see [`extraExcluded`](#extraexcluded--per-call-exclusions).

### `nextBusinessDay`

Get the first business day after a given date.

```php
$newYear  = new DateTimeImmutable('2024-01-01');
$nextBizDay = $cal->nextBusinessDay($newYear);

echo $nextBizDay->format('Y-m-d') . PHP_EOL; // 2024-01-04
```

### `firstBusinessDayOfMonth`

Get the first business day of a specific month.

```php
$first = $cal->firstBusinessDayOfMonth(2024, 1);

echo $first->format('Y-m-d') . PHP_EOL; // 2024-01-04
```

### `firstBusinessDaysOfYear`

Get the first business day of each month in a year (12 elements).

```php
$firsts = $cal->firstBusinessDaysOfYear(2024);

foreach ($firsts as $i => $date) {
    $month = $i + 1;
    echo sprintf('%02d: %s', $month, $date->format('Y-m-d')) . PHP_EOL;
}
// 01: 2024-01-04
// 02: 2024-02-01
// ...
```

### `holidays`

Get all holidays in a date range.

```php
$from = new DateTimeImmutable('2024-01-01');
$to   = new DateTimeImmutable('2024-03-31');

foreach ($cal->holidays($from, $to) as $holiday) {
    echo $holiday->getDate()->format('Y-m-d') . ' ' . $holiday->getName() . PHP_EOL;
}
// 2024-01-01 元日
// 2024-01-08 成人の日
// ...
```

### `extraExcluded` — per-call exclusions

Pass additional `MonthDay` values as variadic arguments to `isBusinessDay` to exclude dates for a single call without modifying the calendar instance.

```php
$date     = new DateTimeImmutable('2024-08-12');
$extraDay = new MonthDay(8, 12);

// Without extra exclusion
echo ($cal->isBusinessDay($date) ? 'true' : 'false') . PHP_EOL;           // true

// With extra exclusion for this call only
echo ($cal->isBusinessDay($date, $extraDay) ? 'true' : 'false') . PHP_EOL; // false
```

---

## Timezone

This library compares dates using PHP's process-default timezone. Always call `date_default_timezone_set()` before using the library.

```php
date_default_timezone_set('Asia/Tokyo');
```

Without this setting, date comparisons may be off by one day around midnight JST when the server timezone differs from Asia/Tokyo.

For more details, see [`date_default_timezone_set`](https://www.php.net/manual/en/function.date-default-timezone-set.php) in the PHP manual.
