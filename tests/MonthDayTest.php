<?php

declare(strict_types=1);

namespace Heijitu\Tests;

use Heijitu\MonthDay;
use PHPUnit\Framework\TestCase;

final class MonthDayTest extends TestCase
{
    public function testGetMonthReturnsConstructorValue(): void
    {
        // Given: 月=8, 日=15 で MonthDay を生成する
        $monthDay = new MonthDay(8, 15);

        // Then: getMonth() が 8 を返す
        $this->assertSame(8, $monthDay->getMonth());
    }

    public function testGetDayReturnsConstructorValue(): void
    {
        // Given: 月=8, 日=15 で MonthDay を生成する
        $monthDay = new MonthDay(8, 15);

        // Then: getDay() が 15 を返す
        $this->assertSame(15, $monthDay->getDay());
    }

    public function testMatchesReturnsTrueWhenMonthAndDayMatch(): void
    {
        // Given: 8月15日の MonthDay
        $monthDay = new MonthDay(8, 15);

        // When: 2024-08-15 と照合する
        $date = new \DateTimeImmutable('2024-08-15');

        // Then: true を返す
        $this->assertTrue($monthDay->matches($date));
    }

    public function testMatchesReturnsTrueRegardlessOfYear(): void
    {
        // Given: 8月15日の MonthDay
        $monthDay = new MonthDay(8, 15);

        // When: 異なる年の 8月15日 と照合する
        $date2000 = new \DateTimeImmutable('2000-08-15');
        $date2030 = new \DateTimeImmutable('2030-08-15');

        // Then: いずれも true を返す
        $this->assertTrue($monthDay->matches($date2000));
        $this->assertTrue($monthDay->matches($date2030));
    }

    public function testMatchesReturnsFalseWhenMonthDiffers(): void
    {
        // Given: 8月15日の MonthDay
        $monthDay = new MonthDay(8, 15);

        // When: 9月15日 と照合する（月が不一致）
        $date = new \DateTimeImmutable('2024-09-15');

        // Then: false を返す
        $this->assertFalse($monthDay->matches($date));
    }

    public function testMatchesReturnsFalseWhenDayDiffers(): void
    {
        // Given: 8月15日の MonthDay
        $monthDay = new MonthDay(8, 15);

        // When: 8月16日 と照合する（日が不一致）
        $date = new \DateTimeImmutable('2024-08-16');

        // Then: false を返す
        $this->assertFalse($monthDay->matches($date));
    }

    public function testMatchesReturnsFalseForNonExistentDate(): void
    {
        // Given: 2月30日（存在しない日付）の MonthDay
        $monthDay = new MonthDay(2, 30);

        // When: 2月の各日と照合する（2月30日は存在しないため一致しない）
        $feb28 = new \DateTimeImmutable('2024-02-28');
        $feb29 = new \DateTimeImmutable('2024-02-29');

        // Then: いずれも false を返す
        $this->assertFalse($monthDay->matches($feb28));
        $this->assertFalse($monthDay->matches($feb29));
    }

    public function testMatchesReturnsTrueForLeapYearFeb29(): void
    {
        // Given: 2月29日の MonthDay
        $monthDay = new MonthDay(2, 29);

        // When: うるう年の 2月29日 と照合する
        $leapDate = new \DateTimeImmutable('2024-02-29');

        // Then: true を返す
        $this->assertTrue($monthDay->matches($leapDate));
    }

    public function testMatchesReturnsFalseForNonLeapYearFeb29(): void
    {
        // Given: 2月29日の MonthDay
        $monthDay = new MonthDay(2, 29);

        // When: 平年の 2月28日 と照合する（平年には2月29日が存在しない）
        $nonLeapFeb28 = new \DateTimeImmutable('2023-02-28');

        // Then: false を返す（日が一致しない）
        $this->assertFalse($monthDay->matches($nonLeapFeb28));
    }

    public function testMatchesJanuary1st(): void
    {
        // Given: 1月1日の MonthDay
        $monthDay = new MonthDay(1, 1);

        // When: 元日と照合する
        $date = new \DateTimeImmutable('2025-01-01');

        // Then: true を返す
        $this->assertTrue($monthDay->matches($date));
    }

    public function testMatchesDecember31st(): void
    {
        // Given: 12月31日の MonthDay
        $monthDay = new MonthDay(12, 31);

        // When: 大晦日と照合する
        $date = new \DateTimeImmutable('2025-12-31');

        // Then: true を返す
        $this->assertTrue($monthDay->matches($date));
    }
}
