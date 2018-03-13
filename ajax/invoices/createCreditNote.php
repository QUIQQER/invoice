<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_create
 */

/**
 * Creates a new temporary invoice
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_createCreditNote',
    function ($invoiceId) {
        $Handler = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $Invoice = $Handler->getInvoice($invoiceId);

        return $Invoice->createCreditNote()->getId();
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
