<?php

/**
 * This file contains package_quiqqer_invoice_ajax_address_create
 */

/**
 * Creates a new invoice address for the user
 *
 * @param int $userId
 * @param array $data
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_address_create',
    function ($userId, $data) {
        $User = QUI::getUsers()->get($userId);

        $Address = $User->addAddress(
            \json_decode($data, true)
        );

        $User->setAttribute('quiqqer.erp.address', $Address->getId());
        $User->save();
    },
    ['userId', 'data'],
    'Permission::checkAdminUser'
);
