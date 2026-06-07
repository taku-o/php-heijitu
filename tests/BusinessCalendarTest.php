<?php

declare(strict_types=1);

namespace Heijitu\Tests;

use Heijitu\BusinessCalendar;
use Heijitu\Config;
use Heijitu\Exception\ProviderException;
use Heijitu\Holiday;
use Heijitu\HolidayProvider;
use Heijitu\MonthDay;
use Heijitu\Providers\HolidayJp\Provider;
use PHPUnit\Framework\TestCase;

final class BusinessCalendarTest extends TestCase
{
    /**
     * @return HolidayProvider 全日を非祝日として返すプロバイダー
     */
    private function neverHolidayProvider(): HolidayProvider
    {
        return new class implements HolidayProvider {
            public function isHoliday(\DateTimeImmutable $t): bool
            {
                return false;
            }

            public function holidayName(\DateTimeImmutable $t): string
            {
                return '';
            }

            /** @return Holiday[] */
            public function holidaysBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
            {
                return [];
            }
        };
    }

    /**
     * @param string[] $holidayDates Y-m-d 形式の祝日リスト
     * @return HolidayProvider 指定日を祝日として返すプロバイダー
     */
    private function fixedHolidayProvider(array $holidayDates): HolidayProvider
    {
        return new class($holidayDates) implements HolidayProvider {
            /** @var string[] */
            private array $dates;

            /** @param string[] $dates */
            public function __construct(array $dates)
            {
                $this->dates = $dates;
            }

            public function isHoliday(\DateTimeImmutable $t): bool
            {
                return in_array($t->format('Y-m-d'), $this->dates, true);
            }

            public function holidayName(\DateTimeImmutable $t): string
            {
                return $this->isHoliday($t) ? '祝日' : '';
            }

            /** @return Holiday[] */
            public function holidaysBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
            {
                return [];
            }
        };
    }

    // -------------------------------------------------------
    // 正常系: 週末（土・日）→ false（要件 7.1）
    // -------------------------------------------------------

    /** @return array<string, array<string>> */
    public function weekendDayProvider(): array
    {
        return [
            'Saturday' => ['2025-06-07'],
            'Sunday'   => ['2025-06-08'],
        ];
    }

    /** @dataProvider weekendDayProvider */
    public function testReturnsFalseForWeekend(string $dateStr): void
    {
        // Given: 全日を非祝日とするプロバイダーで BusinessCalendar を構築する
        $calendar = new BusinessCalendar($this->neverHolidayProvider());

        // When/Then: 週末の日付を判定し、false を返す
        $this->assertFalse($calendar->isBusinessDay(new \DateTimeImmutable($dateStr)));
    }

    // -------------------------------------------------------
    // 正常系: 祝日 → false（要件 7.2）
    // -------------------------------------------------------

    public function testReturnsFalseForHoliday(): void
    {
        // Given: 2025-01-01 を祝日とするプロバイダーで BusinessCalendar を構築する
        $provider = $this->fixedHolidayProvider(['2025-01-01']);
        $calendar = new BusinessCalendar($provider);

        // When: 祝日の水曜日（2025-01-01）を判定する
        $holiday = new \DateTimeImmutable('2025-01-01');

        // Then: false を返す
        $this->assertFalse($calendar->isBusinessDay($holiday));
    }

    // -------------------------------------------------------
    // 正常系: コンストラクタの excludedDates に一致 → false（要件 7.3）
    // -------------------------------------------------------

    public function testReturnsFalseForExcludedDate(): void
    {
        // Given: 8月15日を除外日付として BusinessCalendar を構築する
        $excluded = [new MonthDay(8, 15)];
        $calendar = new BusinessCalendar($this->neverHolidayProvider(), $excluded);

        // When: 平日の 2025-08-15（金曜日）を判定する
        $date = new \DateTimeImmutable('2025-08-15');

        // Then: false を返す
        $this->assertFalse($calendar->isBusinessDay($date));
    }

    // -------------------------------------------------------
    // 正常系: excludedDates が異なる年でも一致 → false（要件 7.3 + 3.2）
    // -------------------------------------------------------

