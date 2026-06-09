<?php

declare(strict_types=1);

namespace Heijitu\Tests\Providers\GoogleCalendar;

use Heijitu\Exception\ProviderException;
use Heijitu\Holiday;
use Heijitu\HolidayProvider;
use Heijitu\Providers\GoogleCalendar\Provider;
use PHPUnit\Framework\TestCase;

final class ProviderTest extends TestCase
{
    // -------------------------------------------------------
    // コンストラクタ契約テスト（要件 2.4, 4.1）
    // -------------------------------------------------------

    public function testThrowsWhenNoCredentials(): void
    {
        $this->expectException(ProviderException::class);
        new Provider('', '');
    }

    // -------------------------------------------------------
    // HolidayProvider インターフェースの実装確認（要件 1.1）
    // -------------------------------------------------------

    public function testImplementsHolidayProviderInterface(): void
    {
        // When: API キーのみでインスタンスを生成する
        $provider = new Provider('dummy_key', '');

        // Then: HolidayProvider インターフェースを実装している
        $this->assertInstanceOf(HolidayProvider::class, $provider);
    }

    // -------------------------------------------------------
    // コンストラクタ: credentialsFile のみで構築成功（要件 2.2, 2.5）
    // -------------------------------------------------------

    public function testConstructsWithCredentialsFileOnly(): void
    {
        // When: apiKey 空・credentialsFile のみでインスタンスを生成する
        $provider = new Provider('', '/path/to/credentials.json');

        // Then: HolidayProvider として構築に成功する
        $this->assertInstanceOf(HolidayProvider::class, $provider);
    }

    // -------------------------------------------------------
    // コンストラクタ: 両方指定で構築成功（要件 2.5）
    // -------------------------------------------------------

    public function testConstructsWithBothApiKeyAndCredentialsFile(): void
    {
        // When: apiKey と credentialsFile の両方を指定してインスタンスを生成する
        $provider = new Provider('dummy_key', '/path/to/credentials.json');

        // Then: HolidayProvider として構築に成功する
        $this->assertInstanceOf(HolidayProvider::class, $provider);
    }

    // -------------------------------------------------------
    // holidaysBetween: from > to で空配列（要件 3.6）
    // -------------------------------------------------------

    public function testHolidaysBetweenReturnsEmptyWhenFromAfterTo(): void
    {
        // Given: API キーでインスタンスを生成する
        $provider = new Provider('dummy_key', '');
        $from = new \DateTimeImmutable('2024-01-10');
        $to   = new \DateTimeImmutable('2024-01-01');

        // When: from > to で holidaysBetween を呼び出す
        $result = $provider->holidaysBetween($from, $to);

        // Then: 空配列を返す
        $this->assertSame([], $result);
    }

    // -------------------------------------------------------
    // fetchEvents: 終日イベントが祝日として返される（要件 1.2, 1.4, 3.1）
    // -------------------------------------------------------

    public function testIsHolidayReturnsTrueForAllDayEvent(): void
    {
        // Given: 終日イベント1件を返すモックサービスを注入する
        $provider = $this->createProviderWithMockService(
            $this->createSinglePageEventList([
                $this->createAllDayEvent('2024-01-01', '元日'),
            ])
        );

        // When: 該当日で isHoliday を呼び出す
        $result = $provider->isHoliday(new \DateTimeImmutable('2024-01-01'));

        // Then: true を返す
        $this->assertTrue($result);
    }

    // -------------------------------------------------------
    // fetchEvents: 終日イベントがない日は非祝日（要件 3.2）
    // -------------------------------------------------------

    public function testIsHolidayReturnsFalseWhenNoEvents(): void
    {
        // Given: 空のイベントリストを返すモックサービスを注入する
        $provider = $this->createProviderWithMockService(
            $this->createSinglePageEventList([])
        );

        // When: isHoliday を呼び出す
        $result = $provider->isHoliday(new \DateTimeImmutable('2024-01-02'));

        // Then: false を返す
        $this->assertFalse($result);
    }

