<?php

namespace QUI\ERP\DemoData\DTO;

use DateTimeImmutable;

if (!class_exists(DemoDataDateRange::class, false)) {
    final readonly class DemoDataDateRange
    {
        public function __construct(
            public DateTimeImmutable $startDate,
            public DateTimeImmutable $endDate
        ) {
        }
    }
}
