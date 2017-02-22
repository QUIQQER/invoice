<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_list
 */

use QUI\ERP\Accounting\Invoice\TemporaryInvoice;
use QUI\ERP\Accounting\Invoice\Handler;

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
        $Invoices = Handler::getInstance();
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
            'c_username',
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
                    $data[$field] = Handler::EMPTY_VALUE;
                }
            }
        };

        foreach ($data as $key => $entry) {
            $fillFields($data[$key]);

            $data[$key]['id'] = TemporaryInvoice::ID_PREFIX . $data[$key]['id'];

            // customer data
            if (!empty($entry['customer_id'])) {
                try {
                    $Customer = QUI::getUsers()->get($entry['customer_id']);

                    $data[$key]['customer_name'] = $Customer->getName();
                } catch (QUI\Exception $Exception) {
                    $data[$key]['customer_id']   = Handler::EMPTY_VALUE;
                    $data[$key]['customer_name'] = Handler::EMPTY_VALUE;
                }
            }

            if (!empty($entry['c_user'])) {
                try {
                    $CUser = QUI::getUsers()->get($entry['c_user']);

                    $data[$key]['c_username'] = $CUser->getName();
                } catch (QUI\Exception $Exception) {
                    $data[$key]['c_user']     = Handler::EMPTY_VALUE;
                    $data[$key]['c_username'] = Handler::EMPTY_VALUE;
                }
            }
        }

        return $Grid->parseResult($data, $Invoices->countTemporaryInvoices());
    },
    array('params'),
    'Permission::checkAdminUser'
);
