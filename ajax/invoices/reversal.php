<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_reversal
 */

use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

/**
 * Cancellation of an invoice
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_reversal',
    function ($invoiceId, $reason) {
        $Settings = QUI\ERP\Accounting\Invoice\Settings::getInstance();
        $currentSetting = $Settings->sendMailAtInvoiceCreation();
        $Settings->set('invoice', 'sendMailAtCreation', false);

        $result = InvoiceUtils::getInvoiceByString($invoiceId)->reversal($reason);
        $Reversal = InvoiceUtils::getInvoiceByString($result);

        $Settings->set('invoice', 'sendMailAtCreation', $currentSetting);

        return $Reversal->getUUID();
    },
    ['invoiceId', 'reason'],
    'Permission::checkAdminUser'
);
