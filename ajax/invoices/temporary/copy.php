<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_copy
 */

/**
 * Create a temporary invoice and copy the data from another invoice into it
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_copy',
    function ($invoiceId) {
        $Invoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getTemporaryInvoiceByString($invoiceId);

        return $Invoice->copy()->getId();
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
