<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_product_hasProductCustomFields
 */

use QUI\ERP\Products\Handler\Products;

/**
 *
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_product_hasProductCustomFields',
    function ($productId) {
        $Product = Products::getProduct($productId);
        $fields  = $Product->createUniqueProduct()->getCustomFields();

        return count($fields);
    },
    ['productId'],
    'Permission::checkAdminUser'
);
