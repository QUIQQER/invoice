<?php

namespace QUI\ERP\Order;

if (!class_exists(Handler::class)) {
    class Handler
    {
        public static function getInstance(): self
        {
            return new self();
        }

        public function get(int | string $orderId): Order
        {
            throw new \LogicException("PHPStan stub");
        }

        public function getOrderById(int | string $id): OrderInProcess | Order
        {
            throw new \LogicException("PHPStan stub");
        }

        public function getOrderByHash(string $hash): OrderInProcess | Order
        {
            throw new \LogicException("PHPStan stub");
        }

        public function getOrderByGlobalProcessId(int | string $id): Order
        {
            throw new \LogicException("PHPStan stub");
        }

        /**
         * @return array<string, mixed>
         */
        public function getOrderData(int | string $orderId): array
        {
            return [];
        }
    }
}
