<?php

declare(strict_types=1);

namespace Heijitu;

/**
 * 祝日の日付と名称を保持する不変値オブジェクト
 */
final class Holiday
{
    private \DateTimeImmutable $date;
    private string $name;

    /**
     * @param \DateTimeImmutable $date 祝日の日付
     * @param string             $name 祝日の名称
     */
    public function __construct(\DateTimeImmutable $date, string $name)
    {
        $this->date = $date;
        $this->name = $name;
    }

    /**
     * @return \DateTimeImmutable 祝日の日付
     */
    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    /**
     * @return string 祝日の名称
     */
    public function getName(): string
    {
        return $this->name;
    }
}
