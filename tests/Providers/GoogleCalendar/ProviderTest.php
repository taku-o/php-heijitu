<?php

declare(strict_types=1);

namespace Heijitu\Tests\Providers\GoogleCalendar;

use Heijitu\Exception\ProviderException;
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
}
