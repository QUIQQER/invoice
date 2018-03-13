<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_getTimeForPayment
 */

/**
 * @return int
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_getTimeForPayment',
    function ($uid) {
        $User = QUI::getUsers()->get($uid);

        $permission = $User->getPermission(
            'quiqqer.invoice.timeForPayment',
            'maxInteger'
        );

        return $permission;
    },
    ['uid'],
    'Permission::checkAdminUser'
);
