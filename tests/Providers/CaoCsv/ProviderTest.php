<?php

declare(strict_types=1);

namespace Heijitu\Tests\Providers\CaoCsv;

use Heijitu\Holiday;
use Heijitu\HolidayProvider;
use Heijitu\Providers\CaoCsv\Provider;
use PHPUnit\Framework\TestCase;

final class ProviderTest extends TestCase
{
    private Provider $provider;

    protected function setUp(): void
    {
        $fixturePath = __DIR__ . '/testdata/syukujitsu_test.csv';
        $this->provider = new Provider($fixturePath);
    }

    // -------------------------------------------------------
    // インターフェース実装確認（要件 1.1）
    // -------------------------------------------------------

    public function testImplementsHolidayProviderInterface(): void
    {
        // Then: HolidayProvider インターフェースを実装している
        $this->assertInstanceOf(HolidayProvider::class, $this->provider);
    }

    // -------------------------------------------------------
    // 正常系: isHoliday — 祝日で true（要件 2.1）
    // -------------------------------------------------------

    public function testIsHolidayReturnsTrueForKnownHoliday(): void
    {
        // When: フィクスチャ内の既知祝日（2020-01-01 元日）で判定する
        $result = $this->provider->isHoliday(new \DateTimeImmutable('2020-01-01'));

        // Then: true を返す
        $this->assertTrue($result);
    }

    // -------------------------------------------------------
    // 正常系: isHoliday — 非祝日で false（要件 2.2）
    // -------------------------------------------------------

    public function testIsHolidayReturnsFalseForNonHoliday(): void
    {
        // When: 祝日でない日付（2020-01-02）で判定する
        $result = $this->provider->isHoliday(new \DateTimeImmutable('2020-01-02'));

        // Then: false を返す
        $this->assertFalse($result);
    }

    // -------------------------------------------------------
    // 正常系: holidayName — 祝日名を返す（要件 2.3）
    // -------------------------------------------------------

    public function testHolidayNameReturnsNameForKnownHoliday(): void
    {
        // When: フィクスチャ内の既知祝日（2020-01-01 元日）で名前を取得する
        $result = $this->provider->holidayName(new \DateTimeImmutable('2020-01-01'));

        // Then: '元日' を返す
        $this->assertSame('元日', $result);
    }

    // -------------------------------------------------------
    // 正常系: holidayName — 非祝日で空文字（要件 2.4）
    // -------------------------------------------------------

    public function testHolidayNameReturnsEmptyStringForNonHoliday(): void
    {
        // When: 祝日でない日付（2020-01-02）で名前を取得する
        $result = $this->provider->holidayName(new \DateTimeImmutable('2020-01-02'));

        // Then: 空文字を返す
        $this->assertSame('', $result);
    }

    // -------------------------------------------------------
    // 正常系: holidaysBetween — 範囲内祝日を昇順で返す（要件 2.5）
    // -------------------------------------------------------

    public function testHolidaysBetweenReturnsHolidaysInRangeAscending(): void
    {
        // When: 2020-01-01〜2020-01-13 の範囲で祝日を取得する
        $from = new \DateTimeImmutable('2020-01-01');
        $to = new \DateTimeImmutable('2020-01-13');
        $result = $this->provider->holidaysBetween($from, $to);

        // Then: 2件（元日・成人の日）を昇順で返す
        $this->assertCount(2, $result);

        $this->assertInstanceOf(Holiday::class, $result[0]);
        $this->assertSame('2020-01-01', $result[0]->getDate()->format('Y-m-d'));
        $this->assertSame('元日', $result[0]->getName());

        $this->assertInstanceOf(Holiday::class, $result[1]);
        $this->assertSame('2020-01-13', $result[1]->getDate()->format('Y-m-d'));
        $this->assertSame('成人の日', $result[1]->getName());
    }

    // -------------------------------------------------------
    // 正常系: holidaysBetween — from > to で空配列（要件 2.6）
    // -------------------------------------------------------

    public function testHolidaysBetweenReturnsEmptyArrayWhenFromAfterTo(): void
    {
        // When: from > to の範囲を指定する
        $from = new \DateTimeImmutable('2020-01-13');
        $to = new \DateTimeImmutable('2020-01-01');
        $result = $this->provider->holidaysBetween($from, $to);

        // Then: 空配列を返す
        $this->assertSame([], $result);
    }

    // -------------------------------------------------------
    // 境界値: holidaysBetween — 両端を含む（from == to で祝日1件）（要件 2.5）
    // -------------------------------------------------------

    public function testHolidaysBetweenIncludesBothEndpoints(): void
    {
        // When: from == to == 祝日で範囲を指定する
        $date = new \DateTimeImmutable('2020-01-01');
        $result = $this->provider->holidaysBetween($date, $date);

        // Then: 元日1件を返す
        $this->assertCount(1, $result);
        $this->assertSame('2020-01-01', $result[0]->getDate()->format('Y-m-d'));
        $this->assertSame('元日', $result[0]->getName());
    }

    // -------------------------------------------------------
    // 境界値: holidaysBetween — 範囲内に祝日がない場合（要件 2.5）
    // -------------------------------------------------------

    public function testHolidaysBetweenReturnsEmptyArrayWhenNoHolidaysInRange(): void
    {
        // When: 祝日のない範囲（2020-01-02〜2020-01-12）を指定する
        $from = new \DateTimeImmutable('2020-01-02');
        $to = new \DateTimeImmutable('2020-01-12');
        $result = $this->provider->holidaysBetween($from, $to);

        // Then: 空配列を返す
        $this->assertSame([], $result);
    }

    // -------------------------------------------------------
    // ローカルCSVモード確認（要件 1.2, 4.2）
    // -------------------------------------------------------

    public function testLocalCsvModeReadsFromSpecifiedFilePath(): void
    {
        // Given: ローカルフィクスチャのパスを指定してインスタンスを生成する
        $fixturePath = __DIR__ . '/testdata/syukujitsu_test.csv';
        $provider = new Provider($fixturePath);

        // When: フィクスチャ内の祝日で判定する
        $result = $provider->isHoliday(new \DateTimeImmutable('2020-02-11'));

        // Then: ローカルCSVから読み込んだデータで true を返す
        $this->assertTrue($result);
        $this->assertSame('建国記念の日', $provider->holidayName(new \DateTimeImmutable('2020-02-11')));
    }

    // -------------------------------------------------------
    // 境界値: holidaysBetween — 戻り値の Holiday::getDate() が DateTimeImmutable（要件 2.5）
    // -------------------------------------------------------

    public function testHolidaysBetweenReturnsDateTimeImmutableInHoliday(): void
    {
        // When: 祝日を含む範囲を指定する
        $from = new \DateTimeImmutable('2020-01-01');
        $to = new \DateTimeImmutable('2020-01-01');
        $result = $this->provider->holidaysBetween($from, $to);

        // Then: Holiday の date が DateTimeImmutable である
        $this->assertInstanceOf(\DateTimeImmutable::class, $result[0]->getDate());
    }

    // -------------------------------------------------------
    // インテグレーションテスト: オンライン取得モード（要件 1.3, 4.3）
    // -------------------------------------------------------

    /**
     * @group integration
     */
    public function testOnlineFetchModeWithoutCsvPath(): void
    {
        // Given: csvPath 未指定でインスタンスを生成する（内閣府URLからCSV取得）
        $provider = new Provider();

        // When: 既知の祝日で判定する（元日は毎年存在する）
        $result = $provider->isHoliday(new \DateTimeImmutable('2024-01-01'));

        // Then: オンライン取得したデータで true を返す
        $this->assertTrue($result);
    }
}
