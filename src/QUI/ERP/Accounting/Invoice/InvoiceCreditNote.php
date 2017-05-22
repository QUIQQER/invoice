<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\CreditNote
 */

namespace QUI\ERP\Accounting\Invoice;

/**
 * Class CreditNote
 * - Gutschrift Rechnung
 * - Invoice Credit Note
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class CreditNote extends Invoice
{
    protected $type = Handler::TYPE_INVOICE_CREDIT_NOTE;
}
