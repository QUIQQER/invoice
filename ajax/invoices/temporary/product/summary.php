<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_product_summary
 */

/**
 * Data for the summary display of an article
 * The calculation is with a brutto user, so you get the complete data
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_product_summary',
    function ($article) {
        $article = json_decode($article, true);

        $Brutto = new QUI\ERP\User([
            'id'        => 'BRUTTO',
            'country'   => '',
            'username'  => '',
            'firstname' => '',
            'lastname'  => '',
            'lang'      => QUI::getLocale()->getCurrent(),
            'isCompany' => 0,
            'isNetto'   => 0
        ]);

        $Brutto->setAttribute(
            'quiqqer.erp.isNettoUser',
            QUI\ERP\Utils\User::IS_BRUTTO_USER
        );

        $Calc    = QUI\ERP\Accounting\Calc::getInstance($Brutto);
        $Article = new QUI\ERP\Accounting\Article($article);

        $Article->setUser($Brutto);
        $Article->calc($Calc);

        return $Article->toArray();
    },
    ['article'],
    'Permission::checkAdminUser'
);
