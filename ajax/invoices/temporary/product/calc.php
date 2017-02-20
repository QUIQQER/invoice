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
    'package_quiqqer_invoice_ajax_invoices_temporary_product_calc',
    function ($params) {
        $params = json_decode($params, true);
        $Calc   = QUI\ERP\Accounting\Calc::getInstance();

        if (!isset($params['type'])) {
            throw new QUI\Exception('Not found');
        }

        \QUI\System\Log::writeRecursive($params);

        if ($params['type'] == QUI\ERP\Products\Product\Product::class) {
            $Product = new QUI\ERP\Products\Product\Product($params['productId']);
            $Unique  = $Product->createUniqueProduct();;

            $Unique->setQuantity($params['quantity']);
            $Calc->getProductPrice($Unique);

            return $Unique->getAttributes();
        }

        return array();
    },
    array('params'),
    'Permission::checkAdminUser'
);