    public function testExcludedDateMatchesRegardlessOfYear(): void
    {
        // Given: 12月29日を除外日付として BusinessCalendar を構築する
        $excluded = [new MonthDay(12, 29)];
        $calendar = new BusinessCalendar($this->neverHolidayProvider(), $excluded);

        // When: 異なる年の平日の 12月29日 を判定する
        $date2025 = new \DateTimeImmutable('2025-12-29'); // 月曜日
        $date2026 = new \DateTimeImmutable('2026-12-29'); // 火曜日

        // Then: いずれも false を返す
        $this->assertFalse($calendar->isBusinessDay($date2025));
        $this->assertFalse($calendar->isBusinessDay($date2026));
    }

    // -------------------------------------------------------
    // 正常系: extraExcluded に一致 → false（要件 7.4）
    // -------------------------------------------------------

    public function testReturnsFalseForExtraExcludedDate(): void
    {
        // Given: 除外日付なしで BusinessCalendar を構築する
        $calendar = new BusinessCalendar($this->neverHolidayProvider());

        // When: 平日の 2025-06-09（月曜日）を、6月9日を追加除外して判定する
        $date = new \DateTimeImmutable('2025-06-09');
        $extra = new MonthDay(6, 9);

        // Then: false を返す
        $this->assertFalse($calendar->isBusinessDay($date, $extra));
    }

    // -------------------------------------------------------
    // 正常系: extraExcluded は他の呼び出しに影響しない（要件 7.4）
    // -------------------------------------------------------

    public function testExtraExcludedDoesNotAffectOtherCalls(): void
    {
        // Given: 除外日付なしで BusinessCalendar を構築する
        $calendar = new BusinessCalendar($this->neverHolidayProvider());
        $date = new \DateTimeImmutable('2025-06-09'); // 月曜日

        // When: 1回目は 6月9日を追加除外して判定する
        $extra = new MonthDay(6, 9);
        $firstCall = $calendar->isBusinessDay($date, $extra);

        // When: 2回目は追加除外なしで同じ日を判定する
        $secondCall = $calendar->isBusinessDay($date);

        // Then: 1回目は false、2回目は true を返す
        $this->assertFalse($firstCall);
        $this->assertTrue($secondCall);
    }

    // -------------------------------------------------------
    // 正常系: 平日・非祝日・除外なし → true（要件 7.5）
    // -------------------------------------------------------

    public function testReturnsTrueForRegularBusinessDay(): void
    {
        // Given: 全日を非祝日とするプロバイダー・除外日付なしで BusinessCalendar を構築する
        $calendar = new BusinessCalendar($this->neverHolidayProvider());

        // When: 平日の 2025-06-09（月曜日）を判定する
        $monday = new \DateTimeImmutable('2025-06-09');

        // Then: true を返す
        $this->assertTrue($calendar->isBusinessDay($monday));
    }

    // -------------------------------------------------------
    // 正常系: 各曜日（月〜金）で true（要件 7.5）
    // -------------------------------------------------------

    public function testReturnsTrueForAllWeekdays(): void
    {
        // Given: 全日を非祝日とするプロバイダーで BusinessCalendar を構築する
        $calendar = new BusinessCalendar($this->neverHolidayProvider());

        // When/Then: 月曜〜金曜を判定し、いずれも true を返す
        // 2025-06-09(月), 10(火), 11(水), 12(木), 13(金)
        $this->assertTrue($calendar->isBusinessDay(new \DateTimeImmutable('2025-06-09')));
        $this->assertTrue($calendar->isBusinessDay(new \DateTimeImmutable('2025-06-10')));
        $this->assertTrue($calendar->isBusinessDay(new \DateTimeImmutable('2025-06-11')));
        $this->assertTrue($calendar->isBusinessDay(new \DateTimeImmutable('2025-06-12')));
        $this->assertTrue($calendar->isBusinessDay(new \DateTimeImmutable('2025-06-13')));
    }

    // -------------------------------------------------------
    // 正常系: タイムゾーンが考慮される（要件 7.6）
    // -------------------------------------------------------

    public function testUsesTimezoneOfSuppliedDate(): void
    {
        // Given: 全日を非祝日とするプロバイダーで BusinessCalendar を構築する
        $calendar = new BusinessCalendar($this->neverHolidayProvider());

        // When: UTC では日曜だが JST では月曜になる日時を JST で判定する
        // 2025-06-08 23:00 UTC = 2025-06-09 08:00 JST（月曜日）
        $jstDate = new \DateTimeImmutable('2025-06-09 08:00:00', new \DateTimeZone('Asia/Tokyo'));

        // Then: JST の曜日（月曜日）で判定され true を返す
        $this->assertTrue($calendar->isBusinessDay($jstDate));
    }

