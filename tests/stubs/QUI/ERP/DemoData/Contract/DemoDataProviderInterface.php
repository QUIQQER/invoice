<?php

namespace QUI\ERP\DemoData\Contract;

use Doctrine\DBAL\Connection;
use QUI\Locale;

if (!interface_exists(DemoDataProviderInterface::class, false)) {
    interface DemoDataProviderInterface
    {
        public function getIdentifier(): string;

        public function getTitle(?Locale $locale = null): string;

        public function getDemoDataCreator(Connection $connection): DemoDataCreatorInterface;
    }
}
