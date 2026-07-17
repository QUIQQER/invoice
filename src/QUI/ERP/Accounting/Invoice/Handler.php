<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Handler
 */

namespace QUI\ERP\Accounting\Invoice;

use Doctrine\DBAL\Query\QueryBuilder;
use QUI;
use QUI\Utils\Doctrine;

use function count;
use function explode;
use function in_array;
use function is_numeric;
use function is_string;
use function mb_strtoupper;
use function str_replace;

/**
 * Class Handler
 * - Maintains invoices
 * - Returns invoices
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class Handler extends QUI\Utils\Singleton
{
    /**
     * @var string
     */
    const EMPTY_VALUE = '---';

    /**
     * @var string
     */
    const TABLE_INVOICE = 'invoice';

    /**
     * @var string
     */
    const TABLE_TEMPORARY_INVOICE = 'invoice_temporary';

    /**
     * Tables
     */

    /**
     * Return the invoice table name
     *
     * @return string
     */
    public function invoiceTable(): string
    {
        return QUI::getDBTableName(self::TABLE_INVOICE);
    }

    /**
     * Return all invoices by a user
     *
     * @param QUI\Users\User $User
     * @return Invoice[]
     */
    public function getInvoicesByUser(QUI\Users\User $User): array
    {
        $result = [];

        try {
            $invoiceIds = QUI::getDataBaseConnection()->createQueryBuilder()
                ->select(Doctrine::quoteIdentifier('id'))
                ->from(Doctrine::quoteIdentifier(self::invoiceTable()))
                ->where(Doctrine::quoteIdentifier('customer_id') . ' = :customerId')
                ->setParameter('customerId', $User->getUUID())
                ->executeQuery()
                ->fetchFirstColumn();
        } catch (\Exception) {
            return [];
        }

        foreach ($invoiceIds as $invoiceId) {
            try {
                $result[] = $this->getInvoice($invoiceId);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }
        }

        return $result;
    }

    /**
     * Delete a temporary invoice
     * Only temporary invoices are deletable
     *
     * @param string|int $invoiceId - ID of a temporary Invoice
     * @param QUI\Interfaces\Users\User|null $User
     *
     * @throws QUI\Permissions\Exception
     * @throws QUI\Lock\Exception
     * @throws QUI\Exception
     */
    public function delete(string | int $invoiceId, null | QUI\Interfaces\Users\User $User = null): void
    {
        $Invoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getInvoiceByString($invoiceId);

        if (!($Invoice instanceof InvoiceTemporary)) {
            $Invoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getTemporaryInvoiceByString($invoiceId);
        }

        $Invoice->delete($User);
    }

    /**
     * GET methods
     */

    /**
     * Search for invoices
     *
     * @param array<string, mixed> $params - search params
     * @return list<array<string, mixed>>
     *
     * @throws QUI\DataBase\Exception
     */
    public function search(array $params = []): array
    {
        $params['limit'] ??= 20;
        $params['order'] ??= 'id DESC';

        return $this->createSearchQueryBuilder($this->invoiceTable(), $params)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * Count the invoices
     *
     * @param array<string, mixed> $queryParams - optional
     * @return int
     *
     * @throws QUI\DataBase\Exception
     */
    public function count(array $queryParams = []): int
    {
        return (int)$this->createSearchQueryBuilder($this->invoiceTable(), $queryParams, true)
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Search for temporary invoices
     *
     * @param array<string, mixed> $params - search params
     * @return list<array<string, mixed>>
     */
    public function searchTemporaryInvoices(array $params = []): array
    {
        $params['limit'] ??= 20;

        if (isset($params['order']) && !$this->canBeUseAsOrderField($params['order'])) {
            unset($params['order']);
        }

        try {
            return $this->createSearchQueryBuilder($this->temporaryInvoiceTable(), $params)
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * Count the invoices
     *
     * @param array<string, mixed> $queryParams - optional
     * @return int
     *
     * @throws QUI\DataBase\Exception
     */
    public function countTemporaryInvoices(array $queryParams = []): int
    {
        return (int)$this->createSearchQueryBuilder($this->temporaryInvoiceTable(), $queryParams, true)
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Return an Invoice
     * Alias for getInvoice()
     *
     * @param int|string $id - ID of the Invoice or InvoiceTemporary
     * @return InvoiceTemporary|Invoice
     *
     * @throws QUI\Exception
     */
    public function get(int | string $id): Invoice | InvoiceTemporary
    {
        try {
            return $this->getInvoice($id);
        } catch (QUI\Exception) {
        }

        return $this->getTemporaryInvoice($id);
    }

    /**
     * Return an Invoice
     *
     * @param int|string $id - ID of the Invoice
     * @return Invoice
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function getInvoice(int | string $id): Invoice
    {
        return new Invoice($id, $this);
    }

    /**
     * Return an Invoice by hash
     *
     * @param string $hash - Hash of the Invoice
     * @return Invoice|InvoiceTemporary
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function getInvoiceByHash(string $hash): Invoice | InvoiceTemporary
    {
        $hash = QUI\Utils\Security\Orthos::clear($hash);

        $Connection = QUI::getDataBaseConnection();
        $invoiceId = $Connection->createQueryBuilder()
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier(self::invoiceTable()))
            ->where(Doctrine::quoteIdentifier('hash') . ' = :hash')
            ->setParameter('hash', $hash)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        if ($invoiceId !== false) {
            return $this->getInvoice($invoiceId);
        }

        $invoiceId = $Connection->createQueryBuilder()
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier(self::temporaryInvoiceTable()))
            ->where(Doctrine::quoteIdentifier('hash') . ' = :hash')
            ->setParameter('hash', $hash)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        if ($invoiceId !== false) {
            return $this->getTemporaryInvoice($invoiceId);
        }

        throw new Exception(
            ['quiqqer/invoice', 'exception.invoice.not.found', ['id' => $hash]],
            404
        );
    }

    /**
     * Return the data from an invoice
     *
     * @param integer|string $id
     * @return array<string, mixed>
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function getInvoiceData(int | string $id): array
    {
        // check invoice via hash
        $Connection = QUI::getDataBaseConnection();
        $result = $Connection->createQueryBuilder()
            ->select('*')
            ->from(Doctrine::quoteIdentifier(self::invoiceTable()))
            ->where(Doctrine::quoteIdentifier('hash') . ' = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if ($result !== false) {
            return $this->normalizeInvoiceData($result);
        }

        // check invoice via old ids
        $whereOr = [
            'id_with_prefix' => $id
        ];

        $idSanitized = str_replace(Settings::getInstance()->getInvoicePrefix(), '', (string)$id);

        if (is_numeric($idSanitized)) {
            $whereOr['id'] = (int)$idSanitized;
        }

        $QueryBuilder = $Connection->createQueryBuilder()
            ->select('*')
            ->from(Doctrine::quoteIdentifier(self::invoiceTable()))
            ->setMaxResults(1);
        $orConditions = [];

        foreach ($whereOr as $field => $value) {
            $parameter = 'invoice_' . $field;
            $orConditions[] = Doctrine::quoteIdentifier($field) . ' = :' . $parameter;
            $QueryBuilder->setParameter($parameter, $value);
        }

        $result = $QueryBuilder
            ->where($QueryBuilder->expr()->or(...$orConditions))
            ->executeQuery()
            ->fetchAssociative();

        if ($result === false) {
            throw new Exception(
                ['quiqqer/invoice', 'exception.invoice.not.found', ['id' => $id]],
                404
            );
        }

        return $this->normalizeInvoiceData($result);
    }

    //region temporary

    /**
     * Return the temporary invoice table name
     *
     * @return string
     */
    public function temporaryInvoiceTable(): string
    {
        return QUI::getDBTableName(self::TABLE_TEMPORARY_INVOICE);
    }

    /**
     * Return a temporary invoice
     *
     * @param int|string $id - ID / Hash of the Invoice
     * @return InvoiceTemporary
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function getTemporaryInvoice(int | string $id): InvoiceTemporary
    {
        return new InvoiceTemporary($id, $this);
    }

    /**
     * Return an Invoice by hash
     *
     * @param string $hash - Hash of the Invoice
     * @return InvoiceTemporary
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function getTemporaryInvoiceByHash(string $hash): InvoiceTemporary
    {
        $invoiceId = QUI::getDataBaseConnection()->createQueryBuilder()
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier(self::temporaryInvoiceTable()))
            ->where(Doctrine::quoteIdentifier('hash') . ' = :hash')
            ->setParameter('hash', $hash)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();

        $hash = QUI\Utils\Security\Orthos::clear($hash);

        if ($invoiceId === false) {
            throw new Exception(
                ['quiqqer/invoice', 'exception.temporary.invoice.not.found', ['id' => $hash]],
                404
            );
        }

        return $this->getTemporaryInvoice($invoiceId);
    }

    /**
     * Return the data from a temporary invoice
     *
     * @param int|string $id
     * @return array<string, mixed>
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function getTemporaryInvoiceData(int | string $id): array
    {
        $Connection = QUI::getDataBaseConnection();
        $result = $Connection->createQueryBuilder()
            ->select('*')
            ->from(Doctrine::quoteIdentifier(self::temporaryInvoiceTable()))
            ->where(Doctrine::quoteIdentifier('hash') . ' = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if ($result === false) {
            $prefix = Settings::getInstance()->getTemporaryInvoicePrefix();
            $id = QUI\Utils\Security\Orthos::clear((string)$id);

            $result = $Connection->createQueryBuilder()
                ->select('*')
                ->from(Doctrine::quoteIdentifier(self::temporaryInvoiceTable()))
                ->where(Doctrine::quoteIdentifier('id') . ' = :id')
                ->setParameter('id', (int)str_replace($prefix, '', $id))
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchAssociative();
        }

        if ($result === false) {
            throw new Exception(
                ['quiqqer/invoice', 'exception.temporary.invoice.not.found', ['id' => $id]],
                404
            );
        }

        $canceled = null;

        if (isset($result['canceled'])) {
            $canceled = (int)$result['canceled'];
        }

        $result['id'] = (int)$result['id'];
        $result['isbrutto'] = (int)$result['isbrutto'];
        $result['paid_status'] = (int)$result['paid_status'];
        $result['processing_status'] = (int)$result['processing_status'];
        $result['time_for_payment'] = (int)$result['time_for_payment'];
        $result['canceled'] = $canceled;

        $result['nettosum'] = (float)$result['nettosum'];
        $result['subsum'] = (float)$result['subsum'];
        $result['sum'] = (float)$result['sum'];

        return $result;
    }

    //endregion

    /**
     * Return all invoices from a process id
     *
     * @param string $processId
     * @return Invoice[]|InvoiceTemporary[]
     *
     * @throws QUI\DataBase\Exception
     */
    public function getInvoicesByGlobalProcessId(string $processId): array
    {
        $result = [];

        $Connection = QUI::getDataBaseConnection();
        $invoiceIds = $Connection->createQueryBuilder()
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier(self::invoiceTable()))
            ->where(Doctrine::quoteIdentifier('global_process_id') . ' = :processId')
            ->setParameter('processId', $processId)
            ->executeQuery()
            ->fetchFirstColumn();

        foreach ($invoiceIds as $invoiceId) {
            try {
                $result[] = $this->get($invoiceId);
            } catch (QUI\Exception) {
            }
        }

        $invoiceIds = $Connection->createQueryBuilder()
            ->select(Doctrine::quoteIdentifier('id'))
            ->from(Doctrine::quoteIdentifier(self::temporaryInvoiceTable()))
            ->where(Doctrine::quoteIdentifier('global_process_id') . ' = :processId')
            ->setParameter('processId', $processId)
            ->executeQuery()
            ->fetchFirstColumn();

        foreach ($invoiceIds as $invoiceId) {
            try {
                $result[] = $this->getTemporaryInvoice($invoiceId);
            } catch (QUI\Exception) {
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeInvoiceData(array $data): array
    {
        $data['id'] = (int)$data['id'];
        $data['isbrutto'] = (int)$data['isbrutto'];
        $data['paid_status'] = (int)$data['paid_status'];
        $data['canceled'] = (int)$data['canceled'];
        $data['c_user'] = (int)$data['c_user'];

        $data['nettosum'] = (float)$data['nettosum'];
        $data['subsum'] = (float)$data['subsum'];
        $data['sum'] = (float)$data['sum'];
        $data['processing_status'] = (int)$data['processing_status'];

        return $data;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function createSearchQueryBuilder(string $table, array $params, bool $count = false): QueryBuilder
    {
        $QueryBuilder = QUI::getDataBaseConnection()->createQueryBuilder()
            ->from(Doctrine::quoteIdentifier($table));

        if ($count) {
            $QueryBuilder->select('COUNT(' . Doctrine::quoteIdentifier('id') . ')');
        } else {
            $select = $params['select'] ?? '*';

            if (is_array($select)) {
                $select = array_map(Doctrine::quoteIdentifier(...), $select);
                $QueryBuilder->select(...$select);
            } elseif (in_array($select, $this->getOrderGroupFields(), true)) {
                $QueryBuilder->select(Doctrine::quoteIdentifier($select));
            } else {
                $QueryBuilder->select($select);
            }
        }

        if (isset($params['where']) && is_array($params['where'])) {
            $this->applyWhere($QueryBuilder, $params['where']);
        }

        if (isset($params['where_or']) && is_array($params['where_or'])) {
            $this->applyWhere($QueryBuilder, $params['where_or'], true);
        }

        if ($count) {
            return $QueryBuilder;
        }

        if (isset($params['order']) && $this->canBeUseAsOrderField($params['order'])) {
            [$orderField, $orderDirection] = array_pad(explode(' ', $params['order'], 2), 2, 'ASC');
            $QueryBuilder->orderBy(Doctrine::quoteIdentifier($orderField), $orderDirection);
        }

        if (isset($params['limit'])) {
            $limit = explode(',', (string)$params['limit'], 2);

            if (isset($limit[1])) {
                $QueryBuilder->setFirstResult((int)$limit[0]);
                $QueryBuilder->setMaxResults((int)$limit[1]);
            } else {
                $QueryBuilder->setMaxResults((int)$limit[0]);
            }
        }

        return $QueryBuilder;
    }

    /**
     * @param array<string, mixed> $where
     */
    private function applyWhere(QueryBuilder $QueryBuilder, array $where, bool $or = false): void
    {
        $expressions = [];
        $index = count($QueryBuilder->getParameters());

        foreach ($where as $field => $condition) {
            $column = Doctrine::quoteIdentifier($field);
            $parameter = 'where' . $index++;
            $operator = '=';
            $value = $condition;

            if (is_array($condition) && isset($condition['type'], $condition['value'])) {
                $operator = mb_strtoupper((string)$condition['type']);
                $value = $condition['value'];
            }

            if ($value === null) {
                $expressions[] = $operator === 'NOT'
                    ? $QueryBuilder->expr()->isNotNull($column)
                    : $QueryBuilder->expr()->isNull($column);
                continue;
            }

            if ($operator === 'IN' && is_array($value)) {
                if ($value === []) {
                    $expressions[] = '1 = 0';
                    continue;
                }

                $placeholders = [];

                foreach ($value as $entry) {
                    $entryParameter = 'where' . $index++;
                    $placeholders[] = ':' . $entryParameter;
                    $QueryBuilder->setParameter($entryParameter, $entry);
                }

                $expressions[] = $QueryBuilder->expr()->in($column, $placeholders);
                continue;
            }

            if ($operator === '%LIKE%' || $operator === 'LIKE%' || $operator === '%LIKE') {
                $value = match ($operator) {
                    '%LIKE%' => '%' . $value . '%',
                    'LIKE%' => $value . '%',
                    default => '%' . $value
                };
                $operator = 'LIKE';
            }

            if ($operator === 'NOT') {
                $operator = '!=';
            }

            if (!in_array($operator, ['=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE'], true)) {
                $operator = '=';
            }

            $expressions[] = $column . ' ' . $operator . ' :' . $parameter;
            $QueryBuilder->setParameter($parameter, $value);
        }

        if ($expressions === []) {
            return;
        }

        if ($or) {
            $QueryBuilder->andWhere($QueryBuilder->expr()->or(...$expressions));
            return;
        }

        foreach ($expressions as $expression) {
            $QueryBuilder->andWhere($expression);
        }
    }

    /**
     * @return list<string>
     */
    protected function getOrderGroupFields(): array
    {
        return [
            'id',
            'customer_id',
            'hash',
            'type',
            'order_id',
            'ordered_by',
            'ordered_by_name',
            'project_name',
            'payment_method',
            'payment_time',
            'time_for_payment',
            'paid_status',
            'paid_date',
            'paid_data',
            'date',
            'service_period',
            'c_user',
            'editor_id',
            'editor_name',
            'isbrutto',
            'nettosum',
            'nettosubsum',
            'subsum',
            'sum',
            'processing_status'
        ];
    }

    /**
     * Can the string be used as a mysql order field?
     *
     * @param mixed $str
     * @return bool
     */
    protected function canBeUseAsOrderField(mixed $str): bool
    {
        if (!is_string($str)) {
            return false;
        }

        $parts = explode(' ', trim($str));

        if (count($parts) > 2 || !in_array($parts[0], $this->getOrderGroupFields(), true)) {
            return false;
        }

        if (!isset($parts[1])) {
            return true;
        }

        return in_array(mb_strtoupper($parts[1]), ['ASC', 'DESC'], true);
    }
}
