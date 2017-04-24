<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_product_calc
 */

/**
 * Calculates the current price of the article
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_product_calc',
    function ($params, $user) {
        $params = json_decode($params, true);
        $user   = json_decode($user, true);

        if (!empty($user)) {
            $User = QUI\ERP\User::convertUserDataToErpUser($user);
            $Calc = QUI\ERP\Accounting\Calc::getInstance($User);
        } else {
            $Calc = QUI\ERP\Accounting\Calc::getInstance();
        }

        $Article = new QUI\ERP\Accounting\Article($params);
        $Article->calc($Calc);

        return $Article->toArray();
    },
    array('params', 'user'),
    'Permission::checkAdminUser'
);
