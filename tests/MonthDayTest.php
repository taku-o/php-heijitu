<?php

declare(strict_types=1);

namespace Heijitu\Tests;

use Heijitu\MonthDay;
use PHPUnit\Framework\TestCase;

final class MonthDayTest extends TestCase
{
    public function testGetMonthReturnsConstructorValue(): void
    {
        $monthDay = new MonthDay(8, 15);
        $this->assertSame(8, $monthDay->getMonth());
    }

    public function testGetDayReturnsConstructorValue(): void
    {
        $monthDay = new MonthDay(8, 15);
        $this->assertSame(15, $monthDay->getDay());
    }

    public function testMatchesReturnsTrueWhenMonthAndDayMatch(): void
    {
        $monthDay = new MonthDay(8, 15);
        $this->assertTrue($monthDay->matches(new \DateTimeImmutable('2024-08-15')));
    }

    public function testMatchesReturnsTrueRegardlessOfYear(): void
    {
        $monthDay = new MonthDay(8, 15);
        $this->assertTrue($monthDay->matches(new \DateTimeImmutable('2000-08-15')));
        $this->assertTrue($monthDay->matches(new \DateTimeImmutable('2030-08-15')));
    }

    /** @return array<string, array<mixed>> */
    public function mismatchDatesProvider(): array
    {
        return [
            'month differs' => [8, 15, '2024-09-15'],
            'day differs'   => [8, 15, '2024-08-16'],
        ];
    }

    /**
     * @dataProvider mismatchDatesProvider
     */
    public function testMatchesReturnsFalse(int $month, int $day, string $dateStr): void
    {
        $monthDay = new MonthDay($month, $day);
        $this->assertFalse($monthDay->matches(new \DateTimeImmutable($dateStr)));
    }

    public function testMatchesReturnsFalseForNonExistentDate(): void
    {
        // 2月30日は存在しないため、2月のどの日付とも一致しない
        $monthDay = new MonthDay(2, 30);
        $this->assertFalse($monthDay->matches(new \DateTimeImmutable('2024-02-28')));
        $this->assertFalse($monthDay->matches(new \DateTimeImmutable('2024-02-29')));
    }

    public function testMatchesReturnsTrueForLeapYearFeb29(): void
    {
        $monthDay = new MonthDay(2, 29);
        $this->assertTrue($monthDay->matches(new \DateTimeImmutable('2024-02-29')));
    }

    public function testMatchesReturnsFalseForNonLeapYearFeb29(): void
    {
        // 平年には2月29日が存在しないため、2月28日とは一致しない
        $monthDay = new MonthDay(2, 29);
        $this->assertFalse($monthDay->matches(new \DateTimeImmutable('2023-02-28')));
    }

    public function testMatchesJanuary1st(): void
    {
        $monthDay = new MonthDay(1, 1);
        $this->assertTrue($monthDay->matches(new \DateTimeImmutable('2025-01-01')));
    }

    public function testMatchesDecember31st(): void
    {
        $monthDay = new MonthDay(12, 31);
        $this->assertTrue($monthDay->matches(new \DateTimeImmutable('2025-12-31')));
    }
}
