<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_setStatus
 */

use QUI\ERP\Accounting\Invoice\Invoice;

/**
 * set a status to the invoice
 *
 * @param string $invoiceId - ID of the invoice
 * @param string $status - new status
 *
 * @return string|integer
 */
QUI::getAjax()->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_setStatus',
    function ($invoiceId, $status) {
        $Invoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getInvoiceByString($invoiceId);

        if (!($Invoice instanceof Invoice)) {
            throw new QUI\Exception('Processing statuses can only be set for posted invoices.');
        }

        $Invoice->setProcessingStatus($status);
    },
    ['invoiceId', 'status'],
    'Permission::checkAdminUser'
);
