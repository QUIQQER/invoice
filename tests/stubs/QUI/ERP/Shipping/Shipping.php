<?php

namespace QUI\ERP\Shipping;

use QUI\ERP\Shipping\Types\ShippingEntry;

if (!class_exists(Shipping::class)) {
    class Shipping
    {
        public static function getInstance(): self
        {
            return new self();
        }

        public function getShippingEntry(int $shippingId): ShippingEntry
        {
            return new ShippingEntry();
        }
    }
}
