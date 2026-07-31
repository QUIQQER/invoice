<?php

namespace QUI\ERP\DemoData\DTO;

if (!class_exists(CreatedDemoDataCollection::class, false)) {
    final readonly class CreatedDemoDataCollection
    {
        /**
         * @param list<CreatedDemoData> $items
         */
        public function __construct(private array $items)
        {
        }

        /**
         * @return list<CreatedDemoData>
         */
        public function all(): array
        {
            return $this->items;
        }
    }
}
