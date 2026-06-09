<?php

declare(strict_types=1);

namespace Heijitu\Tests\Providers;

use Heijitu\Holiday;
use PHPUnit\Framework\TestCase;

abstract class ProviderTestCase extends TestCase
{
    protected function assertHoliday(object $holiday, string $date, string $name): void
    {
        $this->assertInstanceOf(Holiday::class, $holiday);
        $this->assertSame($date, $holiday->getDate()->format('Y-m-d'));
        $this->assertSame($name, $holiday->getName());
    }
}
