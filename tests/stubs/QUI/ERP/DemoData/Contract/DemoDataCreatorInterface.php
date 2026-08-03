<?php

namespace QUI\ERP\DemoData\Contract;

use QUI\ERP\DemoData\DTO\CreatedDemoDataCollection;
use QUI\ERP\DemoData\DTO\DemoDataCreationContext;
use QUI\ERP\DemoData\DTO\DemoDataReferenceCollection;

if (!interface_exists(DemoDataCreatorInterface::class, false)) {
    interface DemoDataCreatorInterface
    {
        /**
         * @return list<string>
         */
        public function getDependencies(): array;

        public function createDemoData(DemoDataCreationContext $context): CreatedDemoDataCollection;

        public function deleteDemoData(DemoDataReferenceCollection $demoData): void;
    }
}
