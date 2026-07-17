<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_create
 */

use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

/**
 * Creates a new temporary invoice
 *
 * @return string
 */
QUI::getAjax()->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_createCreditNote',
    function ($invoiceId, $invoiceData) {
        if (!isset($invoiceData)) {
            $invoiceData = '';
        }

        $invoiceData = json_decode($invoiceData, true);

        if (!is_array($invoiceData)) {
            $invoiceData = [];
        }

        $Settings = QUI\ERP\Accounting\Invoice\Settings::getInstance();
        $currentSetting = $Settings->sendMailAtInvoiceCreation();
        $Settings->set('invoice', 'sendMailAtCreation', false);

        $Invoice = InvoiceUtils::getInvoiceByString($invoiceId);

        if (!($Invoice instanceof Invoice)) {
            throw new QUI\Exception('Credit notes can only be created from posted invoices.');
        }

        $CreditNote = $Invoice->createCreditNote();

        if (!empty($invoiceData)) {
            foreach ($invoiceData as $key => $value) {
                $CreditNote->setData($key, $value);
            }

            $CreditNote->save();
        }

        $Settings->set('invoice', 'sendMailAtCreation', $currentSetting);

        return $CreditNote->getUUID();
    },
    ['invoiceId', 'invoiceData'],
    'Permission::checkAdminUser'
);
