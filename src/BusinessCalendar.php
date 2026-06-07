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
     * 翌営業日を返す
     *
     * 戻り値は実行環境のデフォルト TZ（date_default_timezone_get()）で生成される。
     * $from が別の TZ を持つ場合は $from の Y-m-d を基準日として使用する。
     *
     * @param \DateTimeImmutable $from 起点日
     * @return \DateTimeImmutable 翌営業日（プロセスデフォルト TZ）
     * @throws \Heijitu\Exception\ProviderException プロバイダーからの例外をそのまま伝播
     */
    public function nextBusinessDay(\DateTimeImmutable $from): \DateTimeImmutable
    {
        $candidate = (new \DateTimeImmutable($from->format('Y-m-d'), $this->defaultTimezone()))
            ->modify('+1 day');

        return $this->findBusinessDayFrom($candidate);
    }

    /**
     * 指定年月の月初営業日を返す
     *
     * @param int $year 年
     * @param int $month 月（1〜12）
     * @return \DateTimeImmutable 月初営業日
     * @throws \Heijitu\Exception\ProviderException プロバイダーからの例外をそのまま伝播
     */
    public function firstBusinessDayOfMonth(int $year, int $month): \DateTimeImmutable
    {
        $candidate = new \DateTimeImmutable(
            sprintf('%04d-%02d-01', $year, $month),
            $this->defaultTimezone()
        );

        return $this->findBusinessDayFrom($candidate);
    }

    /**
     * 指定年の各月の月初営業日を返す
     *
     * @param int $year 年
     * @return \DateTimeImmutable[] 12件の月初営業日（index 0 = 1月、index 11 = 12月）
     * @throws \Heijitu\Exception\ProviderException プロバイダーからの例外をそのまま伝播
     */
    public function firstBusinessDaysOfYear(int $year): array
    {
        $result = [];
        for ($month = 1; $month <= 12; $month++) {
            $result[] = $this->firstBusinessDayOfMonth($year, $month);
        }

        return $result;
    }

    /**
     * 指定期間の祝日を返す
     *
     * @param \DateTimeImmutable $from 開始日
     * @param \DateTimeImmutable $to 終了日
     * @return Holiday[] 祝日リスト
     * @throws \Heijitu\Exception\ProviderException プロバイダーからの例外をそのまま伝播
     */
    public function holidays(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->provider->holidaysBetween($from, $to);
    }

    /**
     * 候補日から最初の営業日を見つけて返す
     *
     * プロバイダーのデータ範囲外（例: 2050年超）を起点とすると営業日が見つからず無限ループになる。
     *
     * @throws \Heijitu\Exception\ProviderException プロバイダーからの例外をそのまま伝播
     */
    private function findBusinessDayFrom(\DateTimeImmutable $candidate): \DateTimeImmutable
    {
        while (!$this->isBusinessDay($candidate)) {
            $candidate = $candidate->modify('+1 day');
        }

        return $candidate;
    }

    private function defaultTimezone(): \DateTimeZone
    {
        return new \DateTimeZone(date_default_timezone_get());
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
