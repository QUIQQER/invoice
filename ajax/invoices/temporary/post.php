<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_post
 */

/**
 * Post a temporary invoices and returns the new Invoice ID
 *
 * @param string $params - JSON query params
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_post',
    function ($invoiceId) {
        $Invoices  = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $Temporary = $Invoices->getTemporaryInvoice($invoiceId);
        $Invoice   = $Temporary->post();

        return $Invoice->getId();
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
