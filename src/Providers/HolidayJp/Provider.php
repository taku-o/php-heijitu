<?php

declare(strict_types=1);

namespace Heijitu\Providers\HolidayJp;

use Heijitu\Exception\ProviderException;
use Heijitu\Holiday;
use Heijitu\HolidayProvider;
use HolidayJp\HolidayJp;

final class Provider implements HolidayProvider
{
    public function __construct()
    {
        if (!class_exists(HolidayJp::class)) {
            throw new ProviderException(
                'holiday-jp/holiday_jp パッケージが必要です。composer require holiday-jp/holiday_jp を実行してください。'
            );
        }
    }

    public function isHoliday(\DateTimeImmutable $t): bool
    {
        return HolidayJp::isHoliday($this->toMutableDate($t));
    }

    public function holidayName(\DateTimeImmutable $t): string
    {
        $entries = HolidayJp::between($this->toMutableDate($t), $this->toMutableDate($t));
        return !empty($entries) ? $entries[0]['name'] : '';
    }

    public function holidaysBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $entries = HolidayJp::between($this->toMutableDate($from), $this->toMutableDate($to));

        $holidays = [];
        foreach ($entries as $entry) {
            $date = \DateTimeImmutable::createFromMutable($entry['date']);
            $holidays[] = new Holiday($date, $entry['name']);
        }

        usort($holidays, function (Holiday $a, Holiday $b): int {
            return $a->getDate() <=> $b->getDate();
        });

        return $holidays;
    }

    private function toMutableDate(\DateTimeImmutable $dt): \DateTime
    {
        return new \DateTime($dt->format('Y-m-d'));
    }
}
