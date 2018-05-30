<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_getArticleHtml
 */

/**
 * Returns the temporary invoice articles as html
 *
 * @param string $invoiceId - ID of the invoice or invoice hash
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_getArticleHtml',
    function ($invoiceId) {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();

        try {
            $Invoice = $Invoices->getTemporaryInvoice($invoiceId);
        } catch (QUI\Exception $Exception) {
            $Invoice = $Invoices->getInvoiceByHash($invoiceId);
        }

        return $Invoice->getView()->getArticles()->render();
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
