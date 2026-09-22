<?php

declare(strict_types=1);

namespace QUITests\ERP\Accounting\Invoice\Upgrade;

final class OrderHandlerDouble
{
    /** @var array<string, string> */
    private static array $orderUuids = [];

    public static function getInstance(): self
    {
        return new self();
    }

    public static function setOrderUuid(int|string $legacyId, string $uuid): void
    {
        self::$orderUuids[(string)$legacyId] = $uuid;
    }

    public static function reset(): void
    {
        self::$orderUuids = [];
    }

    public function getOrderById(int|string $legacyId): OrderDouble
    {
        $legacyId = (string)$legacyId;

        if (!isset(self::$orderUuids[$legacyId])) {
            throw new \QUI\Exception('The upgrade test order does not exist.');
        }

        return new OrderDouble(self::$orderUuids[$legacyId]);
    }
}
