<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_copy
 */

use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

/**
 * Create a temporary invoice and copy the data from the invoice into it
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_copy',
    function ($invoiceId) {
        return InvoiceUtils::getInvoiceByString($invoiceId)->copy()->getUUID();
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
