<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_refund
 */

/**
 * Execute a refund
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_refund',
    function ($txid, $refund, $message = '') {
        $Transactions = QUI\ERP\Accounting\Payments\Transactions\Handler::getInstance();
        $Transaction = $Transactions->get($txid);

        $Transaction->refund($refund, $message);
    },
    ['txid', 'refund', 'message'],
    'Permission::checkAdminUser'
);
