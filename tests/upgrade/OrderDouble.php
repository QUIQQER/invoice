<?php

declare(strict_types=1);

namespace QUITests\ERP\Accounting\Invoice\Upgrade;

final readonly class OrderDouble
{
    public function __construct(private string $uuid)
    {
    }

    public function getUUID(): string
    {
        return $this->uuid;
    }
}
