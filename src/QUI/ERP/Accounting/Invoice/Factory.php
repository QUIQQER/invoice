<?php

namespace QUI\ERP\Accounting\Invoice;

use QUI;
use Ramsey\Uuid\Uuid;

/**
 * Class Factory
 * - Creates Temporary invoices
 *
 * @package QUI\ERP\Accounting\Invoice
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
    public function createInvoice()
    {
        $Handler  = Handler::getInstance();
        $Database = QUI::getDataBase();
        $Hash     = Uuid::uuid1();

        $Database->insert(
            $Handler->temporaryInvoiceTable(),
            array(
                'hash' => $Hash->toString()
            )
        );

        $newId = $Database->getPDO()->lastInsertId();

        return $Handler->getTemporaryInvoice($newId);
    }
}
