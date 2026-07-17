<?php

namespace QUI\REST;

use QUI;

if (!interface_exists(ProviderInterface::class)) {
    interface ProviderInterface
    {
        public function register(Server $Server): void;

        public function getOpenApiDefinitionFile(): bool|string;

        public function getTitle(?QUI\Locale $Locale = null): string;

        public function getName(): string;
    }
}
