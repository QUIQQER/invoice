<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_list
 */

use QUI\ERP\Accounting\Invoice\Search\InvoiceSearch;

/**
 * Returns invoices list for a grid
 *
 * @param string $params - JSON query params
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_list',
    function ($params) {
        $Search = InvoiceSearch::getInstance();
        $Grid   = new QUI\Utils\Grid();

        // query params
        $query = $Grid->parseDBParams(json_decode($params, true));

        if (isset($query['limit'])) {
            $limit = explode(',', $query['limit']);

            $Search->limit($limit[0], $limit[1]);
        }

        return $Search->search();
    },
    array('params'),
    'Permission::checkAdminUser'
);
