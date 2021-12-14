<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_addPayment
 */

use QUI\ERP\Accounting\Payments\Payments as Payments;
use QUI\ERP\Accounting\Payments\Transactions\Factory as TransactionFactory;

/**
 * Add a payment to an invoice
 *
 * @param string|integer invoiceId - ID of the invoice
 * @param string|int $amount - amount of the payment
 * @param string $paymentMethod - Payment method
 * @param string|int $date - Date of the payment
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_addPayment',
    function ($invoiceId, $amount, $paymentMethod, $date) {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $Payment = Payments::getInstance()->getPayment($paymentMethod);

        try {
            $Invoice = $Invoices->getInvoice($invoiceId);
        } catch (QUI\Exception $Exception) {
            $Invoice = $Invoices->getInvoiceByHash($invoiceId);
        }

        // create the transaction
        TransactionFactory::createPaymentTransaction(
            $amount,
            $Invoice->getCurrency(),
            $Invoice->getHash(),
            $Payment->getPaymentType()->getName(),
            [],
            QUI::getUserBySession(),
            $date,
            $Invoice->getGlobalProcessId()
        );
    },
    [
        'invoiceId',
        'amount',
        'paymentMethod',
        'date'
    ],
    'Permission::checkAdminUser'
);
