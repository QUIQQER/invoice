<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_addComment
 */

/**
 * Add a comment to an invoice
 *
 * @param string|integer invoiceId - ID of the invoice
 * @param string $comment - amount of the payment
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_addComment',
    function ($invoiceId, $comment) {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $Invoice  = $Invoices->getInvoice($invoiceId);

        $Invoice->addComment($comment);
    },
    ['invoiceId', 'comment'],
    'Permission::checkAdminUser'
);
