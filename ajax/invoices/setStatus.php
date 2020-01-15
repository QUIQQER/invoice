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
    'package_quiqqer_invoice_ajax_invoices_setStatus',
    function ($invoiceId, $status) {
        $Invoice = QUI\ERP\Accounting\Invoice\Handler::getInstance()->get($invoiceId);
        $Invoice->setProcessingStatus($status);
    },
    ['invoiceId', 'status'],
    'Permission::checkAdminUser'
);
