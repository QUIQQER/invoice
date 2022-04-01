<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_save
 */

use QUI\ERP\Shipping\Shipping;
use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;

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
        $data    = \json_decode($data, true);

        if (empty($data['customer_id'])) {
            $data['invoice_address_id'] = '';
            $data['invoice_address']    = '';
        }

        if (!empty($data['addressDelivery'])) {
            $delivery = $data['addressDelivery'];
            unset($data['addressDelivery']);

            try {
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

        if (isset($data['currency'])) {
            $Invoice->setCurrency($data['currency']);
        }

        if (!empty($data['currencyRate'])) {
            $Currency = $Invoice->getCurrency();
            $Currency->setExchangeRate(\floatval($data['currencyRate']));
            $Invoice->setCurrency($Currency);
        }

        $Invoice->setAttribute('invoice_address', false); // needed because of address reset
        $Invoice->setAttributes($data);

        // Attached customer files
        $Invoice->clearCustomerFiles();

        if (!empty($data['attached_customer_files'])) {
            if (\is_string($data['attached_customer_files'])) {
                $customerFiles = \json_decode($data['attached_customer_files'], true);
            } else {
                $customerFiles = $data['attached_customer_files'];
            }

            foreach ($customerFiles as $entry) {
                try {
                    $Invoice->addCustomerFile($entry['hash'], $entry['options']);
                } catch (\Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                }
            }
        }

        $Invoice->save();

        return $Invoice->toArray();
    },
    ['invoiceId', 'data'],
    'Permission::checkAdminUser'
);
