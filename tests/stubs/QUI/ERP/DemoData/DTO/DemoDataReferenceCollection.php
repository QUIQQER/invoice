<?php

namespace QUI\ERP\DemoData\DTO;

if (!class_exists(DemoDataReferenceCollection::class, false)) {
    final readonly class DemoDataReferenceCollection
    {
        /**
         * @param array<string, list<DemoDataReference>> $referencesByProvider
         */
        public function __construct(private array $referencesByProvider = [])
        {
        }

        /**
         * @return list<DemoDataReference>
         */
        public function forProvider(string $providerIdentifier): array
        {
            return $this->referencesByProvider[$providerIdentifier] ?? [];
        }
    }
}
