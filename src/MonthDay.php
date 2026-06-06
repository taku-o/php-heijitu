<?php

declare(strict_types=1);

namespace Heijitu;

final class MonthDay
{
    private int $month;
    private int $day;

    public function __construct(int $month, int $day)
    {
        $this->month = $month;
        $this->day   = $day;
    }

    public function getMonth(): int
    {
        return $this->month;
    }

    public function getDay(): int
    {
        return $this->day;
    }

    public function matches(\DateTimeImmutable $t): bool
    {
        return (int) $t->format('n') === $this->month
            && (int) $t->format('j') === $this->day;
    }
}
