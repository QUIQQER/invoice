<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Handler
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;

use function array_flip;
use function explode;
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
     * @var int
     */
    const TYPE_INVOICE = QUI\ERP\Constants::TYPE_INVOICE;

    /**
     * @var int
     */
    const TYPE_INVOICE_TEMPORARY = QUI\ERP\Constants::TYPE_INVOICE_TEMPORARY;

    /**
     * Gutschrift / Credit note
     * @var int
     */
    const TYPE_INVOICE_CREDIT_NOTE = QUI\ERP\Constants::TYPE_INVOICE_CREDIT_NOTE;

    // Storno types

    /**
     * Reversal, storno, cancellation
     */
    const TYPE_INVOICE_REVERSAL = QUI\ERP\Constants::TYPE_INVOICE_REVERSAL;

    /**
     * Alias for reversal
     * @var int
     */
    const TYPE_INVOICE_STORNO = QUI\ERP\Constants::TYPE_INVOICE_STORNO;

    /**
     * Status für alte stornierte Rechnung
     *
     * @var int
     */
    const TYPE_INVOICE_CANCEL = QUI\ERP\Constants::TYPE_INVOICE_CANCEL;

    /**
     * ID of the invoice product text field
     */
    const INVOICE_PRODUCT_TEXT_ID = QUI\ERP\Constants::INVOICE_PRODUCT_TEXT_ID;

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
     * Return all invoices by an user
     *
     * @param QUI\Users\User $User
     * @return Invoice[]
     */
    public function getInvoicesByUser(QUI\Users\User $User): array
    {
        $result = [];

        try {
            $invoices = QUI::getDataBase()->fetch([
                'select' => 'id',
                'from' => self::invoiceTable(),
                'where' => [
                    'customer_id' => $User->getId()
                ]
            ]);
        } catch (QUI\Exception) {
            return [];
        }

        foreach ($invoices as $invoice) {
            try {
                $result[] = $this->getInvoice($invoice['id']);
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
     * @param string $invoiceId - ID of a temporary Invoice
     * @param QUI\Interfaces\Users\User|null $User
     *
     * @throws QUI\Permissions\Exception
     * @throws QUI\Lock\Exception
     * @throws QUI\Exception
     */
    public function delete($invoiceId, $User = null): void
    {
        $Invoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getInvoiceByString($invoiceId);

        if (!($Invoice instanceof InvoiceTemporary)) {
            $Invoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getTemporaryInvoiceByString($invoiceId);
        }

        if ($Invoice instanceof InvoiceTemporary) {
            $Invoice->delete($User);
        }
    }

    /**
     * GET methods
     */

    /**
     * Search for invoices
     *
     * @param array $params - search params
     * @return array
     *
     * @throws QUI\DataBase\Exception
     */
    public function search($params = [])
    {
        $query = [
            'from' => $this->invoiceTable(),
            'limit' => 20
        ];

        if (isset($params['select'])) {
            $query['select'] = $params['select'];
        }

        if (isset($params['where'])) {
            $query['where'] = $params['where'];
        }

        if (isset($params['where_or'])) {
            $query['where_or'] = $params['where_or'];
        }

        if (isset($params['limit'])) {
            $query['limit'] = $params['limit'];
        }

        if (isset($params['order'])) {
            $query['order'] = $params['order'];
        } else {
            $query['order'] = 'id DESC';
        }

        return QUI::getDataBase()->fetch($query);
    }

    /**
     * Count the invoices
     *
     * @param array $queryParams - optional
     * @return int
     *
     * @throws QUI\DataBase\Exception
     */
    public function count($queryParams = [])
    {
        $query = [
            'from' => $this->invoiceTable(),
            'count' => [
                'select' => 'id',
                'as' => 'count'
            ]
        ];

        if (isset($queryParams['where'])) {
            $query['where'] = $queryParams['where'];
        }

        if (isset($queryParams['where_or'])) {
            $query['where_or'] = $queryParams['where_or'];
        }

        $data = QUI::getDataBase()->fetch($query);

        if (isset($data[0]) && isset($data[0]['count'])) {
            return (int)$data[0]['count'];
        }

        return 0;
    }

    /**
     * Search for temporary invoices
     *
     * @param array $params - search params
     * @return array
     */
    public function searchTemporaryInvoices(array $params = []): array
    {
        $query = [
            'from' => $this->temporaryInvoiceTable(),
            'limit' => 20
        ];

        if (isset($params['where'])) {
            $query['where'] = $params['where'];
        }

        if (isset($params['where_or'])) {
            $query['where_or'] = $params['where_or'];
        }

        if (isset($params['limit'])) {
            $query['limit'] = $params['limit'];
        }

        if (isset($params['order']) && $this->canBeUseAsOrderField($params['order'])) {
            $query['order'] = $params['order'];
        }

        try {
            return QUI::getDataBase()->fetch($query);
        } catch (QUi\Exception $Exception) {
            return [];
        }
    }

    /**
     * Count the invoices
     *
     * @param array $queryParams - optional
     * @return int
     *
     * @throws QUI\DataBase\Exception
     */
    public function countTemporaryInvoices(array $queryParams = []): int
    {
        $query = [
            'from' => $this->temporaryInvoiceTable(),
            'count' => [
                'select' => 'id',
                'as' => 'count'
            ]
        ];

        if (isset($queryParams['where'])) {
            $query['where'] = $queryParams['where'];
        }

        if (isset($queryParams['where_or'])) {
            $query['where_or'] = $queryParams['where_or'];
        }

        $data = QUI::getDataBase()->fetch($query);

        if (isset($data[0]) && isset($data[0]['count'])) {
            return (int)$data[0]['count'];
        }

        return 0;
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
    public function get(int|string $id): Invoice|InvoiceTemporary
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
    public function getInvoice(int|string $id): Invoice
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
    public function getInvoiceByHash(string $hash)
    {
        $hash = QUI\Utils\Security\Orthos::clear($hash);

        $result = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from' => self::invoiceTable(),
            'where' => [
                'hash' => $hash
            ],
            'limit' => 1
        ]);

        if (!empty($result)) {
            return $this->getInvoice($result[0]['id']);
        }

        $result = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from' => self::temporaryInvoiceTable(),
            'where' => [
                'hash' => $hash
            ],
            'limit' => 1
        ]);

        if (!empty($result)) {
            return $this->getTemporaryInvoice($result[0]['id']);
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
     * @return array
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function getInvoiceData(int|string $id): array
    {
        // check invoice via hash
        $result = QUI::getDataBase()->fetch([
            'from' => self::invoiceTable(),
            'where' => [
                'hash' => $id
            ],
            'limit' => 1
        ]);

        if (!empty($result)) {
            $result[0]['id'] = (int)$result[0]['id'];
            $result[0]['customer_id'] = (int)$result[0]['customer_id'];
            $result[0]['isbrutto'] = (int)$result[0]['isbrutto'];
            $result[0]['paid_status'] = (int)$result[0]['paid_status'];
            $result[0]['canceled'] = (int)$result[0]['canceled'];
            $result[0]['c_user'] = (int)$result[0]['c_user'];

            $result[0]['nettosum'] = (float)$result[0]['nettosum'];
            $result[0]['subsum'] = (float)$result[0]['subsum'];
            $result[0]['sum'] = (float)$result[0]['sum'];
            $result[0]['processing_status'] = (int)$result[0]['processing_status'];

            return $result[0];
        }

        // check invoice via old ids
        $whereOr = [
            'id_with_prefix' => $id
        ];

        $idSanitized = str_replace(Settings::getInstance()->getInvoicePrefix(), '', $id);

        if (is_numeric($idSanitized)) {
            $whereOr['id'] = (int)$idSanitized;
        }

        $result = QUI::getDataBase()->fetch([
            'from' => self::invoiceTable(),
            'where_or' => $whereOr,
            'limit' => 1
        ]);

        if (empty($result)) {
            throw new Exception(
                ['quiqqer/invoice', 'exception.invoice.not.found', ['id' => $id]],
                404
            );
        }

        $result[0]['id'] = (int)$result[0]['id'];
        $result[0]['customer_id'] = (int)$result[0]['customer_id'];
        $result[0]['isbrutto'] = (int)$result[0]['isbrutto'];
        $result[0]['paid_status'] = (int)$result[0]['paid_status'];
        $result[0]['canceled'] = (int)$result[0]['canceled'];
        $result[0]['c_user'] = (int)$result[0]['c_user'];

        $result[0]['nettosum'] = (float)$result[0]['nettosum'];
        $result[0]['subsum'] = (float)$result[0]['subsum'];
        $result[0]['sum'] = (float)$result[0]['sum'];
        $result[0]['processing_status'] = (int)$result[0]['processing_status'];

        return $result[0];
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
     * @param string|int $id - ID / Hash of the Invoice
     * @return InvoiceTemporary
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function getTemporaryInvoice($id): InvoiceTemporary
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
        $result = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from' => self::temporaryInvoiceTable(),
            'where' => [
                'hash' => $hash
            ],
            'limit' => 1
        ]);

        $hash = QUI\Utils\Security\Orthos::clear($hash);

        if (!isset($result[0])) {
            throw new Exception(
                ['quiqqer/invoice', 'exception.temporary.invoice.not.found', ['id' => $hash]],
                404
            );
        }

        return $this->getTemporaryInvoice($result[0]['id']);
    }

    /**
     * Return the data from a temporary invoice
     *
     * @param int|string $id
     * @return array
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function getTemporaryInvoiceData(int|string $id): array
    {
        $result = QUI::getDataBase()->fetch([
            'from' => self::temporaryInvoiceTable(),
            'where' => [
                'hash' => $id
            ],
            'limit' => 1
        ]);

        if (empty($result)) {
            $prefix = Settings::getInstance()->getTemporaryInvoicePrefix();
            $id = QUI\Utils\Security\Orthos::clear($id);

            $result = QUI::getDataBase()->fetch([
                'from' => self::temporaryInvoiceTable(),
                'where' => [
                    'id' => (int)str_replace($prefix, '', $id)
                ],
                'limit' => 1
            ]);
        }

        if (!isset($result[0])) {
            throw new Exception(
                ['quiqqer/invoice', 'exception.temporary.invoice.not.found', ['id' => $id]],
                404
            );
        }

        $canceled = null;

        if (isset($result[0]['canceled'])) {
            $canceled = (int)$result[0]['canceled'];
        }

        $result[0]['id'] = (int)$result[0]['id'];
        $result[0]['customer_id'] = (int)$result[0]['customer_id'];
        $result[0]['invoice_address_id'] = (int)$result[0]['invoice_address_id'];
        $result[0]['isbrutto'] = (int)$result[0]['isbrutto'];
        $result[0]['paid_status'] = (int)$result[0]['paid_status'];
        $result[0]['processing_status'] = (int)$result[0]['processing_status'];
        $result[0]['time_for_payment'] = (int)$result[0]['time_for_payment'];
        $result[0]['canceled'] = $canceled;
        $result[0]['c_user'] = (int)$result[0]['c_user'];

        $result[0]['nettosum'] = (float)$result[0]['nettosum'];
        $result[0]['subsum'] = (float)$result[0]['subsum'];
        $result[0]['sum'] = (float)$result[0]['sum'];

        return $result[0];
    }

    //endregion

    /**
     * Return all invoices from a process id
     *
     * @param $processId
     * @return Invoice[]|InvoiceTemporary[]
     *
     * @throws QUI\DataBase\Exception
     */
    public function getInvoicesByGlobalProcessId($processId): array
    {
        $result = [];

        $data = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from' => self::invoiceTable(),
            'where' => [
                'global_process_id' => $processId
            ]
        ]);

        foreach ($data as $entry) {
            try {
                $result[] = $this->get($entry['id']);
            } catch (QUI\Exception $Exception) {
            }
        }

        $data = QUI::getDataBase()->fetch([
            'select' => 'id',
            'from' => self::temporaryInvoiceTable(),
            'where' => [
                'global_process_id' => $processId
            ]
        ]);

        foreach ($data as $entry) {
            try {
                $result[] = $this->getTemporaryInvoice($entry['id']);
            } catch (QUI\Exception $Exception) {
            }
        }

        return $result;
    }

    /**
     * @return array
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
     * @param $str
     * @return bool
     */
    protected function canBeUseAsOrderField($str): bool
    {
        if (!is_string($str)) {
            return false;
        }

        $fields = array_flip($this->getOrderGroupFields());
        $str = explode(' ', $str);

        if (!isset($fields[$str[0]])) {
            return false;
        }

        if (!isset($fields[$str[1]])) {
            return true;
        }

        if (
            mb_strtoupper($fields[$str[1]]) === 'DESC' ||
            mb_strtoupper($fields[$str[1]]) === 'ASC'
        ) {
            return true;
        }

        return true;
    }
}
