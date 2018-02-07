<?php

/**
 * This file contains package_quiqqer_invoice_ajax_frontend_address_delete
 */

/**
 *
 * @param int $addressId
 * @param array $data
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_frontend_address_getCreate',
    function () {
        $_REQUEST['create'] = true;

        $Control = new QUI\ERP\Accounting\Invoice\Order\Address();

        return $Control->create();
    }
);
