<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_list
 */

/**
 * Returns temporary invoices list for a grid
 *
 * @param string $params - JSON query params
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_post',
    function ($invoiceId) {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();

        $Invoices->getTemporaryInvoice($invoiceId)
            ->post();
    },
    array('invoiceId'),
    'Permission::checkAdminUser'
);
