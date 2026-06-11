<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('Asia/Tokyo');

use Heijitu\BusinessCalendar;
use Heijitu\Config;
use Heijitu\MonthDay;
use Heijitu\Providers\CaoCsv\Provider as CaoCsvProvider;
use Heijitu\Providers\HolidayJp\Provider as HolidayJpProvider;

// =========================================================
// Section 1: HolidayJp Provider — 全 API
// =========================================================

echo "=== HolidayJp Provider ===" . PHP_EOL;

$provider = new HolidayJpProvider();
$cal = new BusinessCalendar($provider);

$newYear = new DateTimeImmutable('2024-01-01');
echo "2024-01-01 is business day: " . ($cal->isBusinessDay($newYear) ? 'true' : 'false') . PHP_EOL;

$nextBd = $cal->nextBusinessDay($newYear);
echo "Next business day after 2024-01-01: " . $nextBd->format('Y-m-d') . PHP_EOL;

$firstBd = $cal->firstBusinessDayOfMonth(2024, 1);
echo "First business day of 2024-01: " . $firstBd->format('Y-m-d') . PHP_EOL;

echo "First business days of 2024:" . PHP_EOL;
$firstBds = $cal->firstBusinessDaysOfYear(2024);
foreach ($firstBds as $i => $day) {
    echo "  " . sprintf('%02d', $i + 1) . ": " . $day->format('Y-m-d') . PHP_EOL;
}

$holidaysFrom = new DateTimeImmutable('2024-01-01');
$holidaysTo = new DateTimeImmutable('2024-03-31');
$holidays = $cal->holidays($holidaysFrom, $holidaysTo);
echo "Holidays between 2024-01-01 and 2024-03-31:" . PHP_EOL;
foreach ($holidays as $holiday) {
    echo "  " . $holiday->getDate()->format('Y-m-d') . " " . $holiday->getName() . PHP_EOL;
}

echo PHP_EOL;

// =========================================================
// Section 2: Excluded Dates (constructor)
// =========================================================

echo "=== Excluded Dates (constructor) ===" . PHP_EOL;

$calWithExcluded = new BusinessCalendar($provider, [
    new MonthDay(8, 15),
    new MonthDay(12, 31),
]);

$obon = new DateTimeImmutable('2024-08-15');
echo "2024-08-15 with dates excluded: " . ($calWithExcluded->isBusinessDay($obon) ? 'true' : 'false') . PHP_EOL;
echo "2024-08-15 without exclusion: " . ($cal->isBusinessDay($obon) ? 'true' : 'false') . PHP_EOL;

echo PHP_EOL;

// =========================================================
// Section 3: Config File
// =========================================================

echo "=== Config File ===" . PHP_EOL;

$tmpBase = tempnam(sys_get_temp_dir(), 'heijitu_');
if ($tmpBase === false) {
    die('Failed to create temporary file.' . PHP_EOL);
}
$tmpPath = $tmpBase . '.json';
$written = file_put_contents($tmpBase, json_encode([
    'excluded_dates' => [
        ['month' => 8, 'day' => 15],
        ['month' => 12, 'day' => 29],
    ],
]));
if ($written === false) {
    unlink($tmpBase);
    die('Failed to write temporary config file.' . PHP_EOL);
}
rename($tmpBase, $tmpPath);

try {
    $configExcluded = Config::loadExcludedDates($tmpPath);
    echo "Loaded excluded dates from config: " . count($configExcluded) . " entries" . PHP_EOL;

    $manualExcluded = [new MonthDay(12, 31)];
    $merged = array_merge($configExcluded, $manualExcluded);
    $calMerged = new BusinessCalendar($provider, $merged);
    echo "Total excluded dates (config + manual): " . count($merged) . " entries" . PHP_EOL;

    $obon2024 = new DateTimeImmutable('2024-08-15');
    echo "2024-08-15 is business day (merged): " . ($calMerged->isBusinessDay($obon2024) ? 'true' : 'false') . PHP_EOL;
} finally {
    unlink($tmpPath);
}

echo PHP_EOL;

// =========================================================
// Section 4: CaoCsv Provider (local)
// =========================================================

echo "=== CaoCsv Provider (local) ===" . PHP_EOL;

$csvPath = __DIR__ . '/data/syukujitsu_sample.csv';
$caoCsvLocal = new CaoCsvProvider($csvPath);
$calCaoLocal = new BusinessCalendar($caoCsvLocal);

$newYear2020 = new DateTimeImmutable('2020-01-01');
echo "2020-01-01 is holiday (CaoCsv): " . ($caoCsvLocal->isHoliday($newYear2020) ? 'true' : 'false') . PHP_EOL;
echo "2020-01-01 is business day (CaoCsv): " . ($calCaoLocal->isBusinessDay($newYear2020) ? 'true' : 'false') . PHP_EOL;

echo PHP_EOL;

// =========================================================
// Section 5: CaoCsv Provider (online)
// =========================================================

echo "=== CaoCsv Provider (online) ===" . PHP_EOL;

$caoCsvOnline = new CaoCsvProvider();
$calCaoOnline = new BusinessCalendar($caoCsvOnline);

$newYear2024 = new DateTimeImmutable('2024-01-01');
echo "2024-01-01 is holiday (CaoCsv online): " . ($caoCsvOnline->isHoliday($newYear2024) ? 'true' : 'false') . PHP_EOL;
echo "2024-01-01 is business day (CaoCsv online): " . ($calCaoOnline->isBusinessDay($newYear2024) ? 'true' : 'false') . PHP_EOL;

echo PHP_EOL;

// =========================================================
// Section 6: extraExcluded
// =========================================================

echo "=== extraExcluded ===" . PHP_EOL;

$t = new DateTimeImmutable('2024-08-13');
echo "2024-08-13 no extra: " . ($cal->isBusinessDay($t) ? 'true' : 'false') . PHP_EOL;
echo "2024-08-13 excluded via extraExcluded: " . ($cal->isBusinessDay($t, new MonthDay(8, 13)) ? 'true' : 'false') . PHP_EOL;
