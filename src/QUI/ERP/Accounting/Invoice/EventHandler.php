<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\EventHandler
 */

namespace QUI\ERP\Accounting\Invoice;

use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\ZugferdDocumentPdfMerger;
use QUI;
use QUI\ERP\Accounting\Invoice\Output\OutputProviderCancelled;
use QUI\ERP\Accounting\Invoice\Output\OutputProviderCreditNote;
use QUI\ERP\Accounting\Invoice\Output\OutputProviderInvoice;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;
use QUI\ERP\Exception;
use QUI\ERP\Products\Handler\Fields;
use QUI\ERP\Products\Handler\Search;
use QUI\Mail\Mailer;
use QUI\Package\Package;
use QUI\Smarty\Collector;

use function dirname;
use function file_exists;
use function file_get_contents;
use function in_array;
use function is_numeric;
use function str_replace;
use function strtolower;
use function strtotime;

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
    public static function onPackageSetup(Package $Package): void
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
    public static function onProductsPackageSetup(Package $Package): void
    {
        if ($Package->getName() !== 'quiqqer/products') {
            return;
        }

        try {
            // if the Field exists, we don't need to create it
            Fields::getField(QUI\ERP\Constants::INVOICE_PRODUCT_TEXT_ID);
            return;
        } catch (QUI\ERP\Products\Field\Exception) {
        }

        try {
            Fields::createField([
                'id' => QUI\ERP\Constants::INVOICE_PRODUCT_TEXT_ID,
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
    public static function onTransactionCreate(Transaction $Transaction): void
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
    public static function onTransactionStatusChange(Transaction $Transaction): void
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
     * @throws QUI\Database\Exception
     */
    public static function onFrontendUsersAddressTop(Collector $Collector, QUI\Users\User $User): void
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $current = '';

        if ($User->getAttribute('quiqqer.erp.address')) {
            $current = $User->getAttribute('quiqqer.erp.address');
        }

        $Engine->assign([
            'addresses' => $User->getAddressList(),
            'current' => $current
        ]);

        $result = '<style>';
        $result .= file_get_contents(dirname(__FILE__) . '/FrontendUsers/userProfileAddressSelect.css');
        $result .= '</style>';
        $result .= $Engine->fetch(dirname(__FILE__) . '/FrontendUsers/userProfileAddressSelect.html');

        $Collector->append($result);
    }

    /**
     * @param QUI\Users\User $User
     * @throws QUi\Exception
     */
    public static function onUserSaveBegin(QUI\Users\User $User): void
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
    ): void {
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
    ): void {
        $Handler = Handler::getInstance();
        $invoices = $Handler->getInvoicesByUser($User);

        foreach ($invoices as $Invoice) {
            // created invoice
            $Comments->addComment(
                QUI::getLocale()->get('quiqqer/invoice', 'erp.comment.invoice.created', [
                    'invoiceId' => $Invoice->getPrefixedNumber()
                ]),
                strtotime($Invoice->getAttribute('c_date')),
                'quiqqer/invoice',
                Factory::ERP_INVOICE_ICON,
                false,
                $Invoice->getUUID()
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
     * @param Mailer $Mailer
     * @param string $mailFile
     * @return void
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public static function onQuiqqerErpOutputSendMailBefore(
        $entityId,
        string $entityType,
        string $recipient,
        QUI\Mail\Mailer $Mailer,
        string $mailFile = ''
    ): void {
        $allowedEntityTypes = [
            OutputProviderInvoice::getEntityType(),
            OutputProviderCancelled::getEntityType(),
            OutputProviderCreditNote::getEntityType()
        ];

        if (!in_array($entityType, $allowedEntityTypes)) {
            return;
        }

        try {
            $Invoice = OutputProviderInvoice::getEntity($entityId);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return;
        }

        // extend pdf with e-invoice
        $Config = QUI::getPackage('quiqqer/invoice')->getConfig();

        if (file_exists($mailFile) && $Config->getValue('invoice', 'xInvoiceAttachment')) {
            $xmlFile = str_replace('.pdf', '.xml', $mailFile);
            $document = QUI\ERP\Accounting\Invoice\Utils\Invoice::getElectronicInvoice(
                $Invoice,
                $Config->getValue('invoice', 'xInvoiceAttachmentType')
            );

            $document->writeFile($xmlFile);
            $Mailer->addAttachment($xmlFile);
        }

        // @todo
        $customerFiles = $Invoice->getCustomerFiles();

        foreach ($customerFiles as $entry) {
            if (empty($entry['options']['attachToEmail'])) {
                continue;
            }

            $file = QUI\ERP\Customer\CustomerFiles::getFileByHash($Invoice->getCustomer()->getUUID(), $entry['hash']);

            if ($file) {
                $filePath = $file['dirname'] . '/' . $file['basename'];

                if (file_exists($filePath)) {
                    $Mailer->addAttachment($filePath);
                }
            }
        }
    }

    public static function onQuiqqerHtmlToPDFCreated(QUI\HtmlToPdf\Document $Document, $filename): void
    {
        $Entity = $Document->getAttribute('Entity');

        if (!($Entity instanceof Invoice)) {
            return;
        }

        // extend pdf with e-invoice
        $Config = QUI::getPackage('quiqqer/invoice')->getConfig();

        if (file_exists($filename) && $Config->getValue('invoice', 'zugferdInvoiceAttachment')) {
            $document = QUI\ERP\Accounting\Invoice\Utils\Invoice::getElectronicInvoice(
                $Entity,
                $Config->getValue('invoice', 'xInvoiceAttachmentType')
            );
            $pdfBuilder = new ZugferdDocumentPdfBuilder($document, $filename);
            $pdfBuilder->generateDocument()->saveDocument($filename);
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
    public static function onQuiqqerErpOutputSendMail($entityId, string $entityType, string $recipient): void
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
    protected static function patchIdWithPrefixColumn(): void
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


    /**
     * @throws QUI\Database\Exception
     */
    public static function onQuiqqerMigrationV2(QUI\System\Console\Tools\MigrationV2 $Console): void
    {
        $Console->writeLn('- Migrate invoice');

        $invoiceTable = Handler::getInstance()->invoiceTable();

        // migrate database
        $alterOrderId = function ($table) {
            $tableInfo = QUI::getDatabase()->table()->getFieldsInfos($table);
            $hashFields = [
                'c_user' => 'VARCHAR(50) NOT NULL',
                'editor_id' => 'VARCHAR(50) NULL',
                'customer_id' => 'VARCHAR(50) NOT NULL',
                'invoice_address_id' => 'VARCHAR(50) NULL',
                'ordered_by' => 'VARCHAR(50) NULL',
                'order_id' => 'VARCHAR(50) NULL',
            ];

            foreach ($tableInfo as $tableEntry) {
                $tableField = $tableEntry['Field'];

                if (!isset($hashFields[$tableField])) {
                    continue;
                }

                $swl = $hashFields[$tableField];

                if (!str_contains(strtolower($tableEntry['Type']), 'varchar')) {
                    QUI::getDatabase()->execSQL(
                        "ALTER TABLE `$table` CHANGE `$tableField` `$tableField` $swl;"
                    );
                }
            }
        };

        $alterOrderId($invoiceTable);
        $alterOrderId(Handler::getInstance()->temporaryInvoiceTable());


        QUI\Utils\MigrationV1ToV2::migrateUsers(
            Handler::getInstance()->invoiceTable(),
            [
                'customer_id',
                'ordered_by',
                'c_user',
                'editor_id'
            ]
        );


        // migrate order ids
        $result = QUI::getDataBase()->fetch([
            'from' => Handler::getInstance()->invoiceTable()
        ]);

        foreach ($result as $invoice) {
            if (!is_numeric($invoice['order_id'])) {
                continue;
            }

            if ($invoice['order_id'] == 0) {
                continue;
            }

            try {
                $Order = QUI\ERP\Order\Handler::getInstance()->getOrderById($invoice['order_id']);

                QUI::getDataBase()->update(
                    $invoiceTable,
                    ['order_id' => $Order->getUUID()],
                    ['id' => $invoice['id']]
                );
            } catch (QUI\Exception) {
            }
        }
    }
}