    // -------------------------------------------------------
    // 正常系: Config由来の除外日付とコンストラクタ除外日付の併用（要件 6.4）
    // -------------------------------------------------------

    public function testExcludedDatesFromConfigAndConstructorAreBothApplied(): void
    {
        // Given: Config から除外日付を読み込む（8/15, 12/29）
        $configExcluded = Config::loadExcludedDates(__DIR__ . '/testdata/config.yaml');

        // Given: コンストラクタ引数で追加の除外日付（1/2）も渡す
        $merged = array_merge($configExcluded, [new MonthDay(1, 2)]);
        $calendar = new BusinessCalendar($this->neverHolidayProvider(), $merged);

        // When: Config 由来の除外日付 8/15（金曜日）を判定する
        $aug15 = new \DateTimeImmutable('2025-08-15');

        // When: コンストラクタ引数由来の除外日付 1/2（木曜日）を判定する
        $jan2 = new \DateTimeImmutable('2025-01-02');

        // Then: いずれも false を返す
        $this->assertFalse($calendar->isBusinessDay($aug15));
        $this->assertFalse($calendar->isBusinessDay($jan2));
    }

    // -------------------------------------------------------
    // 異常系: プロバイダーが例外を throw → 伝播（要件 7.7）
    // -------------------------------------------------------

    public function testPropagatesProviderException(): void
    {
        // Given: isHoliday() が ProviderException を throw するプロバイダー
        $provider = new class implements HolidayProvider {
            public function isHoliday(\DateTimeImmutable $t): bool
            {
                throw new ProviderException('API failure');
            }

            public function holidayName(\DateTimeImmutable $t): string
            {
                return '';
            }

            /** @return Holiday[] */
            public function holidaysBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
            {
                return [];
            }
        };
        $calendar = new BusinessCalendar($provider);

        // When/Then: isBusinessDay() が ProviderException を伝播する
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('API failure');
        $calendar->isBusinessDay(new \DateTimeImmutable('2025-06-09'));
    }

    // -------------------------------------------------------
    // 異常系: excludedDates に非 MonthDay → InvalidArgumentException（要件 9.5）
    // -------------------------------------------------------

    /** @return array<string, array<mixed>> */
    public function invalidExcludedDatesProvider(): array
    {
        return [
            'non-MonthDay string' => ['not-a-month-day'],
            'integer value'       => [123],
        ];
    }

    /** @dataProvider invalidExcludedDatesProvider */
    public function testThrowsInvalidArgumentExceptionForInvalidExcludedDates($invalidValue): void
    {
        // Given/When: excludedDates に無効な値を含む配列でコンストラクタを呼ぶ
        $this->expectException(\InvalidArgumentException::class);

        /** @phpstan-ignore-next-line */
        new BusinessCalendar($this->neverHolidayProvider(), [$invalidValue]);
    }

    // -------------------------------------------------------
    // 境界値: 除外日付が空配列（デフォルト）→ 影響なし
    // -------------------------------------------------------

    public function testEmptyExcludedDatesDoesNotAffectBusinessDay(): void
    {
        // Given: 除外日付を空で BusinessCalendar を構築する
        $calendar = new BusinessCalendar($this->neverHolidayProvider(), []);

        // When: 平日を判定する
        $date = new \DateTimeImmutable('2025-06-09');

        // Then: true を返す
        $this->assertTrue($calendar->isBusinessDay($date));
    }

    // -------------------------------------------------------
    // 境界値: excludedDates と extraExcluded が同時に作用
    // -------------------------------------------------------

    public function testBothExcludedAndExtraExcludedAreApplied(): void
    {
        // Given: 8月15日を除外日付として BusinessCalendar を構築する
        $excluded = [new MonthDay(8, 15)];
        $calendar = new BusinessCalendar($this->neverHolidayProvider(), $excluded);

        // When: excludedDates に一致する日を判定する → false
        $aug15 = new \DateTimeImmutable('2025-08-15'); // 金曜日
        $this->assertFalse($calendar->isBusinessDay($aug15));

        // When: extraExcluded に一致する日を判定する → false
        $jun9 = new \DateTimeImmutable('2025-06-09'); // 月曜日
        $extra = new MonthDay(6, 9);
        $this->assertFalse($calendar->isBusinessDay($jun9, $extra));

        // When: どちらにも該当しない日を判定する → true
        $jun10 = new \DateTimeImmutable('2025-06-10'); // 火曜日
        $this->assertTrue($calendar->isBusinessDay($jun10));
    }

