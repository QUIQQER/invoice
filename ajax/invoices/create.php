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
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $Invoice  = $Invoices->create();

        return $Invoice->getId();
    },
    array(),
    'Permission::checkAdminUser'
);
