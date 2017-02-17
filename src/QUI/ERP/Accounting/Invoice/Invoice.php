<?php

namespace QUI\ERP\Accounting\Invoice;

use QUI;

/**
 * Class Invoice
 * - Invoice class
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class Invoice extends QUI\QDOM
{
    /**
     * @var string
     */
    const ID_PREFIX = 'INV-';

    /**
     * @var int
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
        $this->setAttributes($Handler->getInvoiceData($id));

        $this->id = (int)str_replace(self::ID_PREFIX, '', $id);
    }

    /**
     * Return the invoice id
     *
     * @return string
     */
    public function getId()
    {
        return self::ID_PREFIX . $this->id;
    }

    /**
     * Returns only the integer id
     *
     * @return int
     */
    public function getCleanId()
    {
        return (int)str_replace(self::ID_PREFIX, '', $this->getId());
    }

    /**
     * Storno
     */
    public function cancel()
    {
        // @todo wenn umgesetzt wird, vorher mor fragen
        // -> Gutschrift erzeugen
    }

    public function copy()
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
