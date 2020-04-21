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
        $Invoices = InvoiceHandler::getInstance();
        $Invoice  = $Invoices->getTemporaryInvoice($invoiceId);
        $data     = \json_decode($data, true);

        if (empty($data['customer_id'])) {
            $data['invoice_address_id'] = '';
            $data['invoice_address']    = '';
        }

        if (isset($data['addressDelivery']) && !empty($data['addressDelivery'])) {
            $delivery = $data['addressDelivery'];

            if ($delivery) {
                unset($data['addressDelivery']);

                try {
                    $Invoice->setDeliveryAddress($delivery);
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::writeDebugException($Exception);
                }
            }
        }

        $Invoice->clearArticles();

        if (isset($data['articles'])) {
            $Invoice->importArticles($data['articles']);
            unset($data['articles']);
        }

        $Invoice->setAttribute('invoice_address', false); // needed because of address reset
        $Invoice->setAttributes($data);
        $Invoice->save();
    },
    ['invoiceId', 'data'],
    'Permission::checkAdminUser'
);
