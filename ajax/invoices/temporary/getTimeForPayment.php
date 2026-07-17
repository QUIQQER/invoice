<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_getTimeForPayment
 */

/**
 * @return int
 */
QUI::getAjax()->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_getTimeForPayment',
    function ($uid) {
        return QUI\ERP\Customer\Utils::getInstance()->getPaymentTimeForUser((int)$uid);
    },
    ['uid'],
    'Permission::checkAdminUser'
);
