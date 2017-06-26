<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_html
 */

/**
 * Return the invoice as HTML
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_isNetto',
    function ($uid) {
        $User   = QUI::getUsers()->get($uid);
        $status = QUI\ERP\Utils\User::getBruttoNettoUserStatus($User);

        return $status === QUI\ERP\Utils\User::IS_NETTO_USER;
    },
    array('uid'),
    'Permission::checkAdminUser'
);