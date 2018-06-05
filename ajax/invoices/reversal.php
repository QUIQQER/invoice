<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_reversal
 */

use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

/**
 * Cancellation of an invoice
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_reversal',
    function ($invoiceId, $reason) {
        return InvoiceUtils::getInvoiceByString($invoiceId)->reversal($reason);
    },
    ['invoiceId', 'reason'],
    'Permission::checkAdminUser'
);
