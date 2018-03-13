<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_delete
 */

/**
 * Creates a new temporary invoice
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_delete',
    function ($invoiceId) {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $Invoices->delete($invoiceId);
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
