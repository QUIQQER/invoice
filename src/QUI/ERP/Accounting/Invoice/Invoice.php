<?php

namespace QUI\ERP\Accounting\Invoice;

use QUI;

/**
 * Class Invoice
 * - Bill class
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class Invoice extends QUI\QDOM
{
    /**
     * Invoice constructor.
     *
     * @param $id
     * @param Handler $Handler
     */
    public function __construct($id, Handler $Handler)
    {
        $this->setAttributes($Handler->getInvoiceData($id));
    }

    /**
     * Storno
     */
    public function cancel()
    {
        // @todo wenn umgesetzt wird, vorher mor fragen
        // -> Gutschrift erzeugen
    }
}
