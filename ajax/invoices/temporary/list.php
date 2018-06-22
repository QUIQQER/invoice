<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_list
 */

use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\Settings;

use QUI\ERP\Currency\Handler as Currencies;
use QUI\ERP\Accounting\Payments\Payments as Payments;
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
    function ($params) {
        $Invoices = Handler::getInstance();
        $Grid     = new QUI\Utils\Grid();
        $Payments = Payments::getInstance();
        $Locale   = QUI::getLocale();

        $data = $Invoices->searchTemporaryInvoices(
            $Grid->parseDBParams(json_decode($params, true))
        );

        $localeCode = QUI::getLocale()->getLocalesByLang(
            QUI::getLocale()->getCurrent()
        );

        $DateFormatter = new \IntlDateFormatter(
            $localeCode[0],
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::NONE
        );

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
            'order_id',
            'processing_status',
            'comments',
            'payment_data',
            'hash'
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
            } catch (QUI\Exception $Exception) {
                continue;
            }

            $data[$key]['id'] = $TemporaryInvoice->getId();

            $Currency = $TemporaryInvoice->getCurrency();

            // payment
            $TemporaryInvoice->getPaidStatusInformation();

            try {
                $data[$key]['payment_title'] = $TemporaryInvoice->getPayment()->getTitle();
            } catch (QUI\Exception $Exception) {
                $data[$key]['payment_title'] = Handler::EMPTY_VALUE;
            }

            $data[$key]['paid_status_display'] = $Locale->get(
                'quiqqer/invoice',
                'payment.status.'.$TemporaryInvoice->getAttribute('paid_status')
            );


            // customer data
            if (!empty($entry['customer_id'])) {
                try {
                    $Customer = $TemporaryInvoice->getCustomer();
                    $customer = $Customer->getName();

                    if (empty($customer)) {
                        $customer = $Address->getAttribute('firstname');
                        $customer .= ' ';
                        $customer .= $Address->getAttribute('lastname');

                        $customer = trim($customer);
                    }

                    if ($Customer->isCompany()) {
                        $Address  = $Customer->getAddress();
                        $customer = $Address->getAttribute('company').' ('.$customer.')';
                    }

                    $data[$key]['customer_name'] = $customer;
                } catch (QUI\Exception $Exception) {
                    $data[$key]['customer_id']   = Handler::EMPTY_VALUE;
                    $data[$key]['customer_name'] = Handler::EMPTY_VALUE;
                }
            }

            if (!empty($entry['c_user'])) {
                try {
                    $CUser = QUI::getUsers()->get($entry['c_user']);

                    $data[$key]['c_username'] = $CUser->getName().' ('.$CUser->getId().')';
                } catch (QUI\Exception $Exception) {
                    $data[$key]['c_user']     = Handler::EMPTY_VALUE;
                    $data[$key]['c_username'] = Handler::EMPTY_VALUE;
                }
            }

            // format
            $data[$key]['date'] = $DateFormatter->format(
                strtotime($TemporaryInvoice->getAttribute('date'))
            );

            //$vatTextArray = InvoiceUtils::getVatTextArrayFromVatArray($invoiceData['vat_array'], $Currency);
            //$vatSumArray  = InvoiceUtils::getVatSumArrayFromVatArray($invoiceData['vat_array']);
            $vatSum = InvoiceUtils::getVatSumFromVatArray($data[$key]['vat_array']);

            $data[$key]['display_vatsum']   = $Currency->format($vatSum);
            $data[$key]['display_nettosum'] = $Currency->format($data[$key]['nettosum']);
            $data[$key]['display_sum']      = $Currency->format($data[$key]['sum']);
            $data[$key]['display_subsum']   = $Currency->format($data[$key]['subsum']);
        }

        return $Grid->parseResult($data, $Invoices->countTemporaryInvoices());
    },
    ['params'],
    'Permission::checkAdminUser'
);
