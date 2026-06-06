<?php

declare(strict_types=1);

namespace Heijitu;

final class BusinessCalendar
{
    private HolidayProvider $provider;

    /** @var MonthDay[] */
    private array $excludedDates;

    /**
     * @param HolidayProvider $provider 祝日判定プロバイダー
     * @param array<mixed> $excludedDates MonthDay[] 除外日付リスト
     * @throws \InvalidArgumentException $excludedDates に MonthDay 以外が含まれる場合
     */
    public function __construct(HolidayProvider $provider, array $excludedDates = [])
    {
        foreach ($excludedDates as $item) {
            if (!$item instanceof MonthDay) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Each element of $excludedDates must be an instance of %s, %s given',
                        MonthDay::class,
                        is_object($item) ? get_class($item) : gettype($item)
                    )
                );
            }
        }

        $this->provider = $provider;
        $this->excludedDates = $excludedDates;
    }

    /**
     * 指定日が営業日かどうかを判定する
     *
     * @param \DateTimeImmutable $t 判定対象日
     * @param MonthDay ...$extraExcluded この呼び出しのみ有効な追加除外日付
     * @throws \Heijitu\Exception\ProviderException プロバイダーからの例外をそのまま伝播
     */
    public function isBusinessDay(\DateTimeImmutable $t, MonthDay ...$extraExcluded): bool
    {
        $dayOfWeek = (int) $t->format('N');
        if ($dayOfWeek === 6 || $dayOfWeek === 7) {
            return false;
        }

        if ($this->provider->isHoliday($t)) {
            return false;
        }

        if ($this->isExcluded($t, $this->excludedDates)) {
            return false;
        }

        if ($this->isExcluded($t, $extraExcluded)) {
            return false;
        }

        return true;
    }

    /**
     * @param MonthDay[] $dates
     */
    private function isExcluded(\DateTimeImmutable $t, array $dates): bool
    {
        foreach ($dates as $monthDay) {
            if ($monthDay->matches($t)) {
                return true;
            }
        }

        return false;
    }
}
