<?php

declare(strict_types=1);

namespace Heijitu\Providers\HolidayJp;

use Heijitu\Exception\ProviderException;
use Heijitu\Holiday;
use Heijitu\HolidayProvider;
use HolidayJp\HolidayJp;

/**
 * holiday-jp/holiday_jp を使用した祝日プロバイダー。
 * データは 2020 年で更新停止しており 2021 年以降の祝日変更に未対応。
 * 本番用途では CaoCsv または GoogleCalendar プロバイダーの使用を推奨。
 */
final class Provider implements HolidayProvider
{
    /**
     * @throws ProviderException holiday-jp/holiday_jp パッケージ未導入時
     */
    public function __construct()
    {
        if (!class_exists(HolidayJp::class)) {
            throw new ProviderException(
                'holiday-jp/holiday_jp パッケージが必要です。composer require holiday-jp/holiday_jp を実行してください。'
            );
        }
    }

    /** {@inheritdoc} */
    public function isHoliday(\DateTimeImmutable $t): bool
    {
        return HolidayJp::isHoliday($this->toMutableDate($t));
    }

    /** {@inheritdoc} */
    public function holidayName(\DateTimeImmutable $t): string
    {
        $entries = HolidayJp::between($this->toMutableDate($t), $this->toMutableDate($t));
        return !empty($entries) ? $entries[0]['name'] : '';
    }

    /** {@inheritdoc} */
    public function holidaysBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $entries = HolidayJp::between($this->toMutableDate($from), $this->toMutableDate($to));

        $holidays = [];
        foreach ($entries as $entry) {
            $date = \DateTimeImmutable::createFromMutable($entry['date']);
            $holidays[] = new Holiday($date, $entry['name']);
        }

        usort($holidays, fn(Holiday $a, Holiday $b): int => $a->getDate() <=> $b->getDate());

        return $holidays;
    }

    private function toMutableDate(\DateTimeImmutable $dt): \DateTime
    {
        return new \DateTime($dt->format('Y-m-d'));
    }
}
