<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_preview
 */

use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

/**
 * Preview of an invoice
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_preview',
    function ($invoiceId) {
        $Invoice = InvoiceUtils::getInvoiceByString($invoiceId);
        $View    = $Invoice->getView();

        return $View->previewHTML();
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
