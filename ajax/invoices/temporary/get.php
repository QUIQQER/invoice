<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_get
 */

/**
 * Return the temporary invoice data
 *
 * @param string $params - JSON query params
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_get',
    function ($invoiceId) {
        $Invoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getTemporaryInvoiceByString($invoiceId);
        $invoice = $Invoice->toArray();

        if (isset($invoice['invoice_address']) && \is_string($invoice['invoice_address'])) {
            $invoice['invoice_address'] = \json_decode($invoice['invoice_address'], true);
        }

        $invoice['currencyRate']            = $Invoice->getCurrency()->getExchangeRate();
        $invoice['attached_customer_files'] = $Invoice->getCustomerFiles();

        return $invoice;
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
