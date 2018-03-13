<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_unlock
 */

/**
 * Unlock a temporary invoice
 *
 * @param integer|string $invoiceId
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_unlock',
    function ($invoiceId) {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $Invoice  = $Invoices->getTemporaryInvoice($invoiceId);
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
