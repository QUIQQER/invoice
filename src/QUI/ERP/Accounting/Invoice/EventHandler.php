<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\EventHandler
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;
use QUI\ERP\Accounting\Invoice\Output\OutputProviderCancelled;
use QUI\ERP\Accounting\Invoice\Output\OutputProviderCreditNote;
use QUI\ERP\Accounting\Invoice\Output\OutputProviderInvoice;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;
use QUI\ERP\Products\Handler\Fields;
use QUI\ERP\Products\Handler\Search;
use QUI\Package\Package;
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

        // Patches
        try {
            self::patchIdWithPrefixColumn();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        // create invoice payment status
        $Handler = ProcessingStatus\Handler::getInstance();
        $Factory = ProcessingStatus\Factory::getInstance();
        $list = $Handler->getList();

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
                'id' => Handler::INVOICE_PRODUCT_TEXT_ID,
                'type' => 'InputMultiLang',
                'prefix' => '',
                'suffix' => '',
                'priority' => 3,
                'systemField' => 0,
                'standardField' => 1,
                'requiredField' => 0,
                'publicField' => 0,
                'search_type' => Search::SEARCHTYPE_TEXT,
                'options' => [
                    'maxLength' => 255,
                    'minLength' => 3
                ],
                'titles' => [
                    'de' => 'Rechnungstext',
                    'en' => 'Invoice text'
                ]
            ]);
        } catch (\Exception $Exception) {
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
     * @param Transaction $Transaction
     */
    public static function onTransactionStatusChange(Transaction $Transaction)
    {
        $hash = $Transaction->getHash();

        try {
            $Invoice = Handler::getInstance()->getInvoiceByHash($hash);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return;
        }

        $Invoice->calculatePayments();
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
            'current' => $current
        ]);

        $result = '';
        $result .= '<style>';
        $result .= \file_get_contents(\dirname(__FILE__) . '/FrontendUsers/userProfileAddressSelect.css');
        $result .= '</style>';
        $result .= $Engine->fetch(\dirname(__FILE__) . '/FrontendUsers/userProfileAddressSelect.html');

        $Collector->append($result);
    }

    /**
     * @param QUI\Users\User $User
     * @throws QUi\Exception
     */
    public static function onUserSaveBegin(QUI\Users\User $User)
    {
        $Package = QUI::getPackage('quiqqer/frontend-users');
        $Config = $Package->getConfig();

        if (!$Config->get('userProfile', 'useAddressManagement')) {
            return;
        }

        $Request = QUI::getRequest();
        $addressId = $Request->get('quiqqer-frontendUsers-userdata-invoice-address');

        if (!$addressId) {
            return;
        }

        // quiqqer.erp.address

        // look if the address is an address of the user
        try {
            $User->getAddress($addressId);

            $User->setAttribute('quiqqer.erp.address', $addressId);
            $User->setAttribute('address', $addressId);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }

    /**
     * @param QUI\Users\User $User
     * @param QUI\ERP\Comments $Comments
     */
    public static function onQuiqqerErpGetCommentsByUser(
        QUI\Users\User $User,
        QUI\ERP\Comments $Comments
    ) {
        $Handler = Handler::getInstance();
        $invoices = $Handler->getInvoicesByUser($User);

        foreach ($invoices as $Invoice) {
            $Comments->import($Invoice->getComments());
        }
    }

    /**
     * quiqqer/customer: onQuiqqerErpGetHistoryByUser
     *
     * @param QUI\Users\User $User
     * @param QUI\ERP\Comments $Comments
     */
    public static function onQuiqqerErpGetHistoryByUser(
        QUI\Users\User $User,
        QUI\ERP\Comments $Comments
    ) {
        $Handler = Handler::getInstance();
        $invoices = $Handler->getInvoicesByUser($User);

        foreach ($invoices as $Invoice) {
            // created invoice
            $Comments->addComment(
                QUI::getLocale()->get('quiqqer/invoice', 'erp.comment.invoice.created', [
                    'invoiceId' => $Invoice->getId()
                ]),
                \strtotime($Invoice->getAttribute('c_date')),
                'quiqqer/invoice',
                'fa fa-history'
            );
        }
    }

    /**
     * quiqqer/erp: onQuiqqerErpOutputSendMailBefore
     *
     * Used to attach customer files that are attached to an invoice to the invoice mail
     *
     * @param $entityId
     * @param string $entityType
     * @param string $recipient
     * @param QUI\Mail\Mailer $Mailer
     * @return void
     */
    public static function onQuiqqerErpOutputSendMailBefore(
        $entityId,
        string $entityType,
        string $recipient,
        QUI\Mail\Mailer $Mailer
    ) {
        $allowedEntityTypes = [
            OutputProviderInvoice::getEntityType(),
            OutputProviderCancelled::getEntityType(),
            OutputProviderCreditNote::getEntityType()
        ];

        if (!\in_array($entityType, $allowedEntityTypes)) {
            return;
        }

        try {
            $Invoice = OutputProviderInvoice::getEntity($entityId);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return;
        }

        $customerFiles = $Invoice->getCustomerFiles();

        foreach ($customerFiles as $entry) {
            if (empty($entry['options']['attachToEmail'])) {
                continue;
            }

            $file = QUI\ERP\Customer\CustomerFiles::getFileByHash($Invoice->getCustomer()->getId(), $entry['hash']);

            if ($file) {
                $filePath = $file['dirname'] . '/' . $file['basename'];

                if (\file_exists($filePath)) {
                    $Mailer->addAttachment($filePath);
                }
            }
        }
    }

    /**
     * quiqqer/erp: onQuiqqerErpOutputSendMail
     *
     * Save to invoice that a dunning was sent
     *
     * @param $entityId
     * @param string $entityType
     * @param string $recipient
     * @return void
     */
    public static function onQuiqqerErpOutputSendMail($entityId, string $entityType, string $recipient)
    {
        switch ($entityType) {
            case OutputProviderInvoice::getEntityType():
            case OutputProviderCancelled::getEntityType():
            case OutputProviderCreditNote::getEntityType():
                break;

            default:
                return;
        }

        try {
            $Invoice = OutputProviderInvoice::getEntity($entityId);

            $Invoice->addHistory(
                QUI::getLocale()->get(
                    'quiqqer/invoice',
                    'message.add.history.sent.to',
                    [
                        'recipient' => $recipient
                    ]
                )
            );
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return;
        }
    }

    /**
     * PATCH:
     *
     * Writes values to "id_with_prefix" columns in invoice table.
     *
     * @return void
     *
     * @throws QUI\Database\Exception
     * @throws QUI\Exception
     */
    protected static function patchIdWithPrefixColumn()
    {
        $Conf = QUI::getPackage('quiqqer/invoice')->getConfig();

        if (!empty($Conf->getValue('patch', 'id_with_prefix'))) {
            return;
        }

        $table = Handler::getInstance()->invoiceTable();

        $result = QUI::getDataBase()->fetch([
            'select' => ['id', 'id_prefix'],
            'from' => $table
        ]);

        foreach ($result as $row) {
            QUI::getDataBase()->update(
                $table,
                [
                    'id_with_prefix' => $row['id_prefix'] . $row['id']
                ],
                [
                    'id' => $row['id']
                ]
            );
        }

        $Conf->setValue('patch', 'id_with_prefix', 1);
        $Conf->save();
    }
}
