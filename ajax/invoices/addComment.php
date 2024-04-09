<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_addComment
 */

/**
 * Add a comment to an invoice
 *
 * @param string|integer $invoiceId - ID of the invoice
 * @param string $comment - amount of the payment
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_addComment',
    function ($invoiceId, $comment) {
        try {
            $Invoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getInvoiceByString($invoiceId);
        } catch (Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());
            throw $Exception;
        }

        $Invoice->addComment($comment);
    },
    ['invoiceId', 'comment'],
    'Permission::checkAdminUser'
);
