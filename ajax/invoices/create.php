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
    'package_quiqqer_invoice_ajax_invoices_create',
    function () {
        $Factory = QUI\ERP\Accounting\Invoice\Factory::getInstance();
        $Invoice = $Factory->createInvoice();

        return $Invoice->getUUID();
    },
    false,
    ['Permission::checkAdminUser', 'quiqqer.invoice.create']
);
