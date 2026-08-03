<?php

namespace QUI\ERP\DemoData\DTO;

if (!class_exists(DemoDataReference::class, false)) {
    final readonly class DemoDataReference
    {
        /**
         * @param array<string, mixed> $metadata
         */
        public function __construct(
            public string $providerIdentifier,
            public string $entityType,
            public string $entityUuid,
            public ?string $referenceKey,
            public array $metadata
        ) {
        }
    }
}
