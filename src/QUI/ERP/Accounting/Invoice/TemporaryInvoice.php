<?php

namespace QUI\ERP\Accounting\Invoice;

use QUI;

/**
 * Class TemporaryInvoice
 * - Temporary Invoice / Invoice class
 * - A temporary invoice is a non posted invoice, it is not a real invoice
 * - To create a correct invoice from the temporary invoice, the post method must be used
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class TemporaryInvoice extends QUI\QDOM
{
    /**
     * @var string
     */
    protected $id;

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
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * SAve te current temporary invoice data to the database
     */
    public function save()
    {

    }

    /**
     * Creates an invoice from the temporary invoice
     * Its post the invoice
     *
     * @throws Exception
     */
    public function post()
    {

    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->getAttributes();
    }
}
