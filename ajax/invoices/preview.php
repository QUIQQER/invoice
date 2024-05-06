<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_preview
 */

use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

/**
 * Preview of an invoice
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_preview',
    function ($invoiceId, $onlyArticles) {
        $Invoice = InvoiceUtils::getInvoiceByString($invoiceId);
        $View = $Invoice->getView();

        if (!isset($onlyArticles)) {
            $onlyArticles = false;
        }

        $onlyArticles = (int)$onlyArticles;

        if ($onlyArticles) {
            return $View->previewOnlyArticles();
        }

        return $View->previewHTML();
    },
    ['invoiceId', 'onlyArticles'],
    'Permission::checkAdminUser'
);
