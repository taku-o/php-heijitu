# API Reference

## BusinessCalendar

Calculates business days using a `HolidayProvider` and an optional list of recurring excluded dates.

### Constructor

```php
public function __construct(
    HolidayProvider $provider,
    array $excludedDates = []
)
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$provider` | `HolidayProvider` | Holiday data source |
| `$excludedDates` | `MonthDay[]` | Recurring dates to always treat as non-business days (e.g. company holidays) |

**Throws:** `\InvalidArgumentException` — if `$excludedDates` contains a non-`MonthDay` element.

### Methods

#### `isBusinessDay`

```php
public function isBusinessDay(
    \DateTimeImmutable $t,
    MonthDay ...$extraExcluded
): bool
```

Returns `true` if `$t` is a business day (not a weekend, not a holiday, not in `$excludedDates`, not in `$extraExcluded`).

| Parameter | Type | Description |
|-----------|------|-------------|
| `$t` | `\DateTimeImmutable` | Date to check |
| `...$extraExcluded` | `MonthDay` | Additional exclusions for this call only |

**Returns:** `bool`

**Throws:** `Exception\ProviderException` — propagated from the provider.

---

#### `nextBusinessDay`

```php
public function nextBusinessDay(\DateTimeImmutable $from): \DateTimeImmutable
```

Returns the first business day strictly after `$from`.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$from` | `\DateTimeImmutable` | Starting date (not included in result) |

**Returns:** `\DateTimeImmutable` — in the process-default timezone.

**Throws:** `Exception\ProviderException` — propagated from the provider.

---

#### `firstBusinessDayOfMonth`

```php
public function firstBusinessDayOfMonth(int $year, int $month): \DateTimeImmutable
```

Returns the first business day of the given month.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$year` | `int` | Year |
| `$month` | `int` | Month (1–12) |

**Returns:** `\DateTimeImmutable`

**Throws:** `Exception\ProviderException` — propagated from the provider.

---

#### `firstBusinessDaysOfYear`

```php
public function firstBusinessDaysOfYear(int $year): array
```

Returns the first business day of each month in the given year.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$year` | `int` | Year |

**Returns:** `\DateTimeImmutable[]` — 12 elements, index 0 = January, index 11 = December.

**Throws:** `Exception\ProviderException` — propagated from the provider.

---

#### `holidays`

```php
public function holidays(
    \DateTimeImmutable $from,
    \DateTimeImmutable $to
): array
```

Returns holidays in the range `$from` to `$to` (both ends inclusive), in ascending date order.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$from` | `\DateTimeImmutable` | Range start (inclusive) |
| `$to` | `\DateTimeImmutable` | Range end (inclusive) |

**Returns:** `Holiday[]` — empty array if `$from > $to`.

**Throws:** `Exception\ProviderException` — propagated from the provider.

---

## HolidayProvider

Interface that all provider implementations must satisfy. For setup and usage of the bundled providers, see the [Provider Guide](providers.md).

```php
interface HolidayProvider
```

### Methods

#### `isHoliday`

```php
public function isHoliday(\DateTimeImmutable $t): bool
```

**Throws:** `Exception\ProviderException` — on data fetch failure.

#### `holidayName`

```php
public function holidayName(\DateTimeImmutable $t): string
```

Returns the holiday name, or an empty string `""` if `$t` is not a holiday (does not throw an exception).

**Throws:** `Exception\ProviderException` — on data fetch failure.

#### `holidaysBetween`

```php
public function holidaysBetween(
    \DateTimeImmutable $from,
    \DateTimeImmutable $to
): array
```

Returns `Holiday[]` in ascending date order. Returns an empty array when `$from > $to`.

**Throws:** `Exception\ProviderException` — on data fetch failure.

---

## MonthDay

An immutable value object representing a month and day, independent of year. Used to specify recurring excluded dates.

### Constructor

```php
public function __construct(int $month, int $day)
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `$month` | `int` | Month (1–12) |
| `$day` | `int` | Day (1–31) |

No validation is performed. An invalid date such as `MonthDay(2, 30)` will never match any real date.

### Methods

#### `getMonth`

```php
public function getMonth(): int
```

#### `getDay`

```php
public function getDay(): int
```

#### `matches`

```php
public function matches(\DateTimeImmutable $t): bool
```

Returns `true` if the month and day of `$t` match this object (year is ignored).

---

## Holiday

An immutable value object holding a holiday date and its name. Returned by `HolidayProvider::holidaysBetween()` and `BusinessCalendar::holidays()`.

### Constructor

```php
public function __construct(\DateTimeImmutable $date, string $name)
```

### Methods

#### `getDate`

```php
public function getDate(): \DateTimeImmutable
```

#### `getName`

```php
public function getName(): string
```

---

## Config

Loads excluded dates from a JSON or YAML configuration file.

### Methods

#### `loadExcludedDates`

```php
public static function loadExcludedDates(string $path): array
```

Reads a JSON or YAML file and returns a `MonthDay[]` array.

| Parameter | Type | Description |
|-----------|------|-------------|
| `$path` | `string` | Absolute or relative path to the config file |

**Returns:** `MonthDay[]`

**Throws:** `Exception\ConfigException` — on unsupported file extension, read failure, or parse failure.

**Note:** YAML support requires `symfony/yaml` (`composer require symfony/yaml`).

---

## Exception Classes

### Hierarchy

```
\RuntimeException
├── Exception\ConfigException  (implements Exception\HeijituException)
└── Exception\ProviderException  (implements Exception\HeijituException)

interface Exception\HeijituException
```

### `Exception\HeijituException`

Marker interface for all exceptions thrown by this library. Catch this type to handle all library exceptions in one block:

```php
try {
    // ...
} catch (\Heijitu\Exception\HeijituException $e) {
    // handles both ConfigException and ProviderException
}
```

### `Exception\ConfigException`

Thrown by `Config::loadExcludedDates()` when:
- The file extension is not `.json`, `.yaml`, or `.yml`
- The file cannot be read
- The file cannot be parsed

### `Exception\ProviderException`

Thrown by provider constructors and `HolidayProvider` methods when:
- A required package is not installed (`holiday-jp/holiday_jp`, `google/apiclient`)
- A file cannot be read (CaoCsv local mode)
- An HTTP request fails (CaoCsv online mode, GoogleCalendar)
- Google Calendar API authentication fails

---

## Configuration File Specification

`Config::loadExcludedDates()` accepts JSON or YAML files with the following structure.

### JSON

```json
{
    "excluded_dates": [
        { "month": 8, "day": 15 },
        { "month": 12, "day": 31 }
    ]
}
```

### YAML

```yaml
excluded_dates:
  - { month: 8, day: 15 }
  - { month: 12, day: 31 }
```

### Field Reference

| Field | Type | Description |
|-------|------|-------------|
| `excluded_dates` | array | List of month/day pairs to exclude each year |
| `excluded_dates[].month` | integer | Month (1–12) |
| `excluded_dates[].day` | integer | Day (1–31) |

If `excluded_dates` is absent from the file, `loadExcludedDates()` returns an empty array.
