<?php

namespace QUI\ERP\DemoData\DTO;

if (!class_exists(CreatedDemoData::class, false)) {
    final readonly class CreatedDemoData
    {
        /**
         * @param array<string, mixed> $metadata
         */
        public function __construct(
            public string $entityType,
            public string $entityUuid,
            public ?string $referenceKey = null,
            public array $metadata = []
        ) {
        }
    }
}
