<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Handler
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;

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
    const TYPE_INVOICE = 1;

    /**
     * @var int
     */
    const TYPE_INVOICE_TEMPORARY = 2;

    /**
     * @var int
     */
    const TYPE_INVOICE_CREDIT_NOTE = 3;

    /**
     * Alias for cancel
     * @var int
     */
    const TYPE_INVOICE_STORNO = 4;

    /**
     * @var int
     */
    const TYPE_INVOICE_CANCEL = 4;


    /**
     * Tables
     */

    /**
     * Return the invoice table name
     *
     * @return string
     */
    public function invoiceTable()
    {
        return QUI::getDBTableName(self::TABLE_INVOICE);
    }

    /**
     * Return the temporary invoice table name
     *
     * @return string
     */
    public function temporaryInvoiceTable()
    {
        return QUI::getDBTableName(self::TABLE_TEMPORARY_INVOICE);
    }

    /**
     * Delete a temporary invoice
     *
     * @param string $invoiceId - ID of a temporary Invoice
     * @param QUI\Interfaces\Users\User|null $User
     */
    public function delete($invoiceId, $User = null)
    {
        $this->getTemporaryInvoice($invoiceId)->delete($User);
    }

    /**
     * GET methods
     */

    /**
     * Search for invoices
     *
     * @param array $params - search params
     * @return array
     */
    public function search($params = array())
    {
        $query = array(
            'from'  => $this->invoiceTable(),
            'limit' => 20
        );

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
     */
    public function count($queryParams = array())
    {
        $query = array(
            'from'  => $this->invoiceTable(),
            'count' => array(
                'select' => 'id',
                'as'     => 'count'
            )
        );

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
    public function searchTemporaryInvoices($params = array())
    {
        $query = array(
            'from'  => $this->temporaryInvoiceTable(),
            'limit' => 20
        );

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
        }

        return QUI::getDataBase()->fetch($query);
    }

    /**
     * Count the invoices
     *
     * @param array $queryParams - optional
     * @return int
     */
    public function countTemporaryInvoices($queryParams = array())
    {
        $query = array(
            'from'  => $this->temporaryInvoiceTable(),
            'count' => array(
                'select' => 'id',
                'as'     => 'count'
            )
        );

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
     * @param string $id - ID of the Invoice or InvoiceTemporary
     * @return InvoiceTemporary|Invoice
     *
     * @throws Exception
     */
    public function get($id)
    {
        $prefix = Settings::getInstance()->getTemporaryInvoicePrefix();

        if (strpos($id, $prefix) !== false) {
            return $this->getTemporaryInvoice($id);
        }

        return $this->getInvoice($id);
    }

    /**
     * Return an Invoice
     *
     * @param string $id - ID of the Invoice
     * @return Invoice
     *
     * @throw Exception
     */
    public function getInvoice($id)
    {
        return new Invoice($id, $this);
    }

    /**
     * Return the data from an invoice
     *
     * @param string $id
     * @return array
     * @throws Exception
     */
    public function getInvoiceData($id)
    {
        $prefix = Settings::getInstance()->getInvoicePrefix();

        $result = QUI::getDataBase()->fetch(array(
            'from'  => self::invoiceTable(),
            'where' => array(
                'id' => (int)str_replace($prefix, '', $id)
            ),
            'limit' => 1
        ));

        if (!isset($result[0])) {
            throw new Exception(
                array('quiqqer/invoice', 'exception.invoice.not.found'),
                404
            );
        }

//        QUI\System\Log::writeRecursive($result[0]['payment_data']);

        $result[0]['id']               = (int)$result[0]['id'];
        $result[0]['customer_id']      = (int)$result[0]['customer_id'];
        $result[0]['order_id']         = (int)$result[0]['order_id'];
        $result[0]['isbrutto']         = (int)$result[0]['isbrutto'];
        $result[0]['paid_status']      = (int)$result[0]['paid_status'];
        $result[0]['time_for_payment'] = (int)$result[0]['time_for_payment'];
        $result[0]['canceled']         = (int)$result[0]['canceled'];
        $result[0]['c_user']           = (int)$result[0]['c_user'];

        $result[0]['nettosum'] = (float)$result[0]['nettosum'];
        $result[0]['subsum']   = (float)$result[0]['subsum'];
        $result[0]['sum']      = (float)$result[0]['sum'];

        return $result[0];
    }

    /**
     * Return a temporary invoice
     *
     * @param string $id - ID of the Invoice
     * @return InvoiceTemporary
     *
     * @throws Exception
     */
    public function getTemporaryInvoice($id)
    {
        return new InvoiceTemporary($id, $this);
    }

    /**
     * Return the data from a temporary invoice
     *
     * @param string $id
     * @return array
     * @throws Exception
     */
    public function getTemporaryInvoiceData($id)
    {
        $prefix = Settings::getInstance()->getTemporaryInvoicePrefix();

        $result = QUI::getDataBase()->fetch(array(
            'from'  => self::temporaryInvoiceTable(),
            'where' => array(
                'id' => (int)str_replace($prefix, '', $id)
            ),
            'limit' => 1
        ));

        if (!isset($result[0])) {
            throw new Exception(
                array('quiqqer/invoice', 'exception.temporary.invoice.not.found'),
                404
            );
        }

        $result[0]['id']                 = (int)$result[0]['id'];
        $result[0]['customer_id']        = (int)$result[0]['customer_id'];
        $result[0]['order_id']           = (int)$result[0]['order_id'];
        $result[0]['invoice_address_id'] = (int)$result[0]['invoice_address_id'];
        $result[0]['isbrutto']           = (int)$result[0]['isbrutto'];
        $result[0]['paid_status']        = (int)$result[0]['paid_status'];
        $result[0]['processing_status']  = (int)$result[0]['processing_status'];
        $result[0]['time_for_payment']   = (int)$result[0]['time_for_payment'];
        $result[0]['canceled']           = (int)$result[0]['canceled'];
        $result[0]['c_user']             = (int)$result[0]['c_user'];

        $result[0]['nettosum'] = (float)$result[0]['nettosum'];
        $result[0]['subsum']   = (float)$result[0]['subsum'];
        $result[0]['sum']      = (float)$result[0]['sum'];

        return $result[0];
    }
}
