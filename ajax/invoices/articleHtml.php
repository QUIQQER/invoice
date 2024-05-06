<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_articleHtml
 */

/**
 * Returns invoices articles as HTML
 *
 * @param string $params - JSON query params
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_articleHtml',
    function ($invoiceId) {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $Invoice = $Invoices->get($invoiceId);
        $Articles = $Invoice->getArticles();

        return $Articles->toHTMLWithCSS();
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
