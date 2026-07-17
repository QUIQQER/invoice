<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_reversal
 */

use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

/**
 * Cancellation of an invoice
 */
QUI::getAjax()->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_reversal',
    function ($invoiceId, $reason) {
        $Settings = QUI\ERP\Accounting\Invoice\Settings::getInstance();
        $currentSetting = $Settings->sendMailAtInvoiceCreation();
        $Invoice = InvoiceUtils::getInvoiceByString($invoiceId);

        if (!$Invoice instanceof QUI\ERP\Accounting\Invoice\Invoice) {
            throw new QUI\Exception(
                QUI::getLocale()->get('quiqqer/invoice', 'exception.invoice.reversal.invalidEntity')
            );
        }

        $Settings->set('invoice', 'sendMailAtCreation', false);

        try {
            return $Invoice->reversal($reason)->getUUID();
        } finally {
            $Settings->set('invoice', 'sendMailAtCreation', $currentSetting);
        }
    },
    ['invoiceId', 'reason'],
    'Permission::checkAdminUser'
);
