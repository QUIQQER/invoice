<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_product_parseProductToArticle
 */

use QUI\ERP\Products\Handler\Fields;
use QUI\ERP\Products\Handler\Products;

/**
 *
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_product_parseProductToArticle',
    function ($productId, $attributes, $user) {
        $user       = json_decode($user, true);
        $attributes = json_decode($attributes, true);
        $User       = null;
        $Locale     = QUI::getLocale();

        if (!empty($user)) {
            $User   = QUI\ERP\User::convertUserDataToErpUser($user);
            $Locale = $User->getLocale();
        }

        try {
            \QUI\System\Log::writeRecursive($productId);

            $Product = Products::getProduct((int)$productId);

            foreach ($attributes as $field => $value) {
                if (strpos($field, 'field-') === false) {
                    continue;
                }

                $field = str_replace('field-', '', $field);
                $Field = $Product->getField((int)$field);

                $Field->setValue($value);
            }

            // create unique product, to create the ERP Article, so invoice can work with it
            $Unique = $Product->createUniqueProduct($User);

            if (isset($attributes['quantity'])) {
                $Unique->setQuantity($attributes['quantity']);
            }

            $Unique->calc();

            return $Unique->toArticle($Locale)->toArray();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::write($Exception->getMessage());
        }

        return array();
    },
    array('productId', 'attributes', 'user'),
    'Permission::checkAdminUser'
);
