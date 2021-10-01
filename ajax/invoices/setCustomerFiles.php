<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_setStatus
 */

/**
 * set a status to the invoice
 *
 * @param string $invoiceId - ID of the invoice
 * @param string $status - new status
 *
 * @return string|integer
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_setCustomerFiles',
    function ($invoiceId, $customerFiles) {
        $customerFiles = \json_decode($customerFiles, true);

        $Invoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getInvoiceByString($invoiceId);
        $Invoice->clearCustomerFiles();

        foreach ($customerFiles as $customerFile) {
            $Invoice->addCustomerFile($customerFile['hash'], $customerFile['options']);
        }
    },
    ['invoiceId', 'customerFiles'],
    'Permission::checkAdminUser'
);
