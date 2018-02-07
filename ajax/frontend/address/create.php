<?php

/**
 * This file contains package_quiqqer_invoice_ajax_frontend_address_create
 */

/**
 * Create a new address for the user
 *
 * @param string $data - json array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_frontend_address_create',
    function ($data) {
        $_REQUEST['createSave'] = true;

        $AddressStep = new \QUI\ERP\Accounting\Invoice\Order\Address();
        $AddressStep->createAddress(json_decode($data, true));
    },
    array('data')
);
