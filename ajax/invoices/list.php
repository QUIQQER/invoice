<?php

/**
 * This file contains package_quiqqer_bill_ajax_invoices_list
 */

/**
 * Returns invoices list for a grid
 *
 * @param string $params - JSON query params
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_bill_ajax_invoices_list',
    function ($params) {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $Grid     = new QUI\Utils\Grid();

        $data = $Invoices->search(
            $Grid->parseDBParams(json_decode($params, true))
        );

        return $Grid->parseResult($data, $Invoices->count());
    },
    array('params'),
    'Permission::checkAdminUser'
);
