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
        if (empty($uid)) {
            return QUI\ERP\Defaults::getBruttoNettoStatus() === QUI\ERP\Utils\User::IS_NETTO_USER;
        }

        try {
            $User = QUI::getUsers()->get($uid);
            $status = QUI\ERP\Utils\User::getBruttoNettoUserStatus($User);
        } catch (QUI\Exception) {
            return QUI\ERP\Defaults::getBruttoNettoStatus() === QUI\ERP\Utils\User::IS_NETTO_USER;
        }

        return $status === QUI\ERP\Utils\User::IS_NETTO_USER;
    },
    ['uid'],
    'Permission::checkAdminUser'
);