    // -------------------------------------------------------
    // 境界値: 複数の extraExcluded を渡す
    // -------------------------------------------------------

    public function testMultipleExtraExcludedDates(): void
    {
        // Given: 除外日付なしで BusinessCalendar を構築する
        $calendar = new BusinessCalendar($this->neverHolidayProvider());

        // When: 複数の extraExcluded を渡して判定する
        $date = new \DateTimeImmutable('2025-06-09'); // 月曜日
        $extra1 = new MonthDay(6, 9);
        $extra2 = new MonthDay(6, 10);

        // Then: 一致する extraExcluded がある場合は false
        $this->assertFalse($calendar->isBusinessDay($date, $extra1, $extra2));
    }

    // -------------------------------------------------------
    // エッジケース: 祝日 + 土曜日（複数条件の重複）
    // -------------------------------------------------------

    public function testReturnsFalseWhenHolidayFallsOnSaturday(): void
    {
        // Given: 2025-01-04（土曜日）を祝日とするプロバイダー
        $provider = $this->fixedHolidayProvider(['2025-01-04']);
        $calendar = new BusinessCalendar($provider);

        // When: 祝日かつ土曜日を判定する
        $date = new \DateTimeImmutable('2025-01-04');

        // Then: false を返す
        $this->assertFalse($calendar->isBusinessDay($date));
    }

    // -------------------------------------------------------
    // エッジケース: 祝日 + 除外日付（複数条件の重複）
    // -------------------------------------------------------

    public function testReturnsFalseWhenHolidayAndExcludedDateOverlap(): void
    {
        // Given: 2025-01-01（水曜日）を祝日とし、1月1日を除外日付にも登録
        $provider = $this->fixedHolidayProvider(['2025-01-01']);
        $excluded = [new MonthDay(1, 1)];
        $calendar = new BusinessCalendar($provider, $excluded);

        // When: 祝日 + 除外の日を判定する
        $date = new \DateTimeImmutable('2025-01-01');

        // Then: false を返す
        $this->assertFalse($calendar->isBusinessDay($date));
    }

    // -------------------------------------------------------
    // nextBusinessDay: 金曜の翌営業日が月曜になる（週末スキップ）（要件 2.1, 2.2）
    // -------------------------------------------------------

    public function testNextBusinessDaySkipsWeekend(): void
    {
        // Given: 全日を非祝日とするプロバイダーで BusinessCalendar を構築する
        $calendar = new BusinessCalendar($this->neverHolidayProvider());

        // When: 金曜日（2020-01-10）の翌営業日を取得する
        $from = new \DateTimeImmutable('2020-01-10');
        $result = $calendar->nextBusinessDay($from);

        // Then: 月曜日（2020-01-13）を返す
        $this->assertSame('2020-01-13', $result->format('Y-m-d'));
    }

    // -------------------------------------------------------
    // nextBusinessDay: 祝日をスキップする（要件 2.1, 2.2）
    // -------------------------------------------------------

    public function testNextBusinessDaySkipsHoliday(): void
    {
        // Given: HolidayJp\Provider（実プロバイダー）で BusinessCalendar を構築する
        $calendar = new BusinessCalendar(new Provider());

        // When: 2019-12-31（火）の翌営業日を取得する（1/1 は元日）
        $from = new \DateTimeImmutable('2019-12-31');
        $result = $calendar->nextBusinessDay($from);

        // Then: 2020-01-02（木）を返す
        $this->assertSame('2020-01-02', $result->format('Y-m-d'));
    }

    // -------------------------------------------------------
    // nextBusinessDay: 除外日付が適用される（要件 2.3）
    // -------------------------------------------------------

    public function testNextBusinessDayWithExcludedDates(): void
    {
        // Given: 1/10 を除外日付として BusinessCalendar を構築する
        $excluded = [new MonthDay(1, 10)];
        $calendar = new BusinessCalendar($this->neverHolidayProvider(), $excluded);

        // When: 2020-01-09（木）の翌営業日を取得する（1/10(金)は除外、1/11-12 は週末）
        $from = new \DateTimeImmutable('2020-01-09');
        $result = $calendar->nextBusinessDay($from);

        // Then: 2020-01-13（月）を返す
        $this->assertSame('2020-01-13', $result->format('Y-m-d'));
    }

