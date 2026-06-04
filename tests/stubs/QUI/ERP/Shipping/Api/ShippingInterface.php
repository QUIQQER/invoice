<?php

namespace QUI\ERP\Shipping\Api;

if (!interface_exists(ShippingInterface::class)) {
    interface ShippingInterface
    {
        public function getId(): int;
    }
}
