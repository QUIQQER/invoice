<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_reversal
 */

/**
 * Cancellation of an invoice
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_reversal',
    function ($invoiceId) {
        $Handler = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $Invoice = $Handler->getInvoice($invoiceId);
        $Invoice->reversal();


    },
    array('invoiceId'),
    'Permission::checkAdminUser'
);
