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
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();

        return $Invoices->getTemporaryInvoice($invoiceId)
            ->copy()
            ->getId();
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
