<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_search
 */

use QUI\ERP\Accounting\Invoice\Search\InvoiceSearch;

/**
 * Search invoices
 *
 * @param string $params - Grid query params
 * @param string $filter - Filter
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_search',
    function ($params, $filter) {
        $Search = InvoiceSearch::getInstance();
        $Grid   = new QUI\Utils\Grid();

        // filter
        $filter = \json_decode($filter);

        foreach ($filter as $entry => $value) {
            $Search->setFilter($entry, $value);
        }

        // query params
        $query = $Grid->parseDBParams(\json_decode($params, true));

        if (isset($query['limit'])) {
            $limit = \explode(',', $query['limit']);

            $Search->limit($limit[0], $limit[1]);
        }

        if (isset($query['order'])) {
            $Search->order($query['order']);
        }

        return $Search->searchForGrid();
    },
    ['params', 'filter'],
    'Permission::checkAdminUser'
);
