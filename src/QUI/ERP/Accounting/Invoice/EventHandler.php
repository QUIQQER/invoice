<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\EventHandler
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;
use QUI\Package\Package;
use QUI\ERP\Accounting\Invoice\ProcessingStatus;

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
}
