<?php

namespace QUI\ERP\Order;

use Doctrine\DBAL\Schema\Table;
use QUI;

if (!class_exists(Handler::class)) {
    class Handler
    {
        public static function getInstance(): self
        {
            return new self();
        }

        public function get(int | string $orderId): Order
        {
            return new Order($this->getOrderData($orderId));
        }

        public function getOrderById(int | string $id): OrderInProcess | Order
        {
            try {
                return $this->getOrderByHash((string)$id);
            } catch (QUI\Exception) {
                return $this->get($id);
            }
        }

        public function getOrderByHash(string $hash): OrderInProcess | Order
        {
            return new Order($this->findOrder('hash', $hash));
        }

        public function getOrderByGlobalProcessId(int | string $id): Order
        {
            return new Order($this->findOrder('global_process_id', $id));
        }

        /**
         * @return array<string, mixed>
         */
        public function getOrderData(int | string $orderId): array
        {
            return $this->findOrder('id', $orderId);
        }

        public function table(): string
        {
            $name = QUI::getDBTableName('invoice_test_orders');
            $SchemaManager = QUI::getDataBaseConnection()->createSchemaManager();

            if (!$SchemaManager->tablesExist([$name])) {
                $Table = new Table($name);
                $Table->addColumn('id', 'integer', ['autoincrement' => true]);
                $Table->setPrimaryKey(['id']);

                foreach (['hash', 'id_str', 'global_process_id', 'customerId', 'c_date', 'c_user', 'invoice_id'] as $column) {
                    $Table->addColumn($column, 'string', ['notnull' => false]);
                }

                foreach (['customer', 'addressInvoice', 'addressDelivery', 'articles', 'currency_data'] as $column) {
                    $Table->addColumn($column, 'text', ['notnull' => false]);
                }

                foreach (['status', 'paid_status', 'successful', 'payment_id'] as $column) {
                    $Table->addColumn($column, 'integer', ['notnull' => false]);
                }

                $SchemaManager->createTable($Table);
            }

            return $name;
        }

        /** @return array<string, mixed> */
        private function findOrder(string $column, int | string $value): array
        {
            $data = QUI::getDataBaseConnection()->createQueryBuilder()
                ->select('*')
                ->from($this->table())
                ->where($column . ' = :value')
                ->setParameter('value', $value)
                ->executeQuery()
                ->fetchAssociative();

            if ($data === false) {
                throw new QUI\Exception('The test order does not exist.');
            }

            return $data;
        }
    }
}
