<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_html
 */

/**
 * Return the invoice as HTML
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_html',
    function ($invoiceId, $data) {
        $Invoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getTemporaryInvoiceByString($invoiceId);
        $data = json_decode($data, true);

        $Invoice->clearArticles();

        if (isset($data['articles'])) {
            $Invoice->importArticles($data['articles']);
            unset($data['articles']);
        }

        $Invoice->setAttributes($data);

        return $Invoice->getView()->toHTML();
    },
    ['invoiceId', 'data'],
    'Permission::checkAdminUser'
);
