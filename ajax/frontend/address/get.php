<?php

/**
 * This file contains package_quiqqer_invoice_ajax_frontend_address_delete
 */

/**
 * Return the address control html
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_frontend_address_get',
    function ($orderId) {
        $Control = new QUI\ERP\Accounting\Invoice\Order\Address([
            'orderId' => $orderId
        ]);

        return $Control->create();
    },
    array('orderId')
);
