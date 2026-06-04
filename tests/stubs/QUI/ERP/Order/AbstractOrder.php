<?php

namespace QUI\ERP\Order;

if (!class_exists(AbstractOrder::class)) {
    abstract class AbstractOrder
    {
        public function getAttribute(string $key): mixed
        {
            return null;
        }

        public function getAttributes(): array
        {
            return [];
        }

        public function getArticles(): \QUI\ERP\Accounting\ArticleList | \QUI\ERP\Accounting\ArticleListUnique
        {
            throw new \LogicException('PHPStan stub');
        }

        public function getId(): int
        {
            return 0;
        }

        public function getUUID(): string
        {
            return '';
        }

        public function getGlobalProcessId(): string
        {
            return '';
        }

        public function getPrefixedNumber(): string
        {
            return '';
        }

        public function getCustomer(): ?\QUI\ERP\User
        {
            return null;
        }

        public function getCurrency(): \QUI\ERP\Currency\Currency
        {
            throw new \LogicException('PHPStan stub');
        }

        public function getPriceCalculation(): \QUI\ERP\Accounting\Calculations
        {
            throw new \LogicException('PHPStan stub');
        }

        public function getDeliveryAddress(): ?\QUI\ERP\Address
        {
            return null;
        }

        public function setCustomer(array | \QUI\Interfaces\Users\User $User): void
        {
        }

        public function toArray(): array
        {
            return [];
        }

        public function reversal(
            string $reason = '',
            null | \QUI\Interfaces\Users\User $PermissionUser = null
        ): ?\QUI\ERP\ErpEntityInterface {
            return null;
        }

        public function addCustomerFile(string $fileHash, array $options = []): void
        {
        }

        public function clearCustomerFiles(): void
        {
        }

        public function getCustomerFiles(bool $parsing = false): array
        {
            return [];
        }

        public function getPayment(): mixed
        {
            return null;
        }

        public function getPaymentData(): array
        {
            return [];
        }

        /**
         *  array<string, mixed>
         */
        public function getPaidStatusInformation(): array
        {
            return [];
        }

        public function getCreateDate(): string
        {
            return "";
        }

        public function addHistory(string $message): void
        {
        }

        public function addFrontendMessage(string $message): void
        {
        }

        public function setAttribute(string $name, mixed $value): void
        {
        }

        public function setData(string $key, mixed $value): void
        {
        }

        public function setPaymentStatus(int $status, bool $force = false): void
        {
        }

        public function save(mixed $PermissionUser = null): void
        {
        }
    }
}
