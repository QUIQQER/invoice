<?php

namespace QUI\ERP\Accounting\Invoice\Output;

use QUI;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Output\OutputProviderInterface;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;
use QUI\Interfaces\Users\User;
use QUI\Locale;

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
    public static function getEntityType()
    {
        return 'Invoice';
    }

    /**
     * Get title for the output entity
     *
     * @param Locale $Locale (optional) - If ommitted use \QUI::getLocale()
     * @return mixed
     */
    public static function getEntityTypeTitle(Locale $Locale = null)
    {
        if (empty($Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/invoice', 'OutputProvider.entity.title.Invoice');
    }

    /**
     * Get the entity the output is created for
     *
     * @param string|int $entityId
     * @return QUI\ERP\Accounting\Invoice\Invoice|InvoiceTemporary
     *
     * @throws QUI\Exception
     */
    public static function getEntity($entityId)
    {
        return InvoiceUtils::getInvoiceByString($entityId);
    }

    /**
     * Get download filename (without file extension)
     *
     * @param string|int $entityId
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getDownloadFileName($entityId)
    {
        return InvoiceUtils::getInvoiceFilename(self::getEntity($entityId));
    }

    /**
     * Get output Locale by entity
     *
     * @param string|int $entityId
     * @return Locale
     *
     * @throws QUI\Exception
     */
    public static function getLocale($entityId)
    {
        $Invoice  = self::getEntity($entityId);
        $Customer = $Invoice->getCustomer();

        if ($Customer) {
            return $Customer->getLocale();
        }

        return QUI::getLocale();
    }

    /**
     * Fill the OutputTemplate with appropriate entity data
     *
     * @param string|int $entityId
     * @return array
     */
    public static function getTemplateData($entityId)
    {
        $Invoice     = self::getEntity($entityId);
        $InvoiceView = $Invoice->getView();
        $Customer    = $Invoice->getCustomer();

        if (!$Customer) {
            $Customer = new QUI\ERP\User([
                'id'        => '',
                'country'   => '',
                'username'  => '',
                'firstname' => '',
                'lastname'  => '',
                'lang'      => '',
                'isCompany' => '',
                'isNetto'   => ''
            ]);
        }

        $Editor = $Invoice->getEditor();

        try {
            $Address = $Customer->getStandardAddress();
            $Address->clearMail();
            $Address->clearPhone();
        } catch (QUI\Exception $Exception) {
            $Address = null;

            if ($Invoice->getAttribute('invoice_address')) {
                $address = \json_decode($Invoice->getAttribute('invoice_address'), true);

                if (!empty($address)) {
                    $Address = new QUI\ERP\Address($address, $Customer);
                    $Address->clearMail();
                    $Address->clearPhone();
                }
            }
        }

        // list calculation
        $Articles = $Invoice->getArticles();

        if (\get_class($Articles) !== QUI\ERP\Accounting\ArticleListUnique::class) {
            $Articles->setUser($Customer);
            $Articles = $Articles->toUniqueList();
        }

        // Delivery address
        $DeliveryAddress = false;
        $deliveryAddress = $Invoice->getAttribute('delivery_address');

        if (!empty($deliveryAddress)) {
            $DeliveryAddress = new QUI\ERP\Address(
                \json_decode($deliveryAddress, true),
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
                $Order       = QUI\ERP\Order\Handler::getInstance()->getOrderById($InvoiceView->getAttribute('order_id'));
                $orderNumber = $Order->getPrefixedId();
            } catch (QUI\Exception $Exception) {
            }
        }

        return [
            'this'              => $InvoiceView,
            'ArticleList'       => $Articles,
            'Customer'          => $Customer,
            'Editor'            => $Editor,
            'Address'           => $Address,
            'DeliveryAddress'   => $DeliveryAddress,
            'Payment'           => $Invoice->getPayment(),
            'transaction'       => $InvoiceView->getTransactionText(),
            'projectName'       => $Invoice->getAttribute('project_name'),
            'useShipping'       => QUI::getPackageManager()->isInstalled('quiqqer/shipping'),
            'globalInvoiceText' => $globalInvoiceText,
            'orderNumber'       => $orderNumber
        ];
    }

    /**
     * Checks if $User has permission to download the document of $entityId
     *
     * @param string|int $entityId
     * @param User $User
     * @return bool
     */
    public static function hasDownloadPermission($entityId, User $User)
    {
        if (!QUI::getUsers()->isAuth($User) || QUI::getUsers()->isNobodyUser($User)) {
            return false;
        }

        try {
            $Invoice  = self::getEntity($entityId);
            $Customer = $Invoice->getCustomer();

            if (empty($Customer)) {
                return false;
            }

            return $User->getId() === $Customer->getId();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return false;
        }
    }

    /**
     * Get e-mail address of the document recipient
     *
     * @param string|int $entityId
     * @return string|false - E-Mail address or false if no e-mail address available
     *
     * @throws QUI\Exception
     */
    public static function getEmailAddress($entityId)
    {
        $Invoice  = self::getEntity($entityId);
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
     * @param string|int $entityId
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getMailSubject($entityId)
    {
        $Invoice  = self::getEntity($entityId);
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
     * @param string|int $entityId
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getMailBody($entityId)
    {
        $Invoice  = self::getEntity($entityId);
        $Customer = $Invoice->getCustomer();

        return $Customer->getLocale()->get(
            'quiqqer/invoice',
            'invoice.send.mail.message',
            self::getInvoiceLocaleVar($Invoice, $Customer)
        );
    }

    /**
     * @param $Invoice
     * @param $Customer
     * @return array
     */
    protected static function getInvoiceLocaleVar($Invoice, $Customer): array
    {
        $user = $Customer->getName();
        $user = \trim($user);

        if (empty($user)) {
            $user = $Customer->getAddress()->getName();
        }

        // contact person
        $contactPerson = $Invoice->getAttribute('contact_person');

        if (empty($contactPerson)) {
            $contactPerson = $user;
        }

        $contactPersonOrName = $contactPerson;

        if (empty($contactPersonOrName)) {
            $contactPersonOrName = $user;
        }

        return \array_merge([
            'invoiceId'     => $Invoice->getId(),
            'hash'          => $Invoice->getAttribute('hash'),
            'date'          => self::dateFormat($Invoice->getAttribute('date')),
            'systemCompany' => self::getCompanyName(),

            'contactPerson'       => $contactPerson,
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
            $Conf    = QUI::getPackage('quiqqer/erp')->getConfig();
            $company = $Conf->get('company', 'name');
        } catch (\Exception $Exception) {
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
        $user = $Customer->getName();
        $user = \trim($user);

        if (empty($user)) {
            $user = $Address->getName();
        }

        // email
        $email = $Customer->getAttribute('email');

        if (empty($email)) {
            $mailList = $Address->getMailList();

            if (isset($mailList[0])) {
                $email = $mailList[0];
            }
        }


        return [
            'user'          => $user,
            'name'          => $user,
            'company'       => $Customer->getStandardAddress()->getAttribute('company'),
            'companyOrName' => self::getCompanyOrName($Customer),
            'address'       => $Address->render(),
            'email'         => $email,
            'salutation'    => $Address->getAttribute('salutation'),
            'firstname'     => $Address->getAttribute('firstname'),
            'lastname'      => $Address->getAttribute('lastname')
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

        $Formatter = new \IntlDateFormatter(
            $localeCode[0],
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::NONE
        );

        if (!$date) {
            $date = \time();
        } else {
            $date = \strtotime($date);
        }

        return $Formatter->format($date);
    }
}
