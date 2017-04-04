<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_list
 */

/**
 * Returns invoices list for a grid
 *
 * @param string $params - JSON query params
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_get',
    function ($invoiceId) {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $Invoice  = $Invoices->get($invoiceId);

        return $Invoice->toArray();
    },
    array('invoiceId'),
    'Permission::checkAdminUser'
);
