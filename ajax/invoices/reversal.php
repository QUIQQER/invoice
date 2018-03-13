<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_reversal
 */

/**
 * Cancellation of an invoice
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_reversal',
    function ($invoiceId, $reason) {
        $Handler = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $Invoice = $Handler->getInvoice($invoiceId);

        return $Invoice->reversal($reason);
    },
    ['invoiceId', 'reason'],
    'Permission::checkAdminUser'
);
