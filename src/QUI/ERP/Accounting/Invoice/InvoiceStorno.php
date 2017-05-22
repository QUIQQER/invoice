<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\StornoInvoice
 */

namespace QUI\ERP\Accounting\Invoice;

/**
 * Class StornoInvoice
 * - Stornierte Rechnung
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class InvoiceStorno extends Invoice
{
    protected $type = Handler::TYPE_INVOICE_CANCEL;
}
