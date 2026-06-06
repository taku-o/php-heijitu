<?php

declare(strict_types=1);

namespace Heijitu\Tests;

use Heijitu\Holiday;
use Heijitu\HolidayProvider;
use PHPUnit\Framework\TestCase;

final class HolidayProviderTest extends TestCase
{
    public function testAnonymousClassImplementsHolidayProvider(): void
    {
        $provider = $this->createStubProvider();
        $this->assertInstanceOf(HolidayProvider::class, $provider);
    }

    public function testIsHolidayReturnsTrueForHoliday(): void
    {
        // Given: 2025-01-01 を祝日とするプロバイダー
        $provider = $this->createStubProvider();

        // When: 祝日の日付で isHoliday を呼ぶ
        $result = $provider->isHoliday(new \DateTimeImmutable('2025-01-01'));

        // Then: true を返す
        $this->assertTrue($result);
    }

    public function testIsHolidayReturnsFalseForNonHoliday(): void
    {
        // Given: 2025-01-01 を祝日とするプロバイダー
        $provider = $this->createStubProvider();

        // When: 祝日でない日付で isHoliday を呼ぶ
        $result = $provider->isHoliday(new \DateTimeImmutable('2025-01-02'));

        // Then: false を返す
        $this->assertFalse($result);
    }

    public function testHolidayNameReturnsNameForHoliday(): void
    {
        // Given: 2025-01-01 を祝日とするプロバイダー
        $provider = $this->createStubProvider();

        // When: 祝日の日付で holidayName を呼ぶ
        $result = $provider->holidayName(new \DateTimeImmutable('2025-01-01'));

        // Then: 祝日名を返す
        $this->assertSame('元日', $result);
    }

    public function testHolidayNameReturnsEmptyStringForNonHoliday(): void
    {
        // Given: 2025-01-01 を祝日とするプロバイダー
        $provider = $this->createStubProvider();

        // When: 祝日でない日付で holidayName を呼ぶ
        $result = $provider->holidayName(new \DateTimeImmutable('2025-01-02'));

        // Then: 空文字を返す
        $this->assertSame('', $result);
    }

    public function testHolidaysBetweenReturnsHolidayArray(): void
    {
        // Given: 2025-01-01 を祝日とするプロバイダー
        $provider = $this->createStubProvider();

        // When: 祝日を含む期間で holidaysBetween を呼ぶ
        $from = new \DateTimeImmutable('2025-01-01');
        $to = new \DateTimeImmutable('2025-01-31');
        $result = $provider->holidaysBetween($from, $to);

        // Then: Holiday の配列が返る
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Holiday::class, $result[0]);
        $this->assertSame('元日', $result[0]->getName());
        $this->assertSame('2025-01-01', $result[0]->getDate()->format('Y-m-d'));
    }

    private function createStubProvider(): HolidayProvider
    {
        return new class implements HolidayProvider {
            public function isHoliday(\DateTimeImmutable $t): bool
            {
                return $t->format('Y-m-d') === '2025-01-01';
            }

            public function holidayName(\DateTimeImmutable $t): string
            {
                if ($t->format('Y-m-d') === '2025-01-01') {
                    return '元日';
                }
                return '';
            }

            /** @return Holiday[] */
            public function holidaysBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
            {
                $holidays = [];
                $date = new \DateTimeImmutable('2025-01-01');
                if ($date >= $from && $date <= $to) {
                    $holidays[] = new Holiday($date, '元日');
                }
                return $holidays;
            }
        };
    }
}
