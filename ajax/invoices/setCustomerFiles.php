<?php

/**
 * Set customer files to an invoice.
 *
 * @param string $invoiceHash
 * @param array $customerFiles
 *
 * @return void
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_setCustomerFiles',
    function ($invoiceHash, $customerFiles) {
        $customerFiles = \json_decode($customerFiles, true);

        $Invoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getInvoiceByString($invoiceHash);
        $Invoice->clearCustomerFiles();

        foreach ($customerFiles as $customerFile) {
            $Invoice->addCustomerFile($customerFile['hash'], $customerFile['options']);
        }
    },
    ['invoiceHash', 'customerFiles'],
    'Permission::checkAdminUser'
);
