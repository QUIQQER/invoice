<?php

namespace QUI\ERP\Shipping\Types;

use QUI\ERP\Shipping\Api\ShippingInterface;

if (!class_exists(ShippingEntry::class)) {
    class ShippingEntry implements ShippingInterface
    {
        public function getId(): int
        {
            return 0;
        }

        /**
         * @return array<string, mixed>
         */
        public function toJSON(): array
        {
            return [];
        }
    }
}
