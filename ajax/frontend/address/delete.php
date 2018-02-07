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
    'package_quiqqer_invoice_ajax_frontend_address_delete',
    function ($addressId) {
        $User    = QUI::getUserBySession();
        $Address = $User->getAddress($addressId);
        $Address->delete();
    },
    array('addressId')
);
