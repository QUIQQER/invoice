<?php

/**
 * This file contains package_quiqqer_invoice_ajax_frontend_address_getEdit
 */

/**
 *
 * @param int $addressId
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_frontend_address_getEdit',
    function ($addressId) {
        $_REQUEST['edit'] = $addressId;

        $Control = new QUI\ERP\Accounting\Invoice\Order\Address();

        return $Control->create();
    },
    array('addressId')
);
