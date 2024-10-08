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
        $Grid = new QUI\Utils\Grid();

        // filter
        $filter = json_decode($filter);
        $params = json_decode($params, true);

        foreach ($filter as $entry => $value) {
            $Search->setFilter($entry, $value);
        }

        // query params
        $query = $Grid->parseDBParams($params);

        if (isset($query['limit'])) {
            $limit = explode(',', $query['limit']);

            $Search->limit($limit[0], $limit[1]);
        }

        if (isset($query['order'])) {
            $Search->order($query['order']);
        }

        if (isset($query['sortOn']) && isset($query['sortBy'])) {
            $Search->order($query['sortOn'] . ' ' . $query['sortBy']);
        }

        if (!empty($params['calcTotal'])) {
            $Search->enableCalcTotal();
        } else {
            $Search->disableCalcTotal();
        }

        try {
            return $Search->searchForGrid();
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return [
                'grid' => [],
                'total' => 0
            ];
        }
    },
    [
        'params',
        'filter'
    ],
    'Permission::checkAdminUser'
);
