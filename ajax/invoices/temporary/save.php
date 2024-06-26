<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_save
 */

/**
 * Saves the temporary invoice
 *
 * @param integer|string $invoiceId
 * @param string $data - JSON data
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_save',
    function ($invoiceId, $data) {
        $Invoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getTemporaryInvoiceByString($invoiceId);
        $data = json_decode($data, true);

        if (empty($data['customer_id'])) {
            $data['invoice_address_id'] = '';
            $data['invoice_address'] = '';
        } else {
            try {
                $Invoice->setCustomer(QUI::getUsers()->get($data['customer_id']));
            } catch (Exception) {
            }
        }

        if (!empty($data['addressDelivery'])) {
            $delivery = $data['addressDelivery'];
            unset($data['addressDelivery']);

            try {
                if (is_string($delivery)) {
                    $delivery = json_decode($delivery, true) ?? [];
                }

                $Invoice->setDeliveryAddress($delivery);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        } else {
            $Invoice->removeDeliveryAddress();
        }

        $Invoice->clearArticles();

        if (isset($data['articles'])) {
            $Invoice->importArticles($data['articles']);
            unset($data['articles']);
        }

        if (isset($data['priceFactors']) && is_array($data['priceFactors'])) {
            try {
                $List = new QUI\ERP\Accounting\PriceFactors\FactorList($data['priceFactors']);
                $Invoice->getArticles()->importPriceFactors($List);
            } catch (QUI\Exception) {
            }
        }

        if (isset($data['currency'])) {
            $Invoice->setCurrency($data['currency']);
        }

        if (!empty($data['currencyRate'])) {
            $Currency = $Invoice->getCurrency();
            $Currency->setExchangeRate(floatval($data['currencyRate']));
            $Invoice->setCurrency($Currency);
        }

        $Invoice->setAttribute('invoice_address', false); // needed because of address reset
        $Invoice->setAttributes($data);
        $Invoice->save();

        return $Invoice->toArray();
    },
    ['invoiceId', 'data'],
    'Permission::checkAdminUser'
);
