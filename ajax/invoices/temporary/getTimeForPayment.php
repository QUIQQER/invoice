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
        try {
            $User = QUI::getUsers()->get($uid);
        } catch (QUI\Exception $Exception) {
            // default time for payment
            return QUI\ERP\Accounting\Invoice\Settings::getInstance()->get('invoice', 'time_for_payment');
        }

        $permission = $User->getPermission(
            'quiqqer.invoice.timeForPayment',
            'maxInteger'
        );

        return $permission;
    },
    ['uid'],
    'Permission::checkAdminUser'
);
