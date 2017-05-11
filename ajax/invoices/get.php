<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_list
 */

/**
 * Returns invoices list for a grid
 *
 * @param string $params - JSON query params
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_get',
    function ($invoiceId) {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $Invoice  = $Invoices->get($invoiceId);

        $attributes = $Invoice->toArray();
        $Currency   = $Invoice->getCurrency();

        $attributes['display_subsum'] = $Currency->format($attributes['subsum']);
        $attributes['display_sum']    = $Currency->format($attributes['sum']);

        return $attributes;
    },
    array('invoiceId'),
    'Permission::checkAdminUser'
);
