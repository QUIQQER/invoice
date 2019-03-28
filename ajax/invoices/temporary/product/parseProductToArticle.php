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
        $user       = \json_decode($user, true);
        $attributes = \json_decode($attributes, true);
        $User       = null;
        $Locale     = QUI::getLocale();

        if (!empty($user)) {
            $User   = QUI\ERP\User::convertUserDataToErpUser($user);
            $Locale = $User->getLocale();
        }

        try {
            $Product = Products::getProduct((int)$productId);

            foreach ($attributes as $field => $value) {
                if (\strpos($field, 'field-') === false) {
                    continue;
                }

                $field = \str_replace('field-', '', $field);
                $Field = $Product->getField((int)$field);

                $Field->setValue($value);
            }

            // look if the invoice text field has values
            try {
                $Description = $Product->getField(Fields::FIELD_SHORT_DESC);
                $InvoiceText = $Product->getField(
                    QUI\ERP\Accounting\Invoice\Handler::INVOICE_PRODUCT_TEXT_ID
                );

                if (!$InvoiceText->isEmpty()) {
                    $Description->setValue($InvoiceText->getValue());
                }
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addNotice($Exception->getMessage());
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

        return [];
    },
    ['productId', 'attributes', 'user'],
    'Permission::checkAdminUser'
);
