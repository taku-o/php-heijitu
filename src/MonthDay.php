<?php

declare(strict_types=1);

namespace Heijitu;

/**
 * 年をまたいで有効な月日を表す不変値オブジェクト。バリデーションなし
 */
final class MonthDay
{
    private int $month;
    private int $day;

    /**
     * @param int $month 月（1〜12）
     * @param int $day   日（1〜31）
     */
    public function __construct(int $month, int $day)
    {
        $this->month = $month;
        $this->day   = $day;
    }

    /**
     * @return int 月（1〜12）
     */
    public function getMonth(): int
    {
        return $this->month;
    }

    /**
     * @return int 日（1〜31）
     */
    public function getDay(): int
    {
        return $this->day;
    }

    /**
     * @param \DateTimeImmutable $t 比較対象の日付
     * @return bool この月日と一致するとき true
     */
    public function matches(\DateTimeImmutable $t): bool
    {
        return (int) $t->format('n') === $this->month
            && (int) $t->format('j') === $this->day;
    }
}
