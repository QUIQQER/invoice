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
        $Invoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getTemporaryInvoiceByString($invoiceId);
        $Invoice->unlock();
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
