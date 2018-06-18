<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_get
 */

/**
 * Return the temporary invoice data
 *
 * @param string $params - JSON query params
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_get',
    function ($invoiceId) {
        $Invoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getInvoiceByString($invoiceId);

        return $Invoice->toArray();
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
