<?php

namespace QUI\ERP\Accounting\Invoice;

use QUI;

/**
 * Class Factory
 * - Creates Temporary invoices
 *
 * @package QUI\ERP\Accounting\Bill
 */
class Factory
{
    /**
     * @var null
     */
    protected static $Instance = null;

    /**
     * @return null|Factory
     */
    public static function getInstance()
    {
        if (self::$Instance === null) {
            self::$Instance = new self();
        }

        return self::$Instance;
    }

    /**
     * Create a non posted invoice
     *
     * @return TemporaryInvoice
     */
    public function createTemporaryBill()
    {
        $Handler  = Handler::getInstance();
        $Database = QUI::getDataBase();

        $Database->insert(
            $Handler->temporaryInvoiceTable(),
            array()
        );

        $newId = $Database->getPDO()->lastInsertId();

        return $Handler->getTemporaryInvoice($newId);
    }
}