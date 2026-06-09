<?php

declare(strict_types=1);

namespace Heijitu\Providers\GoogleCalendar;

use Heijitu\Exception\ProviderException;
use Heijitu\Holiday;
use Heijitu\HolidayProvider;

final class Provider implements HolidayProvider
{
    private const CALENDAR_ID = 'ja.japanese.official#holiday@group.v.calendar.google.com';

    private string $apiKey;

    private string $credentialsFile;

    private ?object $service = null;

    /**
     * @param string $apiKey          GCP で発行した API キー。credentialsFile が指定された場合は無視される。
     * @param string $credentialsFile サービスアカウント JSON キーファイルのパス。指定時は apiKey より優先される。
     * @throws ProviderException google/apiclient 未導入 / apiKey と credentialsFile が両方空
     */
    public function __construct(string $apiKey = '', string $credentialsFile = '')
    {
        if (!class_exists(\Google\Client::class)) {
            throw new ProviderException(
                'google/apiclient パッケージが必要です。composer require google/apiclient を実行してください。'
            );
        }

        if ($apiKey === '' && $credentialsFile === '') {
            throw new ProviderException(
                '認証情報が未設定です。apiKey または credentialsFile を指定してください。'
            );
        }

        $this->apiKey = $apiKey;
        $this->credentialsFile = $credentialsFile;
    }

    public function isHoliday(\DateTimeImmutable $t): bool
    {
        $events = $this->fetchEvents($t, $t);
        return isset($events[$this->dateKey($t)]);
    }

    public function holidayName(\DateTimeImmutable $t): string
    {
        $events = $this->fetchEvents($t, $t);
        return $events[$this->dateKey($t)] ?? '';
    }

    /** @return Holiday[] */
    public function holidaysBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        if ($from > $to) {
            return [];
        }

        $events = $this->fetchEvents($from, $to);

        $holidays = [];
        foreach ($events as $dateKey => $name) {
            $date = new \DateTimeImmutable($dateKey);
            $holidays[] = new Holiday($date, $name);
        }

        usort($holidays, fn(Holiday $a, Holiday $b): int => $a->getDate() <=> $b->getDate());

        return $holidays;
    }

    /** @return \Google\Service\Calendar */
    private function buildService(): object
    {
        if ($this->service !== null) {
            return $this->service;
        }

        $client = new \Google\Client();

        if ($this->credentialsFile !== '') {
            $client->setAuthConfig($this->credentialsFile);
            $client->addScope(\Google\Service\Calendar::CALENDAR_READONLY);
        } else {
            $client->setDeveloperKey($this->apiKey);
        }

        $this->service = new \Google\Service\Calendar($client);
        return $this->service;
    }

    /**
     * $from〜$to（両端含む）の終日イベントを Google Calendar API から全ページ取得する。
     *
     * @return array<string, string> キー: 'YYYY-MM-DD', 値: 祝日名
     * @throws ProviderException API 呼び出し失敗
     */
    private function fetchEvents(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        try {
            $service = $this->buildService();

            $timeMin = $from->setTime(0, 0, 0)->format('c');
            $timeMax = $to->modify('+1 day')->setTime(0, 0, 0)->format('c');

            $params = [
                'singleEvents' => true,
                'orderBy' => 'startTime',
                'maxResults' => 2500,
                'timeMin' => $timeMin,
                'timeMax' => $timeMax,
            ];

            $holidays = [];

            while (true) {
                $eventList = $service->events->listEvents(self::CALENDAR_ID, $params);

                foreach ($eventList->getItems() as $event) {
                    $startDate = $event->getStart()->getDate();
                    if ($startDate !== null) {
                        $holidays[$startDate] = $event->getSummary() ?? '';
                    }
                }

                $pageToken = $eventList->getNextPageToken();
                if ($pageToken === null) {
                    break;
                }
                $params['pageToken'] = $pageToken;
            }

            return $holidays;
        } catch (\Throwable $e) {
            throw new ProviderException(
                'Google Calendar API の呼び出しに失敗しました: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function dateKey(\DateTimeImmutable $t): string
    {
        return $t->format('Y-m-d');
    }
}
