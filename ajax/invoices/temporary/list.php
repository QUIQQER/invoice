<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_list
 */

/**
 * Returns temporary invoices list for a grid
 *
 * @param string $params - JSON query params
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_list',
    function ($params) {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $Grid     = new QUI\Utils\Grid();

        $data = $Invoices->searchTemporaryInvoices(
            $Grid->parseDBParams(json_decode($params, true))
        );

        return $Grid->parseResult($data, $Invoices->countTemporaryInvoices());
    },
    array('params'),
    'Permission::checkAdminUser'
);
