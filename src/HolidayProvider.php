<?php

declare(strict_types=1);

namespace Heijitu;

interface HolidayProvider
{
    /**
     * @throws \Heijitu\Exception\ProviderException データ取得失敗時
     */
    public function isHoliday(\DateTimeImmutable $t): bool;

    /**
     * 非祝日のとき空文字 "" を返す（例外にしない）
     * @throws \Heijitu\Exception\ProviderException データ取得失敗時
     */
    public function holidayName(\DateTimeImmutable $t): string;

    /**
     * from〜to（両端含む）の祝日を日付昇順で返す
     * from > to のとき空配列を返す
     * @return Holiday[]
     * @throws \Heijitu\Exception\ProviderException データ取得失敗時
     */
    public function holidaysBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array;
}
