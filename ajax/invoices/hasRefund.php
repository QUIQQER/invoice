<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_hasRefund
 */

/**
 * Return if it is possible to make a refund
 *
 * @param string $invoiceId - ID of the invoice or invoice hash
 *
 * @return bool
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_hasRefund',
    function ($invoiceId) {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();

        try {
            $Invoice = $Invoices->get($invoiceId);
        } catch (QUI\Exception) {
            $Invoice = $Invoices->getInvoiceByHash($invoiceId);
        }

        return $Invoice->hasRefund();
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
