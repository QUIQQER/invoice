<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_addPayment
 */

use QUI\ERP\Accounting\Payments\Payments as Payments;

/**
 * Add a payment to an invoice
 *
 * @param string|integer invoiceId - ID of the invoice
 * @param string|int $amount - amount of the payment
 * @param string $paymentMethod - Payment method
 * @param string|int $date - Date of the payment
 *
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_addPayment',
    function ($invoiceId, $amount, $paymentMethod, $date) {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $Invoice  = $Invoices->getInvoice($invoiceId);
        $Payment  = Payments::getInstance()->getPayment($paymentMethod);

        $Invoice->addPayment($amount, $Payment, $date);
    },
    array('invoiceId', 'amount', 'paymentMethod', 'date'),
    'Permission::checkAdminUser'
);
