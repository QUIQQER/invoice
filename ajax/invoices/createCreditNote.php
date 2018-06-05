<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_create
 */

use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

/**
 * Creates a new temporary invoice
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_createCreditNote',
    function ($invoiceId) {
        return InvoiceUtils::getInvoiceByString($invoiceId)->createCreditNote()->getId();
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
