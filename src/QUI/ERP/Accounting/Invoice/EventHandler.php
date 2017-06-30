<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\EventHandler
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;
use QUI\Package\Package;
use QUI\ERP\Accounting\Invoice\ProcessingStatus;
use QUI\ERP\Products\Handler\Fields;
use QUI\ERP\Products\Handler\Search;

/**
 * Class EventHandler
 *
 * @package QUI\ERP
 */
class EventHandler
{
    /**
     * event: on package setup
     *
     * @param Package $Package
     */
    public static function onPackageSetup(Package $Package)
    {
        if ($Package->getName() != 'quiqqer/invoice') {
            return;
        }

        // create invoice payment status
        $Handler = ProcessingStatus\Handler::getInstance();
        $Factory = ProcessingStatus\Factory::getInstance();
        $list    = $Handler->getList();

        if (!empty($list)) {
            return;
        }

        $languages = QUI::availableLanguages();

        $getLocaleTranslations = function ($key) use ($languages) {
            $result = array();

            foreach ($languages as $language) {
                $result[$language] = QUI::getLocale()->getByLang($language, 'quiqqer/invoice', $key);
            }

            return $result;
        };


        // Neu
        $Factory->createProcessingStatus(1, '#ff8c00', $getLocaleTranslations('processing.status.default.1'));

        // In Bearbeitung
        $Factory->createProcessingStatus(2, '#9370db', $getLocaleTranslations('processing.status.default.2'));

        // Erledigt
        $Factory->createProcessingStatus(3, '#228b22', $getLocaleTranslations('processing.status.default.3'));
    }

    /**
     * event: on package setup
     * only for the quiqqer/products
     * extend products with a product invoice text field
     *
     * @param Package $Package
     */
    public static function onProductsPackageSetup(Package $Package)
    {
        if ($Package->getName() != 'quiqqer/products') {
            return;
        }

        try {
            // if the Field exists, we doesn't needed to create it
            Fields::getField(Handler::INVOICE_PRODUCT_TEXT_ID);
            return;
        } catch (QUI\ERP\Products\Field\Exception $Exception) {
        }

        try {
            Fields::createField(array(
                'id'            => Handler::INVOICE_PRODUCT_TEXT_ID,
                'type'          => 'InputMultiLang',
                'prefix'        => '',
                'suffix'        => '',
                'priority'      => 3,
                'systemField'   => 0,
                'standardField' => 1,
                'requiredField' => 0,
                'publicField'   => 0,
                'search_type'   => Search::SEARCHTYPE_TEXT,
                'options'       => array(
                    'maxLength' => 255,
                    'minLength' => 3
                ),
                'titles'        => array(
                    'de' => 'Rechnungstext',
                    'en' => 'Invoice text'
                )
            ));
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addAlert($Exception->getMessage());
        }
    }
}
