<?php

namespace QUI\ERP\DemoData\DTO;

if (!class_exists(DemoDataCreationContext::class, false)) {
    final readonly class DemoDataCreationContext
    {
        /**
         * @param list<DemoDataDateRange> $dateRanges
         */
        public function __construct(
            private DemoDataReferenceCollection $dependencyData,
            private array $dateRanges = []
        ) {
        }

        /**
         * @return list<DemoDataReference>
         */
        public function getDependencyData(string $providerIdentifier): array
        {
            return $this->dependencyData->forProvider($providerIdentifier);
        }

        /**
         * @return list<DemoDataDateRange>
         */
        public function getDateRanges(): array
        {
            return $this->dateRanges;
        }
    }
}
