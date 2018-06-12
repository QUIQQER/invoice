<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_getArticleHtml
 */

use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

/**
 * Returns the invoice articles as html
 *
 * @param string $invoiceId - ID of the invoice or invoice hash
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_getArticleHtml',
    function ($invoiceId) {
        $Invoice = InvoiceUtils::getInvoiceByString($invoiceId);

        return $Invoice->getView()->getArticles()->render();
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
