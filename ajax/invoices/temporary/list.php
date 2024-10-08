<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_list
 */

use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\ProcessingStatus\Handler as ProcessingStatusHandler;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

/**
 * Returns temporary invoices list for a grid
 *
 * @param string $params - JSON query params
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_list',
    function ($params, $filter) {
        $Invoices = Handler::getInstance();
        $Grid = new QUI\Utils\Grid();
        $Locale = QUI::getLocale();

        $defaultDateFormat = QUI\ERP\Defaults::getDateFormat();
        $defaultTimeFormat = QUI\ERP\Defaults::getTimestampFormat();

        $ProcessingStatus = ProcessingStatusHandler::getInstance();
        $list = $ProcessingStatus->getProcessingStatusList();
        $processing = [];

        foreach ($list as $Status) {
            $processing[$Status->getId()] = $Status;
        }

        $query = $Grid->parseDBParams(json_decode($params, true));
        $filter = json_decode($filter, true);

        if (!empty($filter['currency'])) {
            $query['where']['currency'] = $filter['currency'];
        }

        $data = $Invoices->searchTemporaryInvoices($query);

        $needleFields = [
            'id',
            'order_id',
            'customer_id',
            'customer_name',
            'global_process_id',
            'date',
            'c_user',
            'c_username',
            'paidstatus',
            'nettosum',
            'display_vatsum',
            'sum',
            'payment_method',
            'payment_time',
            'paid_date',
            'display_paid',
            'display_missing',
            'isbrutto',
            'taxId',
            'processing_status',
            'comments',
            'payment_data',
            'hash',
            'project_name'
        ];

        $fillFields = function (&$data) use ($needleFields) {
            foreach ($needleFields as $field) {
                if (!isset($data[$field])) {
                    $data[$field] = Handler::EMPTY_VALUE;
                }
            }
        };

        foreach ($data as $key => $entry) {
            $fillFields($data[$key]);

            try {
                $TemporaryInvoice = $Invoices->getTemporaryInvoice($entry['id']);
            } catch (QUI\Exception) {
                continue;
            }

            $data[$key]['id'] = $TemporaryInvoice->getUUID();
            $data[$key]['prefixedNumber'] = $TemporaryInvoice->getPrefixedNumber();

            $Currency = $TemporaryInvoice->getCurrency();

            // payment
            $TemporaryInvoice->getPaidStatusInformation();

            try {
                $data[$key]['payment_title'] = $TemporaryInvoice->getPayment()->getTitle();
            } catch (QUI\Exception) {
                $data[$key]['payment_title'] = Handler::EMPTY_VALUE;
            }

            $paidStatus = $TemporaryInvoice->getAttribute('paid_status');
            $paidText = $Locale->get('quiqqer/erp', 'payment.status.' . $paidStatus);

            $data[$key]['paid_status_display'] = '<span class="payment-status payment-status-' . $paidStatus . '">' . $paidText . '</span>';


            // processing status
            $processStatus = $data[$key]['processing_status'];

            if (isset($processing[$processStatus])) {
                /* @var $Status QUI\ERP\Accounting\Invoice\ProcessingStatus\Status */
                $Status = $processing[$processStatus];
                $color = $Status->getColor();

                $data[$key]['processing_status_display'] = '<span class="processing-status" style="color: ' . $color . '">' .
                    $Status->getTitle() .
                    '</span>';
            }


            // customer data
            if (!empty($entry['customer_id'])) {
                try {
                    $Customer = $TemporaryInvoice->getCustomer();
                    $customer = '---';
                    $customerId = '---';

                    if ($Customer) {
                        $customer = $Customer->getName();
                        $Address = $Customer->getAddress();
                        $customerId = $Customer->getAttribute('customerId');

                        if (!$customerId) {
                            $customerId = Handler::EMPTY_VALUE;
                        }

                        if (empty($customer)) {
                            $customer = $Address->getAttribute('firstname');
                            $customer .= ' ';
                            $customer .= $Address->getAttribute('lastname');

                            $customer = trim($customer);
                        }

                        if ($Customer->isCompany() && !empty($Address->getAttribute('company'))) {
                            $oCustomer = $customer;
                            $customer = $Address->getAttribute('company');

                            if (!empty($oCustomer)) {
                                $customer .= ' (' . $oCustomer . ')';
                            }
                        }
                    }

                    $data[$key]['customer_name'] = $customer;
                    $data[$key]['customer_id_display'] = $customerId;
                } catch (QUI\Exception) {
                    $data[$key]['customer_id'] = Handler::EMPTY_VALUE;
                    $data[$key]['customer_name'] = Handler::EMPTY_VALUE;
                    $data[$key]['customer_id_display'] = Handler::EMPTY_VALUE;
                }
            }

            if (!empty($entry['c_user'])) {
                try {
                    $CUser = QUI::getUsers()->get($entry['c_user']);

                    $data[$key]['c_username'] = $CUser->getName() . ' (' . $CUser->getUUID() . ')';
                } catch (QUI\Exception) {
                    $data[$key]['c_user'] = Handler::EMPTY_VALUE;
                    $data[$key]['c_username'] = Handler::EMPTY_VALUE;
                }
            }

            // format
            $data[$key]['date'] = $Locale->formatDate(
                strtotime($TemporaryInvoice->getAttribute('date')),
                $defaultDateFormat
            );

            $data[$key]['c_date'] = $Locale->formatDate(
                strtotime($TemporaryInvoice->getAttribute('date')),
                $defaultTimeFormat
            );

            $vatSum = InvoiceUtils::getVatSumFromVatArray($data[$key]['vat_array']);

            $data[$key]['display_vatsum'] = $Currency->format($vatSum);
            $data[$key]['display_nettosum'] = $Currency->format($data[$key]['nettosum']);
            $data[$key]['display_sum'] = $Currency->format($data[$key]['sum']);
            $data[$key]['display_subsum'] = $Currency->format($data[$key]['subsum']);
        }

        return $Grid->parseResult(
            $data,
            $Invoices->countTemporaryInvoices($query)
        );
    },
    ['params', 'filter'],
    'Permission::checkAdminUser'
);
