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
use QUI\ERP\Accounting\Payments\Transactions\Transaction;
use Quiqqer\Engine\Collector;

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
     * @throws QUI\Exception
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
            $result = [];

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
            Fields::createField([
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
                'options'       => [
                    'maxLength' => 255,
                    'minLength' => 3
                ],
                'titles'        => [
                    'de' => 'Rechnungstext',
                    'en' => 'Invoice text'
                ]
            ]);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::addAlert($Exception->getMessage());
        }
    }

    /**
     * event: on transaction create
     *
     * @param Transaction $Transaction
     */
    public static function onTransactionCreate(Transaction $Transaction)
    {
        $hash = $Transaction->getHash();

        try {
            $Invoice = Handler::getInstance()->getInvoiceByHash($hash);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return;
        }

        try {
            $Invoice->addTransaction($Transaction);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }

    /**
     * template event: on frontend users address top
     *
     * @param Collector $Collector
     * @param QUI\Users\User $User
     */
    public static function onFrontendUsersAddressTop(Collector $Collector, QUI\Users\User $User)
    {
        try {
            $Engine = QUI::getTemplateManager()->getEngine();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return;
        }

        $current = '';

        if ($User->getAttribute('quiqqer.erp.address')) {
            $current = $User->getAttribute('quiqqer.erp.address');
        }

        $Engine->assign([
            'addresses' => $User->getAddressList(),
            'current'   => $current
        ]);

        $result = '';
        $result .= '<style>';
        $result .= file_get_contents(dirname(__FILE__).'/FrontendUsers/userProfileAddressSelect.css');
        $result .= '</style>';
        $result .= $Engine->fetch(dirname(__FILE__).'/FrontendUsers/userProfileAddressSelect.html');

        $Collector->append($result);
    }

    /**
     * @param QUI\Users\User $User
     * @throws QUi\Exception
     */
    public static function onUserSaveBegin(QUI\Users\User $User)
    {
        $Package = QUI::getPackage('quiqqer/frontend-users');
        $Config  = $Package->getConfig();

        if (!$Config->get('userProfile', 'useAddressManagement')) {
            return;
        }

        $Request   = QUI::getRequest();
        $addressId = $Request->get('quiqqer-frontendUsers-userdata-invoice-address');

        if (!$addressId) {
            return;
        }

        // quiqqer.erp.address

        // look if the address is an address of the user
        try {
            $Address = $User->getAddress($addressId);

            $User->setAttribute('quiqqer.erp.address', $addressId);
            $User->setAttribute('address', $addressId);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }
}
