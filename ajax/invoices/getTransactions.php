<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_getTransactions
 */

/**
 * Return all transactions of an invoice
 * or returns all transactions related to an invoice
 *
 * @param string $invoiceId - ID of the invoice or invoice hash
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_getTransactions',
    function ($invoiceId) {
        $transactions = QUI\ERP\Accounting\Invoice\Utils\Invoice::getTransactionsByInvoice($invoiceId);

        return array_map(function ($Transaction) {
            /* @var $Transaction \QUI\ERP\Accounting\Payments\Transactions\Transaction */
            return $Transaction->getAttributes();
        }, $transactions);
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