    // -------------------------------------------------------
    // fetchEvents: 祝日名が正しく返される（要件 3.3）
    // -------------------------------------------------------

    public function testHolidayNameReturnsEventSummary(): void
    {
        // Given: 終日イベント1件を返すモックサービスを注入する
        $provider = $this->createProviderWithMockService(
            $this->createSinglePageEventList([
                $this->createAllDayEvent('2024-01-01', '元日'),
            ])
        );

        // When: 該当日で holidayName を呼び出す
        $result = $provider->holidayName(new \DateTimeImmutable('2024-01-01'));

        // Then: イベントの summary が返る
        $this->assertSame('元日', $result);
    }

    // -------------------------------------------------------
    // fetchEvents: 非祝日で空文字を返す（要件 3.4）
    // -------------------------------------------------------

    public function testHolidayNameReturnsEmptyStringForNonHoliday(): void
    {
        // Given: 空のイベントリストを返すモックサービスを注入する
        $provider = $this->createProviderWithMockService(
            $this->createSinglePageEventList([])
        );

        // When: 非祝日で holidayName を呼び出す
        $result = $provider->holidayName(new \DateTimeImmutable('2024-01-02'));

        // Then: 空文字を返す
        $this->assertSame('', $result);
    }

    // -------------------------------------------------------
    // fetchEvents: 時刻指定イベント（非終日）は除外される（要件 1.4）
    // -------------------------------------------------------

    public function testFetchEventsFiltersOutNonAllDayEvents(): void
    {
        // Given: 終日イベントと時刻指定イベントが混在するリスト
        $provider = $this->createProviderWithMockService(
            $this->createSinglePageEventList([
                $this->createAllDayEvent('2024-01-01', '元日'),
                $this->createTimedEvent(),
            ])
        );

        // When: holidaysBetween で取得する
        $result = $provider->holidaysBetween(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-01-01')
        );

        // Then: 終日イベントの元日のみ返る
        $this->assertCount(1, $result);
        $this->assertSame('元日', $result[0]->getName());
    }

    // -------------------------------------------------------
    // fetchEvents: ページングで全件取得される（要件 1.3）
    // -------------------------------------------------------

    public function testFetchEventsPaginatesThroughAllPages(): void
    {
        // Given: 2ページにまたがるイベントリストを返すモックサービスを注入する
        $page1 = $this->createEventListWithToken(
            [$this->createAllDayEvent('2024-01-01', '元日')],
            'next_page_token'
        );
        $page2 = $this->createSinglePageEventList(
            [$this->createAllDayEvent('2024-01-08', '成人の日')]
        );
        $provider = $this->createProviderWithPaginatedMockService([$page1, $page2]);

        // When: holidaysBetween で範囲指定する
        $result = $provider->holidaysBetween(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-01-31')
        );

        // Then: 全ページのイベントが取得される
        $this->assertCount(2, $result);
        $this->assertSame('元日', $result[0]->getName());
        $this->assertSame('成人の日', $result[1]->getName());
    }

    // -------------------------------------------------------
    // fetchEvents: holidaysBetween が Holiday[] を昇順で返す（要件 3.5）
    // -------------------------------------------------------

    public function testHolidaysBetweenReturnsHolidaysInAscendingOrder(): void
    {
        // Given: 複数の終日イベントを返すモックサービスを注入する
        $provider = $this->createProviderWithMockService(
            $this->createSinglePageEventList([
                $this->createAllDayEvent('2024-01-08', '成人の日'),
                $this->createAllDayEvent('2024-01-01', '元日'),
            ])
        );

        // When: holidaysBetween を呼び出す
        $result = $provider->holidaysBetween(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-01-31')
        );

        // Then: 昇順ソートされた Holiday[] が返る
        $this->assertCount(2, $result);
        $this->assertHoliday($result[0], '2024-01-01', '元日');
        $this->assertHoliday($result[1], '2024-01-08', '成人の日');
    }

