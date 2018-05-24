<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_get
 */

use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

/**
 * Returns the invoice as array
 *
 * @param string $invoiceId - ID of the invoice or invoice hash
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_getArticleHtml',
    function ($invoiceId) {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();

        try {
            $Invoice = $Invoices->get($invoiceId);
        } catch (QUI\Exception $Exception) {
            $Invoice = $Invoices->getInvoiceByHash($invoiceId);
        }

        return $Invoice->getView()->getArticles()->render();
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
