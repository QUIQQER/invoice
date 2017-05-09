<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_list
 */
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Currency\Handler as Currencies;
use QUI\ERP\Accounting\Payments\Handler as Payments;

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
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $Grid     = new QUI\Utils\Grid();
        $Locale   = QUI::getLocale();
        $Payments = Payments::getInstance();

        $query           = $Grid->parseDBParams(json_decode($params, true));
        $query['select'] = 'id';

        $result = array();
        $data   = $Invoices->search($query);

        $needleFields = array(
            'customer_id',
            'customer_name',
            'comments',
            'c_user',
            'c_username',
            'date',
            'display_missing',
            'display_paid',
            'display_vatsum',
            'display_sum',
            'dunning_level',
            'hash',
            'id',
            'isbrutto',
            'nettosum',
            'order_id',
            'orderdate',
            'paidstatus',
            'paid_date',
            'processing',
            'payment_data',
            'payment_method',
            'payment_time',
            'payment_title',
            'processing_status',
            'sum',
            'taxId'
        );

        $fillFields = function (&$data) use ($needleFields) {
            foreach ($needleFields as $field) {
                if (!isset($data[$field])) {
                    $data[$field] = Handler::EMPTY_VALUE;
                }
            }
        };

        foreach ($data as $entry) {
            try {
                $Invoice = $Invoices->getInvoice($entry['id']);
            } catch (QUI\Exception $Exception) {
                continue;
            }

            $Invoice->getPaidStatusInformation();

            $invoiceData = $Invoice->getAttributes();
            $fillFields($invoiceData);

            try {
                $currency = json_decode($Invoice->getAttribute('currency_data'), true);
                $Currency = Currencies::getCurrency($currency['code']);
            } catch (QUI\Exception $Exception) {
                $Currency = \QUI\ERP\Defaults::getCurrency();
            }

            if (!$Invoice->getAttribute('order_id')) {
                $invoiceData['order_id'] = Handler::EMPTY_VALUE;
            }

            if (!$Invoice->getAttribute('dunning_level')) {
                $invoiceData['dunning_level'] = Invoice::DUNNING_LEVEL_OPEN;
            }


            $invoiceData['date'] = date('Y-m-d', strtotime($Invoice->getAttribute('date')));

            if ($Invoice->getAttribute('paid_date')) {
                $invoiceData['paid_date'] = date('Y-m-d', $Invoice->getAttribute('paid_date'));
            } else {
                $invoiceData['paid_date'] = Handler::EMPTY_VALUE;
            }


            $invoiceData['paid_status_display'] = $Locale->get(
                'quiqqer/invoice',
                'payment.status.' . $Invoice->getAttribute('paid_status')
            );

            $invoiceData['dunning_level_display'] = $Locale->get(
                'quiqqer/invoice',
                'dunning.level.' . $invoiceData['dunning_level']
            );

            try {
                $invoiceData['payment_title'] = $Payments->getPayment(
                    $Invoice->getAttribute('payment_method')
                )->getTitle();
            } catch (QUI\Exception $Exception) {
            }


            // data preparation
            if (!$Invoice->getAttribute('id_prefix')) {
                $invoiceData['id_prefix'] = Invoice::ID_PREFIX;
            }

            $invoiceData['id'] = $invoiceData['id_prefix'] . $invoiceData['id'];
            $invoiceAddress    = json_decode($invoiceData['invoice_address'], true);

            $invoiceData['customer_name'] = $invoiceAddress['salutation'] . ' ' .
                                            $invoiceAddress['firstname'] . ' ' .
                                            $invoiceAddress['lastname'];


            // display totals
            $invoiceData['display_nettosum'] = $Currency->format($invoiceData['nettosum']);
            $invoiceData['display_sum']      = $Currency->format($invoiceData['sum']);
            $invoiceData['display_subsum']   = $Currency->format($invoiceData['subsum']);
            $invoiceData['display_paid']     = $Currency->format($invoiceData['paid']);
            $invoiceData['display_toPay']    = $Currency->format($invoiceData['toPay']);


            // vat information
            $vatArray = $invoiceData['vat_array'];
            $vatArray = json_decode($vatArray, true);

            $vat = array_map(function ($data) use ($Currency) {
                return $data['text'] . ': ' . $Currency->format($data['sum']);
            }, $vatArray);

            $vatSum = array_map(function ($data) use ($Currency) {
                return $data['sum'];
            }, $vatArray);

            $invoiceData['vat']            = implode('; ', $vat);
            $invoiceData['display_vatsum'] = $Currency->format(array_sum($vatSum));


            // customer data
            $customerData = json_decode($invoiceData['customer_data'], true);

            if (empty($customerData['erp.taxNumber'])) {
                $customerData['erp.taxNumber'] = Handler::EMPTY_VALUE;
            }

            $invoiceData['taxId'] = $customerData['erp.taxNumber'];


            $result[] = $invoiceData;
        }

        return $Grid->parseResult($result, $Invoices->count());
    },
    array('params'),
    'Permission::checkAdminUser'
);
