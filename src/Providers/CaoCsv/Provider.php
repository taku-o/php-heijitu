<?php

declare(strict_types=1);

namespace Heijitu\Providers\CaoCsv;

use Heijitu\Exception\ProviderException;
use Heijitu\Holiday;
use Heijitu\HolidayProvider;

final class Provider implements HolidayProvider
{
    private const CABINET_OFFICE_CSV_URL = 'https://www8.cao.go.jp/chosei/shukujitsu/syukujitsu.csv';

    /** @var array<string, string> キー: 'YYYY-MM-DD', 値: 祝日名 */
    private array $holidays;

    /**
     * @param string $csvPath ローカルCSVファイルパス。空文字のとき内閣府固定URLからオンライン取得。
     * @throws ProviderException ext-mbstring 未導入 / ファイル読み込み失敗 / HTTP 取得失敗
     */
    public function __construct(string $csvPath = '')
    {
        if (!extension_loaded('mbstring')) {
            throw new ProviderException(
                'ext-mbstring が必要です。PHP の mbstring 拡張をインストールしてください。'
            );
        }

        $content = $this->fetchCsvContent($csvPath);
        $this->holidays = $this->parseAndStore($content);
    }

    /** {@inheritdoc} */
    public function isHoliday(\DateTimeImmutable $t): bool
    {
        return isset($this->holidays[$this->dateKey($t)]);
    }

    /** {@inheritdoc} */
    public function holidayName(\DateTimeImmutable $t): string
    {
        return $this->holidays[$this->dateKey($t)] ?? '';
    }

    /** {@inheritdoc} */
    public function holidaysBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        if ($from > $to) {
            return [];
        }

        $fromKey = $this->dateKey($from);
        $toKey = $this->dateKey($to);

        $holidays = [];
        foreach ($this->holidays as $dateKey => $name) {
            if ($dateKey >= $fromKey && $dateKey <= $toKey) {
                $date = new \DateTimeImmutable($dateKey);
                $holidays[] = new Holiday($date, $name);
            }
        }

        usort($holidays, fn(Holiday $a, Holiday $b): int => $a->getDate() <=> $b->getDate());

        return $holidays;
    }

    private function dateKey(\DateTimeImmutable $t): string
    {
        return $t->format('Y-m-d');
    }

    /**
     * @throws ProviderException
     */
    private function fetchCsvContent(string $csvPath): string
    {
        if ($csvPath !== '') {
            $content = @file_get_contents($csvPath);
            if ($content === false) {
                $reason = error_get_last()['message'] ?? '';
                throw new ProviderException(
                    "CSVファイルの読み込みに失敗しました: {$csvPath}" . ($reason !== '' ? " ({$reason})" : '')
                );
            }
            return $content;
        }

        $content = @file_get_contents(self::CABINET_OFFICE_CSV_URL);
        if ($content === false) {
            $reason = error_get_last()['message'] ?? '';
            throw new ProviderException(
                '内閣府の祝日CSVの取得に失敗しました: ' . self::CABINET_OFFICE_CSV_URL . ($reason !== '' ? " ({$reason})" : '')
            );
        }
        return $content;
    }

    /**
     * @return array<string, string>
     */
    private function parseAndStore(string $content): array
    {
        $utf8Content = mb_convert_encoding($content, 'UTF-8', 'SJIS-win');
        $lines = explode("\n", $utf8Content);

        $holidays = [];
        $isFirstLine = true;
        foreach ($lines as $line) {
            $line = trim($line, "\r\n");
            if ($line === '') {
                continue;
            }

            if ($isFirstLine) {
                $isFirstLine = false;
                continue;
            }

            $row = str_getcsv($line);
            if (count($row) < 2) {
                continue;
            }

            $dateStr = trim($row[0]);
            $name = trim($row[1]);
            if ($dateStr === '' || $name === '') {
                continue;
            }

            $date = \DateTimeImmutable::createFromFormat('Y/m/d', $dateStr);
            $parseErrors = \DateTimeImmutable::getLastErrors();
            if ($date === false || ($parseErrors !== false && ($parseErrors['error_count'] > 0 || $parseErrors['warning_count'] > 0))) {
                continue;
            }

            $holidays[$date->format('Y-m-d')] = $name;
        }

        return $holidays;
    }
}
