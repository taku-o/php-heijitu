<?php

declare(strict_types=1);

namespace Heijitu\Tests;

use Heijitu\Holiday;
use PHPUnit\Framework\TestCase;

final class HolidayTest extends TestCase
{
    public function testGetDateReturnsConstructorValue(): void
    {
        // Given: 2025-01-01 の Holiday を生成する
        $date = new \DateTimeImmutable('2025-01-01');
        $holiday = new Holiday($date, '元日');

        // Then: getDate() がコンストラクタに渡した DateTimeImmutable を返す
        $this->assertSame($date, $holiday->getDate());
    }

    public function testGetNameReturnsConstructorValue(): void
    {
        // Given: 名前「元日」の Holiday を生成する
        $date = new \DateTimeImmutable('2025-01-01');
        $holiday = new Holiday($date, '元日');

        // Then: getName() が「元日」を返す
        $this->assertSame('元日', $holiday->getName());
    }

    public function testHolidayWithEmptyName(): void
    {
        // Given: 空文字の名前で Holiday を生成する
        $date = new \DateTimeImmutable('2025-01-01');
        $holiday = new Holiday($date, '');

        // Then: 空文字が返る
        $this->assertSame('', $holiday->getName());
    }

    public function testHolidayWithMultibyteCharacterName(): void
    {
        // Given: マルチバイト文字を含む名前で Holiday を生成する
        $date = new \DateTimeImmutable('2025-02-11');
        $holiday = new Holiday($date, '建国記念の日');

        // Then: マルチバイト文字の名前が正しく返る
        $this->assertSame('建国記念の日', $holiday->getName());
    }
}
