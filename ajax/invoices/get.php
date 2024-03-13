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
    'package_quiqqer_invoice_ajax_invoices_get',
    function ($invoiceId) {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();

        try {
            $Invoice = $Invoices->get($invoiceId);
        } catch (QUI\Exception) {
            $Invoice = $Invoices->getInvoiceByHash($invoiceId);
        }

        QUI\ERP\Accounting\Calc::calculatePayments($Invoice);

        $attributes = $Invoice->toArray();
        $Currency = $Invoice->getCurrency();
        $DateFormatter = QUI::getLocale()->getDateFormatter();

        $attributes['display_subsum'] = $Currency->format($attributes['subsum']);
        $attributes['display_sum'] = $Currency->format($attributes['sum']);
        $attributes['vatsum'] = QUI\ERP\Accounting\Calc::calculateTotalVatOfInvoice($attributes['vat_array']);
        $attributes['display_vatsum'] = $Currency->format($attributes['vatsum']);

        if (!empty($attributes['time_for_payment'])) {
            $attributes['time_for_payment'] = $DateFormatter->format(
                strtotime($attributes['time_for_payment'])
            );
        }

        $attributes['articles'] = InvoiceUtils::formatArticlesArray($attributes['articles']);
        $attributes['getType'] = $Invoice->getType();

        return $attributes;
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
