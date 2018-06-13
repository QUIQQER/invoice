<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_save
 */

/**
 * Saves the temporary invoice
 *
 * @param integer|string $invoiceId
 * @param string $data - JSON data
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_save',
    function ($invoiceId, $data) {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $Invoice  = $Invoices->getTemporaryInvoice($invoiceId);
        $data     = json_decode($data, true);

        $Invoice->clearArticles();

        if (isset($data['articles'])) {
            $Invoice->importArticles($data['articles']);
            unset($data['articles']);
        }

        $Invoice->setAttributes($data);
        $Invoice->save();
    },
    ['invoiceId', 'data'],
    'Permission::checkAdminUser'
);
