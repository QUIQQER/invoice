<?php

namespace QUI\ERP\Shipping\Types;

use QUI\ERP\Shipping\Api\ShippingInterface;

if (!class_exists(ShippingUnique::class)) {
    class ShippingUnique implements ShippingInterface
    {
        /**
         * @param array<string, mixed> $data
         */
        public function __construct(array $data = [])
        {
        }

        public function getId(): int
        {
            return 0;
        }
    }
}
