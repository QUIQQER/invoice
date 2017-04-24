<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_product_parseProductToArticle
 */

use QUI\ERP\Products\Controls\Products\ProductEdit;
use QUI\ERP\Products\Handler\Products;

/**
 *
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_product_getProductEdit',
    function ($productId, $user) {
        $Product = Products::getProduct($productId);
        $user    = json_encode($user, true);

        $Control = new ProductEdit(array(
            'Product' => $Product
        ));

        $css  = ''; //QUI\Control\Manager::getCSS();
        $html = $Control->create();

        return $css . $html;
    },
    array('productId', 'user'),
    'Permission::checkAdminUser'
);