    // -------------------------------------------------------
    // firstBusinessDayOfMonth: 1日が祝日の場合の翌営業日返却（要件 3.1, 3.2）
    // -------------------------------------------------------

    public function testFirstBusinessDayOfMonthWhenFirstIsHoliday(): void
    {
        // Given: HolidayJp\Provider（実プロバイダー）で BusinessCalendar を構築する
        $calendar = new BusinessCalendar(new Provider());

        // When: 2020年1月の月初営業日を取得する（1/1 は元日）
        $result = $calendar->firstBusinessDayOfMonth(2020, 1);

        // Then: 2020-01-02（木）を返す
        $this->assertSame('2020-01-02', $result->format('Y-m-d'));
    }

    // -------------------------------------------------------
    // firstBusinessDayOfMonth: 除外日付がスキップされる（要件 3.2）
    // -------------------------------------------------------

    public function testFirstBusinessDayOfMonthWithExcludedDates(): void
    {
        // Given: 4/1 を除外日付として BusinessCalendar を構築する
        $excluded = [new MonthDay(4, 1)];
        $calendar = new BusinessCalendar($this->neverHolidayProvider(), $excluded);

        // When: 2020年4月の月初営業日を取得する（4/1(水)は除外）
        $result = $calendar->firstBusinessDayOfMonth(2020, 4);

        // Then: 2020-04-02（木）を返す
        $this->assertSame('2020-04-02', $result->format('Y-m-d'));
    }

    // -------------------------------------------------------
    // firstBusinessDaysOfYear: 12件の配列を返す（要件 4.1）
    // -------------------------------------------------------

    public function testFirstBusinessDaysOfYearReturns12Entries(): void
    {
        // Given: HolidayJp\Provider（実プロバイダー）で BusinessCalendar を構築する
        $calendar = new BusinessCalendar(new Provider());

        // When: 2020年の年間月初営業日を取得する
        $result = $calendar->firstBusinessDaysOfYear(2020);

        // Then: 要素数が12であること
        $this->assertCount(12, $result);

        // Then: 各要素が DateTimeImmutable であること
        foreach ($result as $entry) {
            $this->assertInstanceOf(\DateTimeImmutable::class, $entry);
        }

        // Then: index 0 は1月の月初営業日（2020-01-02、1/1は元日）
        $this->assertSame('2020-01-02', $result[0]->format('Y-m-d'));
    }

    // -------------------------------------------------------
    // holidays: プロバイダーのデータがそのまま返る（要件 5.1）
    // -------------------------------------------------------

    public function testHolidaysReturnsProviderData(): void
    {
        // Given: HolidayJp\Provider（実プロバイダー）で BusinessCalendar を構築する
        $calendar = new BusinessCalendar(new Provider());

        // When: 2020-01-01〜2020-01-13 の祝日を取得する
        $from = new \DateTimeImmutable('2020-01-01');
        $to = new \DateTimeImmutable('2020-01-13');
        $result = $calendar->holidays($from, $to);

        // Then: 元日・成人の日の2件を含む
        $this->assertCount(2, $result);
        $this->assertInstanceOf(Holiday::class, $result[0]);
        $this->assertSame('2020-01-01', $result[0]->getDate()->format('Y-m-d'));
        $this->assertSame('元日', $result[0]->getName());
        $this->assertInstanceOf(Holiday::class, $result[1]);
        $this->assertSame('2020-01-13', $result[1]->getDate()->format('Y-m-d'));
        $this->assertSame('成人の日', $result[1]->getName());
    }

    // -------------------------------------------------------
    // holidays: 除外日付はフィルタされない（要件 5.2）
    // -------------------------------------------------------

    public function testHolidaysIgnoresExcludedDates(): void
    {
        // Given: 1/1 を除外日付として、HolidayJp\Provider で BusinessCalendar を構築する
        $excluded = [new MonthDay(1, 1)];
        $calendar = new BusinessCalendar(new Provider(), $excluded);

        // When: 2020-01-01〜2020-01-13 の祝日を取得する
        $from = new \DateTimeImmutable('2020-01-01');
        $to = new \DateTimeImmutable('2020-01-13');
        $result = $calendar->holidays($from, $to);

        // Then: 除外設定に関わらず元日が含まれる（除外日付は holidays() に影響しない）
        $this->assertCount(2, $result);
        $this->assertSame('2020-01-01', $result[0]->getDate()->format('Y-m-d'));
        $this->assertSame('元日', $result[0]->getName());
    }
}
