<?php

namespace QUI\ERP\Accounting\Invoice\Output;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Exception;
use IntlDateFormatter;
use QUI;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Invoice\Settings;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;
use QUI\ERP\Accounting\Payments\Methods\AdvancePayment\Payment as AdvancePayment;
use QUI\ERP\Accounting\Payments\Methods\Invoice\Payment as InvoicePayment;
use QUI\ERP\BankAccounts\Handler as BankAccounts;
use QUI\ERP\Customer\Utils as CustomerUtils;
use QUI\ERP\Output\OutputProviderInterface;
use QUI\ERP\Payments\SEPA\Provider as SepaProvider;
use QUI\Interfaces\Users\User;
use QUI\Locale;

use function array_merge;
use function get_class;
use function implode;
use function in_array;
use function json_decode;
use function number_format;
use function strtotime;
use function time;
use function trim;

use const PHP_EOL;

/**
 * Class OutputProvider
 *
 * Output provider for quiqqer/invoice:
 *
 * Outputs previews and PDF files for invoices and temporary invoices
 */
class OutputProviderInvoice implements OutputProviderInterface
{
    /**
     * Get output type
     *
     * The output type determines the type of templates/providers that are used
     * to output documents.
     *
     * @return string
     */
    public static function getEntityType(): string
    {
        return 'Invoice';
    }

