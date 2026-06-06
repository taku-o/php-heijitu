<?php

declare(strict_types=1);

namespace Heijitu;

final class Holiday
{
    private \DateTimeImmutable $date;
    private string $name;

    public function __construct(\DateTimeImmutable $date, string $name)
    {
        $this->date = $date;
        $this->name = $name;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
