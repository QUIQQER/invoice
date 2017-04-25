<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_calc
 */

use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\ArticleList;

/**
 * Calculates the current price of the invoice product
 *
 * @return string
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_calc',
    function ($articles, $user) {
        $articles = json_decode($articles, true);
        $user     = json_decode($user, true);

        try {
            $User = new QUI\ERP\User($user);
            $Calc = QUI\ERP\Accounting\Calc::getInstance($User);
        } catch (QUI\Exception $exception) {
            $Calc = QUI\ERP\Accounting\Calc::getInstance();
        }

        $List = new ArticleList();

        foreach ($articles as $article) {
            $List->addArticle(new Article($article));
        }

        $List->calc($Calc);

        return $List->getCalculations();
    },
    array('articles', 'user'),
    'Permission::checkAdminUser'
);
