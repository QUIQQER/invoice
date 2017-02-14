<?php

namespace QUI\ERP\Accounting\Invoice;

use QUI;

/**
 * Class TemporaryInvoice
 * - Temporary Bill / Invoice class
 * - A temporary invoice is a non posted bill, it is not a real invoice
 * - To create a correct invoice from the temporary invoice, the post method must be used
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class TemporaryInvoice extends QUI\QDOM
{
    /**
     * Invoice constructor.
     *
     * @param $id
     * @param Handler $Handler
     */
    public function __construct($id, Handler $Handler)
    {
        $this->setAttributes($Handler->getTemporaryInvoiceData($id));
    }

    /**
     * Creates a bill from the temporary invoice
     *
     * @throws Exception
     */
    public function post()
    {

    }

    /**
     * SAve te current temporary invoice data to the database
     */
    public function save()
    {

    }
}
