<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_create
 */

use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

/**
 * Creates a new temporary invoice
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
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

        $CreditNote = InvoiceUtils::getInvoiceByString($invoiceId)->createCreditNote();

        if (!empty($invoiceData)) {
            foreach ($invoiceData as $key => $value) {
                $CreditNote->setData($key, $value);
            }

            $CreditNote->save();
        }

        $Settings->set('invoice', 'sendMailAtCreation', $currentSetting);

        return $CreditNote->getHash();
    },
    ['invoiceId', 'invoiceData'],
    'Permission::checkAdminUser'
);
