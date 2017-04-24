<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_product_calc
 */

/**
 * Calculates the current price of the invoice product
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_calc',
    function ($params, $user) {
        $params = json_decode($params, true);
        $user   = json_decode($user, true);

        $User = new QUI\ERP\User($user);
        $Calc = QUI\ERP\Accounting\Calc::getInstance($User);


        \QUI\System\Log::writeRecursive($params);

    },
    array('params', 'user'),
    'Permission::checkAdminUser'
);