    // -------------------------------------------------------
    // fetchEvents: Holiday::getDate() が DateTimeImmutable（要件 5.5）
    // -------------------------------------------------------

    public function testHolidaysBetweenReturnsDateTimeImmutableInHoliday(): void
    {
        // Given: 終日イベント1件を返すモックサービスを注入する
        $provider = $this->createProviderWithMockService(
            $this->createSinglePageEventList([
                $this->createAllDayEvent('2024-01-01', '元日'),
            ])
        );

        // When: holidaysBetween を呼び出す
        $result = $provider->holidaysBetween(
            new \DateTimeImmutable('2024-01-01'),
            new \DateTimeImmutable('2024-01-01')
        );

        // Then: Holiday の date が DateTimeImmutable である
        $this->assertCount(1, $result);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result[0]->getDate());
    }

    // -------------------------------------------------------
    // fetchEvents: API 例外が ProviderException に変換される（要件 1.5）
    // -------------------------------------------------------

    public function testFetchEventsWrapsApiExceptionInProviderException(): void
    {
        // Given: listEvents が例外を throw するモックサービスを注入する
        $originalException = new \RuntimeException('API connection failed');
        $provider = $this->createProviderWithThrowingMockService($originalException);

        // Then: ProviderException が throw される
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessageMatches('/Google Calendar API/');

        // When: isHoliday を呼び出す
        $provider->isHoliday(new \DateTimeImmutable('2024-01-01'));
    }

    // -------------------------------------------------------
    // fetchEvents: ProviderException に元例外が $previous として連鎖される（要件 1.5）
    // -------------------------------------------------------

    public function testProviderExceptionChainsPreviousException(): void
    {
        // Given: listEvents が例外を throw するモックサービスを注入する
        $originalException = new \RuntimeException('API connection failed');
        $provider = $this->createProviderWithThrowingMockService($originalException);

        // When: isHoliday を呼び出して ProviderException を捕捉する
        try {
            $provider->isHoliday(new \DateTimeImmutable('2024-01-01'));
            $this->fail('ProviderException が throw されるべき');
        } catch (ProviderException $e) {
            // Then: 元例外が $previous として連鎖されている
            $this->assertSame($originalException, $e->getPrevious());
        }
    }

    // -------------------------------------------------------
    // fetchEvents: buildService のキャッシュが機能する（要件 1.2）
    // -------------------------------------------------------

    public function testBuildServiceCachesServiceInstance(): void
    {
        // Given: listEvents が正確に2回呼ばれることを期待するモックサービスを注入する
        $eventList = $this->createSinglePageEventList([]);
        $mockEvents = $this->createMock(\Google\Service\Calendar\Resource\Events::class);
        $mockEvents->expects($this->exactly(2))
                   ->method('listEvents')
                   ->willReturn($eventList);
        $mockService = new \stdClass();
        $mockService->events = $mockEvents;
        $provider = $this->injectMockService(new Provider('dummy_key', ''), $mockService);

        // When/Then: isHoliday を2回呼んでもキャッシュされた同一サービスが使われる
        $provider->isHoliday(new \DateTimeImmutable('2024-01-01'));
        $provider->isHoliday(new \DateTimeImmutable('2024-01-02'));
    }

    // -------------------------------------------------------
    // ヘルパー: Holiday アサーション
    // -------------------------------------------------------

    private function assertHoliday(object $holiday, string $date, string $name): void
    {
        $this->assertInstanceOf(Holiday::class, $holiday);
        $this->assertSame($date, $holiday->getDate()->format('Y-m-d'));
        $this->assertSame($name, $holiday->getName());
    }

    // -------------------------------------------------------
    // ヘルパー: モックサービスを注入した Provider を生成
    // -------------------------------------------------------

    /**
     * 単一ページのイベントリストを返すモックサービスを注入した Provider を返す。
     *
     * @param \Google\Service\Calendar\Events $eventList
     */
    private function createProviderWithMockService(object $eventList): Provider
    {
        $mockService = $this->createMockCalendarService($eventList);
        return $this->injectMockService(new Provider('dummy_key', ''), $mockService);
    }

    /**
     * 複数ページのイベントリストを順次返すモックサービスを注入した Provider を返す。
     *
     * @param \Google\Service\Calendar\Events[] $eventLists
     */
    private function createProviderWithPaginatedMockService(array $eventLists): Provider
    {
        $mockEvents = $this->createMock(\Google\Service\Calendar\Resource\Events::class);
        $mockEvents->method('listEvents')
            ->willReturnOnConsecutiveCalls(...$eventLists);

        $mockService = new \stdClass();
        $mockService->events = $mockEvents;

        return $this->injectMockService(new Provider('dummy_key', ''), $mockService);
    }

    /**
     * listEvents が例外を throw するモックサービスを注入した Provider を返す。
     */
    private function createProviderWithThrowingMockService(\Throwable $exception): Provider
    {
        $mockEvents = $this->createMock(\Google\Service\Calendar\Resource\Events::class);
        $mockEvents->method('listEvents')
            ->willThrowException($exception);

        $mockService = new \stdClass();
        $mockService->events = $mockEvents;

        return $this->injectMockService(new Provider('dummy_key', ''), $mockService);
    }

    /**
     * リフレクションで $service プロパティにモックを注入する。
     */
    private function injectMockService(Provider $provider, object $mockService): Provider
    {
        $ref = new \ReflectionProperty(Provider::class, 'service');
        $ref->setAccessible(true);
        $ref->setValue($provider, $mockService);

        return $provider;
    }

    /**
     * 単一ページ（nextPageToken = null）のモック CalendarService を生成する。
     *
     * @param \Google\Service\Calendar\Events $eventList
     * @return object
     */
    private function createMockCalendarService(object $eventList): object
    {
        $mockEvents = $this->createMock(\Google\Service\Calendar\Resource\Events::class);
        $mockEvents->method('listEvents')
            ->willReturn($eventList);

        $mockService = new \stdClass();
        $mockService->events = $mockEvents;

        return $mockService;
    }

    // -------------------------------------------------------
    // ヘルパー: モック Event / EventList の生成
    // -------------------------------------------------------

    /**
     * 終日イベント（start.date が設定されている）のモックを生成する。
     */
    private function createAllDayEvent(string $date, string $summary): object
    {
        $start = $this->createMock(\Google\Service\Calendar\EventDateTime::class);
        $start->method('getDate')->willReturn($date);

        $event = $this->createMock(\Google\Service\Calendar\Event::class);
        $event->method('getStart')->willReturn($start);
        $event->method('getSummary')->willReturn($summary);

        return $event;
    }

    /**
     * 時刻指定イベント（start.date が null）のモックを生成する。
     */
    private function createTimedEvent(): object
    {
        $start = $this->createMock(\Google\Service\Calendar\EventDateTime::class);
        $start->method('getDate')->willReturn(null);

        $event = $this->createMock(\Google\Service\Calendar\Event::class);
        $event->method('getStart')->willReturn($start);

        return $event;
    }

    /**
     * nextPageToken = null の単一ページ EventList モックを生成する。
     *
     * @param object[] $events
     */
    private function createSinglePageEventList(array $events): object
    {
        return $this->createEventListWithToken($events, null);
    }

    /**
     * 指定した nextPageToken を持つ EventList モックを生成する。
     *
     * @param object[] $events
     * @param string|null $nextPageToken
     */
    private function createEventListWithToken(array $events, ?string $nextPageToken): object
    {
        $eventList = $this->createMock(\Google\Service\Calendar\Events::class);
        $eventList->method('getItems')->willReturn($events);
        $eventList->method('getNextPageToken')->willReturn($nextPageToken);

        return $eventList;
    }
}