    /**
     * Get title for the output entity
     *
     * @param Locale $Locale (optional) - If ommitted use \QUI::getLocale()
     * @return mixed
     */
    public static function getEntityTypeTitle(Locale $Locale = null): mixed
    {
        if (empty($Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/invoice', 'OutputProvider.entity.title.Invoice');
    }

    /**
     * Get the entity the output is created for
     *
     * @param int|string $entityId
     * @return QUI\ERP\Accounting\Invoice\Invoice|InvoiceTemporary
     *
     * @throws QUI\Exception
     */
    public static function getEntity(int|string $entityId): mixed
    {
        return InvoiceUtils::getInvoiceByString($entityId);
    }

    /**
     * Get download filename (without file extension)
     *
     * @param int|string $entityId
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getDownloadFileName(int|string $entityId): string
    {
        return InvoiceUtils::getInvoiceFilename(self::getEntity($entityId));
    }

    /**
     * Get output Locale by entity
     *
     * @param int|string $entityId
     * @return Locale
     *
     * @throws QUI\Exception
     */
    public static function getLocale(int|string $entityId): Locale
    {
        $Invoice = self::getEntity($entityId);
        $Customer = $Invoice->getCustomer();

        if ($Customer) {
            return $Customer->getLocale();
        }

        return QUI::getLocale();
    }

    /**
     * Fill the OutputTemplate with appropriate entity data
     *
     * @param int|string $entityId
     * @return array
     */
    public static function getTemplateData(int|string $entityId): array
    {
        $Invoice = self::getEntity($entityId);
        $InvoiceView = $Invoice->getView();
        $Customer = $Invoice->getCustomer();

        if (!$Customer) {
            $Customer = new QUI\ERP\User([
                'id' => '',
                'country' => '',
                'username' => '',
                'firstname' => '',
                'lastname' => '',
                'lang' => '',
                'isCompany' => '',
                'isNetto' => ''
            ]);
        }

        $Editor = $Invoice->getEditor();

        try {
            $Address = $Customer->getStandardAddress();
            $Address->clearMail();
            $Address->clearPhone();
        } catch (QUI\Exception) {
            $Address = null;

            if ($Invoice->getAttribute('invoice_address')) {
                $address = json_decode($Invoice->getAttribute('invoice_address'), true);

                if (!empty($address)) {
                    $Address = new QUI\ERP\Address($address, $Customer);
                    $Address->clearMail();
                    $Address->clearPhone();
                }
            }
        }

        // list calculation
        $Articles = $Invoice->getArticles();

        if (get_class($Articles) !== QUI\ERP\Accounting\ArticleListUnique::class) {
            $Articles->setUser($Customer);
            $Articles = $Articles->toUniqueList();
        }

        // Delivery address
        $DeliveryAddress = false;
        $deliveryAddress = $Invoice->getAttribute('delivery_address');

        if (!empty($deliveryAddress)) {
            $DeliveryAddress = new QUI\ERP\Address(
                json_decode($deliveryAddress, true),
                $Customer
            );

            $DeliveryAddress->clearMail();
            $DeliveryAddress->clearPhone();

            if ($DeliveryAddress->equals($Address)) {
                $DeliveryAddress = false;
            }
        }

        QUI::getLocale()->setTemporaryCurrent($Customer->getLang());

        $InvoiceView->setAttributes($Invoice->getAttributes());

        // global invoice text
        $globalInvoiceText = '';

        if (QUI::getLocale()->get('quiqqer/invoice', 'global.invoice.text') !== '') {
            $globalInvoiceText = QUI::getLocale()->get('quiqqer/invoice', 'global.invoice.text');
        }

        // order number
        $orderNumber = '';

        if ($InvoiceView->getAttribute('order_id')) {
            try {
                $Order = QUI\ERP\Order\Handler::getInstance()->getOrderById($InvoiceView->getAttribute('order_id'));
                $orderNumber = $Order->getPrefixedId();
            } catch (QUI\Exception) {
            }
        }

        // EPC QR Code
        $epcQrCodeImageSrc = false;

        if (Settings::getInstance()->isIncludeQrCode()) {
            $epcQrCodeImageSrc = self::getEpcQrCodeImageImgSrc($Invoice);
        }

        return [
            'this' => $InvoiceView,
            'ArticleList' => $Articles,
            'Customer' => $Customer,
            'Editor' => $Editor,
            'Address' => $Address,
            'DeliveryAddress' => $DeliveryAddress,
            'Payment' => $Invoice->getPayment(),
            'transaction' => $InvoiceView->getTransactionText(),
            'projectName' => $Invoice->getAttribute('project_name'),
            'useShipping' => QUI::getPackageManager()->isInstalled('quiqqer/shipping'),
            'globalInvoiceText' => $globalInvoiceText,
            'orderNumber' => $orderNumber,
            'epcQrCodeImageSrc' => $epcQrCodeImageSrc
        ];
    }

    /**
     * Checks if $User has permission to download the document of $entityId
     *
     * @param int|string $entityId
     * @param User $User
     * @return bool
     */
    public static function hasDownloadPermission(int|string $entityId, User $User): bool
    {
        if (!QUI::getUsers()->isAuth($User) || QUI::getUsers()->isNobodyUser($User)) {
            return false;
        }

        try {
            $Invoice = self::getEntity($entityId);
            $Customer = $Invoice->getCustomer();

            if (empty($Customer)) {
                return false;
            }

            return $User->getId() === $Customer->getId();
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return false;
        }
    }

    /**
     * Get e-mail address of the document recipient
     *
     * @param int|string $entityId
     * @return string|false - E-Mail address or false if no e-mail address available
     *
     * @throws QUI\Exception
     */
    public static function getEmailAddress(int|string $entityId): bool|string
    {
        $Invoice = self::getEntity($entityId);
        $Customer = $Invoice->getCustomer();

        if (empty($Customer)) {
            return false;
        }

        if (!empty($Customer->getAttribute('contactEmail'))) {
            return $Customer->getAttribute('contactEmail');
        }

        return QUI\ERP\Customer\Utils::getInstance()->getEmailByCustomer($Customer);
    }

    /**
     * Get e-mail subject when document is sent via mail
     *
     * @param int|string $entityId
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getMailSubject(int|string $entityId): string
    {
        $Invoice = self::getEntity($entityId);
        $Customer = $Invoice->getCustomer();

        return $Invoice->getCustomer()->getLocale()->get(
            'quiqqer/invoice',
            'invoice.send.mail.subject',
            self::getInvoiceLocaleVar($Invoice, $Customer)
        );
    }

    /**
     * Get e-mail body when document is sent via mail
     *
     * @param int|string $entityId
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getMailBody(int|string $entityId): string
    {
        $Invoice = self::getEntity($entityId);
        $Customer = $Invoice->getCustomer();

        return $Customer->getLocale()->get(
            'quiqqer/invoice',
            'invoice.send.mail.message',
            self::getInvoiceLocaleVar($Invoice, $Customer)
        );
    }

    /**
     * @param $Invoice
     * @param QUI\ERP\User $Customer
     * @return array
     */
    protected static function getInvoiceLocaleVar($Invoice, $Customer): array
    {
        $CustomerAddress = $Customer->getAddress();
        $user = $CustomerAddress->getAttribute('contactPerson');

        if (empty($user)) {
            $user = $Customer->getName();
        }

        if (empty($user)) {
            $user = $Customer->getAddress()->getName();
        }

        $user = trim($user);

        // contact person
        $contactPerson = $Invoice->getAttribute('contact_person');

        if (empty($contactPerson)) {
            // Fetch contact person from live user (if existing)
            $ContactPersonAddress = CustomerUtils::getInstance()->getContactPersonAddress($Customer);

            if ($ContactPersonAddress) {
                $contactPerson = $ContactPersonAddress->getName();
            }
        }

        if (empty($contactPerson)) {
            $contactPerson = $user;
        }

        $contactPersonOrName = $contactPerson;

        if (empty($contactPersonOrName)) {
            $contactPersonOrName = $user;
        }

        return array_merge([
            'invoiceId' => $Invoice->getId(),
            'hash' => $Invoice->getAttribute('hash'),
            'date' => self::dateFormat($Invoice->getAttribute('date')),
            'systemCompany' => self::getCompanyName(),

            'contactPerson' => $contactPerson,
            'contactPersonOrName' => $contactPersonOrName
        ], self::getCustomerVariables($Customer));
    }

    /**
     * Return the company name of the quiqqer system
     *
     * @return string
     */
    protected static function getCompanyName(): string
    {
        try {
            $Conf = QUI::getPackage('quiqqer/erp')->getConfig();
            $company = $Conf->get('company', 'name');
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return '';
        }

        if (empty($company)) {
            return '';
        }

        return $company;
    }

    /**
     * @param QUI\ERP\User $Customer
     * @return string
     */
    protected static function getCompanyOrName(QUI\ERP\User $Customer): string
    {
        $Address = $Customer->getStandardAddress();

        if (!empty($Address->getAttribute('company'))) {
            return $Address->getAttribute('company');
        }

        return $Customer->getName();
    }

    /**
     * @param QUI\ERP\User $Customer
     * @return array
     */
    public static function getCustomerVariables(QUI\ERP\User $Customer): array
    {
        $Address = $Customer->getAddress();

        // customer name
        $user = $Address->getAttribute('contactPerson');

        if (empty($user)) {
            $user = $Customer->getName();
        }

        if (empty($user)) {
            $user = $Address->getName();
        }

        $user = trim($user);

        // email
        $email = $Customer->getAttribute('email');

        if (empty($email)) {
            $mailList = $Address->getMailList();

            if (isset($mailList[0])) {
                $email = $mailList[0];
            }
        }

        return [
            'user' => $user,
            'name' => $user,
            'company' => $Customer->getStandardAddress()->getAttribute('company'),
            'companyOrName' => self::getCompanyOrName($Customer),
            'address' => $Address->render(),
            'email' => $email,
            'salutation' => $Address->getAttribute('salutation'),
            'firstname' => $Address->getAttribute('firstname'),
            'lastname' => $Address->getAttribute('lastname')
        ];
    }

    /**
     * @param $date
     * @return false|string
     */
    public static function dateFormat($date)
    {
        // date
        $localeCode = QUI::getLocale()->getLocalesByLang(
            QUI::getLocale()->getCurrent()
        );

        $Formatter = new IntlDateFormatter(
            $localeCode[0],
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE
        );

        if (!$date) {
            $date = time();
        } else {
            $date = strtotime($date);
        }

        return $Formatter->format($date);
    }

    /**
     * Get raw base64 img src for EPC QR code.
     *
     * @param QUI\ERP\Accounting\Invoice\Invoice|InvoiceTemporary $Invoice
     * @return string|false - Raw <img> "src" attribute with base64 image data or false if code can or must not be generated.
     */
    protected static function getEpcQrCodeImageImgSrc($Invoice)
    {
        try {
            // Check currency (must be EUR)
            if ($Invoice->getCurrency()->getCode() !== 'EUR') {
                return false;
            }

            // Check payment type (must be "invoice" or "pay in advance")
            $paymentTypeClassName = $Invoice->getPayment()->getPaymentType();

            $allowedPaymentTypeClasses = [
                AdvancePayment::class,
                InvoicePayment::class
            ];

            if (!in_array($paymentTypeClassName, $allowedPaymentTypeClasses)) {
                return false;
            }

            $varDir = QUI::getPackage('quiqqer/invoice')->getVarDir();
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }


        // Prefer bank account set in SEPA module if available
        if (QUI::getPackageManager()->isInstalled('quiqqer/payment-sepa')) {
            $creditorBankAccount = SepaProvider::getCreditorBankAccount();
        } else {
            $creditorBankAccount = BankAccounts::getCompanyBankAccount();
        }

        $requiredFields = [
            'accountHolder',
            'iban',
            'bic',
        ];

        foreach ($requiredFields as $requiredField) {
            if (empty($creditorBankAccount[$requiredField])) {
                return false;
            }
        }

        try {
            $paidStatus = $Invoice->getPaidStatusInformation();
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }


        $amount = $paidStatus['toPay'];

        if ($amount <= 0) {
            return false;
        }

        $purposeText = QUI::getLocale()->get(
            'quiqqer/invoice',
            'OutputProvider.epc_qr_code_purpose',
            [
                'invoiceNo' => $Invoice->getId()
            ]
        );

        // @todo Warnung, wenn $purposeText zu lang

        // See
        $qrCodeLines = [
            'BCD',
            '002',
            '1', // UTF-8
            'SCT',
            $creditorBankAccount['bic'],
            $creditorBankAccount['accountHolder'],
            $creditorBankAccount['iban'],
            'EUR' . number_format($amount, 2, '.', ''),
            '',
            '',
            $purposeText
        ];

        $qrCodeText = implode(PHP_EOL, $qrCodeLines);

        $QrOptions = new QROptions([
            'version' => QRCode::VERSION_AUTO,
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_M,
            'pngCompression' => -1
        ]);

        $QrCode = new QRCode($QrOptions);
        return $QrCode->render($qrCodeText);
    }
}
