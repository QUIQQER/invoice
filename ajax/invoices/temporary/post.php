<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_post
 */

/**
 * Post a temporary invoices and returns the new Invoice Hash
 *
 * @param string $params - JSON query params
 *
 * @return string - HASH
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_post',
    function ($invoiceId) {
        $Temporary = QUI\ERP\Accounting\Invoice\Utils\Invoice::getTemporaryInvoiceByString($invoiceId);
        $Invoice   = $Temporary->post();

        return $Invoice->getHash();
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
