<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_payments_format
 */

/**
 * Add a payment to an invoice
 *
 * @param string|integer $payments - List of payments
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_payments_format',
    function ($payments) {
        $payments = \json_decode($payments, true);
        $result   = [];

        $Locale   = QUI::getLocale();
        $Currency = QUI\ERP\Defaults::getCurrency();
        $Payments = QUI\ERP\Accounting\Payments\Payments::getInstance();


        foreach ($payments as $payment) {
            $paymentTitle = '';
            $txid         = '';

            try {
                $Payment      = $Payments->getPaymentType($payment['payment']);
                $paymentTitle = $Payment->getTitle();
            } catch (QUI\Exception $Exception) {
            }

            if (isset($payment['txid'])) {
                $txid = $payment['txid'];
            }

            $result[] = [
                'date'    => $Locale->formatDate($payment['date']),
                'amount'  => $Currency->format($payment['amount']),
                'payment' => $paymentTitle,
                'txid'    => $txid
            ];
        }

        return $result;
    },
    ['payments'],
    'Permission::checkAdminUser'
);
