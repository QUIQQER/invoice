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

        $needleFields = array(
            'id',
            'order_id',
            'customer_id',
            'customer_name',
            'date',
            'c_user',
            'paidstatus',
            'display_nettosum',
            'display_vatsum',
            'display_sum',
            'payment_method',
            'payment_time',
            'paid_date',
            'display_paid',
            'display_missing',
            'isbrutto',
            'taxid',
            'order_id',
            'processing_status',
            'comments',
            'payment_data',
            'hash'
        );

        $fillFields = function (&$data) use ($needleFields) {
            foreach ($needleFields as $field) {
                if (!isset($data[$field])) {
                    $data[$field] = '';
                }
            }
        };

        foreach ($data as $key => $entry) {
            $fillFields($data[$key]);
        }

        return $Grid->parseResult($data, $Invoices->countTemporaryInvoices());
    },
    array('params'),
    'Permission::checkAdminUser'
);
