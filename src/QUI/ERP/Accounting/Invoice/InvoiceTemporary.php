<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\InvoiceTemporary
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;
use QUI\ERP\Customer\CustomerFiles;
use QUI\ERP\Money\Price;
use QUI\ERP\Order\Handler as OrderHandler;
use QUI\Utils\Security\Orthos;

use function array_flip;
use function class_exists;
use function date;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use function json_last_error;
use function mb_strpos;
use function preg_replace;
use function str_replace;
use function strip_tags;
use function strtotime;
use function time;

use const JSON_ERROR_NONE;
use const PHP_INT_MAX;

/**
 * Class InvoiceTemporary
 * - Temporary Invoice / Invoice class
 * - A temporary invoice is a non posted invoice, it is not a real invoice
 * - To create a correct invoice from the temporary invoice, the post method must be used
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class InvoiceTemporary extends QUI\QDOM
{
    /**
     * Special attributes
     */

    /**
     * If this attribute is set to this InvoiceTemporary the invoice is NOT
     * automatically send to the customer when posted REGARDLESS of the setting
     * in this module.
     */
    const SPECIAL_ATTRIBUTE_DO_NOT_SEND_CREATION_MAIL = 'do_not_send_creation_mail';

    /**
     * @var string
     */
    protected string $prefix;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $globalProcessId;

    /**
     * @var int
     */
    protected int $type;

    /**
     * @var array
     */
    protected $data = [];

    /**
     * variable data for developers
     *
     * @var array
     */
    protected $customData = [];

    /**
     * @var array
     */
    protected $paymentData = [];

    /**
     * @var array
     */
    protected array $articles = [];

    /**
     * @var ArticleList
     */
    protected ArticleList $Articles;

    /**
     * @var QUI\ERP\Comments
     */
    protected QUI\ERP\Comments $Comments;

    /**
     * @var QUI\ERP\Comments
     */
    protected QUI\ERP\Comments $History;

    /**
     * @var integer|null
     */
    protected ?int $shippingId = null;

    /**
     * @var array
     */
    protected $addressDelivery = [];

    /**
     * @var null|QUI\ERP\Currency\Currency
     */
    protected ?QUI\ERP\Currency\Currency $Currency = null;

    /**
     * Invoice constructor.
     *
     * @param $id
     * @param Handler $Handler
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function __construct($id, Handler $Handler)
    {
        $data = $Handler->getTemporaryInvoiceData($id);

        $this->prefix = Settings::getInstance()->getTemporaryInvoicePrefix();
        $this->id = (int)str_replace($this->prefix, '', $id);

        $this->Articles = new ArticleList();
        $this->History = new QUI\ERP\Comments();
        $this->Comments = new QUI\ERP\Comments();

        if (!empty($data['delivery_address'])) {
            $deliveryAddressData = json_decode($data['delivery_address'], true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->addressDelivery = $deliveryAddressData;
            } else {
                $this->addressDelivery = false;
            }

            if (
                empty($this->addressDelivery['company'])
                && empty($this->addressDelivery['firstname'])
                && empty($this->addressDelivery['lastname'])
                && empty($this->addressDelivery['street_no'])
                && empty($this->addressDelivery['zip'])
                && empty($this->addressDelivery['city'])
                && empty($this->addressDelivery['county'])
            ) {
                $this->addressDelivery = false;
            }
        } else {
            $this->addressDelivery = false;
        }

        if (isset($data['articles'])) {
            $articles = json_decode($data['articles'], true);

            if ($articles) {
                try {
                    $this->Articles = new ArticleList($articles);
                } catch (QUI\ERP\Exception $Exception) {
                    QUI\System\Log::addError($Exception->getMessage());
                }
            }

            unset($data['articles']);
        }

        if (isset($data['history'])) {
            $this->History = QUI\ERP\Comments::unserialize($data['history']);
        }

        if (isset($data['comments'])) {
            $this->Comments = QUI\ERP\Comments::unserialize($data['comments']);
        }

        if (isset($data['global_process_id']) && !empty($data['global_process_id'])) {
            $this->globalProcessId = $data['global_process_id'];
        } else {
            $this->globalProcessId = $data['hash'];
        }


        // invoice extra data
        $this->data = json_decode($data['data'], true);

        if (!is_array($this->data)) {
            $this->data = [];
        }

        if ($data['custom_data']) {
            $this->customData = json_decode($data['custom_data'], true);
        }

        // invoice payment data
        $paymentData = QUI\Security\Encryption::decrypt($data['payment_data']);
        $paymentData = json_decode($paymentData, true);

        if (is_array($paymentData)) {
            $this->paymentData = $paymentData;
        }

        // invoice type
        $this->type = Handler::TYPE_INVOICE_TEMPORARY;

        switch ((int)$data['type']) {
            case Handler::TYPE_INVOICE:
            case Handler::TYPE_INVOICE_TEMPORARY:
            case Handler::TYPE_INVOICE_CREDIT_NOTE:
            case Handler::TYPE_INVOICE_REVERSAL:
                $this->type = (int)$data['type'];
                break;
        }

        // database attributes
        $data['time_for_payment'] = (int)$data['time_for_payment'];

        if ($data['time_for_payment'] < 0) {
            $data['time_for_payment'] = 0;
        }

        $this->setAttributes($data);

        if (!$this->getCustomer()) {
            $this->setAttribute('invoice_address', false);
            $this->setAttribute('customer_id', false);
        } else {
            $isBrutto = QUI\ERP\Utils\User::getBruttoNettoUserStatus($this->getCustomer());

            if (QUI\ERP\Utils\User::IS_NETTO_USER === $isBrutto) {
                $this->setAttribute('isbrutto', 0);
            } else {
                $this->setAttribute('isbrutto', 1);
            }
        }

        $this->Articles->setCurrency($this->getCurrency());


        // consider contact person in address
        if (
            !empty($this->getAttribute('invoice_address')) &&
            !empty($this->getAttribute('contact_person'))
        ) {
            $invoiceAddress = $this->getAttribute('invoice_address');
            $invoiceAddress = json_decode($invoiceAddress, true);

            $invoiceAddress['contactPerson'] = $this->getAttribute('contact_person');
            $this->setAttribute('invoice_address', json_encode($invoiceAddress));
        }

        // shipping
        if (is_numeric($data['shipping_id'])) {
            $this->shippingId = (int)$data['shipping_id'];
        }

        // accounting currency, if exists
        $accountingCurrencyData = $this->getCustomDataEntry('accountingCurrencyData');

        try {
            if ($accountingCurrencyData) {
                $this->Articles->setExchangeCurrency(
                    new QUI\ERP\Currency\Currency(
                        $accountingCurrencyData['accountingCurrency']
                    )
                );

                $this->Articles->setExchangeRate($accountingCurrencyData['rate']);
            } elseif (QUI\ERP\Currency\Conf::accountingCurrencyEnabled()) {
                $this->Articles->setExchangeCurrency(
                    QUI\ERP\Currency\Conf::getAccountingCurrency()
                );
            }
        } catch (QUI\Exception $Exception) {
        }
    }

    //region Getter

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->prefix . $this->id;
    }

    /**
     * Returns only the integer id
     *
     * @return int
     */
    public function getCleanId(): int
    {
        return (int)str_replace($this->prefix, '', $this->getId());
    }

    /**
     * Return the hash - its the unique invoice id
     *
     * @return mixed
     */
    public function getHash()
    {
        return $this->getAttribute('hash');
    }

    /**
     * Return the global process id
     *
     * @return string
     */
    public function getGlobalProcessId(): string
    {
        return $this->globalProcessId;
    }

    /**
     * Return the invoice type
     * - Handler::TYPE_INVOICE
     * - Handler::TYPE_INVOICE_TEMPORARY
     * - Handler::TYPE_INVOICE_CREDIT_NOTE
     * - Handler::TYPE_INVOICE_REVERSAL
     *
     * @return int
     */
    public function getInvoiceType(): int
    {
        return $this->type;
    }

    /**
     * Return the customer
     *
     * @return null|QUI\ERP\User|QUI\Users\Nobody|QUI\Users\SystemUser|QUI\Users\User
     */
    public function getCustomer()
    {
        $invoiceAddress = $this->getAttribute('invoice_address');

        try {
            $User = QUI::getUsers()->get($this->getAttribute('customer_id'));
        } catch (QUI\Users\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return null;
        }

        $userData = [
            'id' => $User->getId(),
            'country' => $User->getCountry(),
            'username' => $User->getUsername(),
            'firstname' => $User->getAttribute('firstname'),
            'lastname' => $User->getAttribute('lastname'),
            'lang' => $User->getLang(),
            'email' => $User->getAttribute('email')
        ];

        $Customer = false;

        try {
            $Customer = QUI\ERP\User::convertUserToErpUser($User);
            $userData = $Customer->getAttributes();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }


        // address
        if (is_string($invoiceAddress)) {
            $invoiceAddress = json_decode($invoiceAddress, true);
        }

        if (!isset($userData['isCompany'])) {
            $userData['isCompany'] = false;

            if (isset($invoiceAddress['company'])) {
                $userData['isCompany'] = true;
            }
        }

        if (!empty($invoiceAddress)) {
            if (!empty($this->getAttribute('contact_person'))) {
                $invoiceAddress['contactPerson'] = $this->getAttribute('contact_person');
            }

            $userData['address'] = $invoiceAddress;

            if (empty($userData['firstname']) && !empty($invoiceAddress['firstname'])) {
                $userData['firstname'] = $invoiceAddress['firstname'];
            }

            if (empty($userData['lastname']) && !empty($invoiceAddress['lastname'])) {
                $userData['lastname'] = $invoiceAddress['lastname'];
            }
        }

        if (empty($userData['country'])) {
            $userData['country'] = QUI\ERP\Defaults::getCountry()->getCode();
        }

        if (!empty($invoiceAddress['contactEmail'])) {
            $userData['contactEmail'] = $invoiceAddress['contactEmail'];
        }

        try {
            return new QUI\ERP\User($userData);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        $this->setAttribute('customer_id', false);
        $this->setAttribute('invoice_address', false);

        return null;
    }

    /**
     * @param string|QUI\ERP\Currency\Currency $currency
     */
    public function setCurrency($currency)
    {
        if (is_string($currency)) {
            try {
                $currency = QUI\ERP\Currency\Handler::getCurrency($currency);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addError($Exception->getMessage());

                return;
            }
        }

        if (!($currency instanceof QUI\ERP\Currency\Currency)) {
            return;
        }

        $this->setAttribute('currency_data', $currency->toArray());
        $this->Articles->setCurrency($this->Currency);
        $this->Currency = $this->getCurrency();
    }

    /**
     * Return the invoice currency
     *
     * @return QUI\ERP\Currency\Currency
     *
     * @throws QUI\Exception
     */
    public function getCurrency(): QUI\ERP\Currency\Currency
    {
        if ($this->Currency !== null) {
            return $this->Currency;
        }

        $currency = $this->getAttribute('currency_data');

        if (!$currency) {
            return QUI\ERP\Defaults::getCurrency();
        }

        if (is_string($currency)) {
            $currency = json_decode($currency, true);
        }

        if (!$currency || !isset($currency['code'])) {
            return QUI\ERP\Defaults::getCurrency();
        }

        $Currency = QUI\ERP\Currency\Handler::getCurrency($currency['code']);

        if (isset($currency['rate'])) {
            $Currency->setExchangeRate($currency['rate']);
        }

        $this->Currency = $Currency;

        return $Currency;
    }

    /**
     * Return the editor user
     *
     * @return null|QUI\Interfaces\Users\User
     */
    public function getEditor(): ?QUI\Interfaces\Users\User
    {
        $Employees = QUI\ERP\Employee\Employees::getInstance();

        if ($this->getAttribute('editor_id')) {
            try {
                $Editor = QUI::getUsers()->get($this->getAttribute('editor_id'));
//                $isInGroup = $Editor->isInGroup($Employees->getEmployeeGroup()->getId());
//
//                if ($isInGroup) {
                return $Editor;
//                }
            } catch (QUI\Exception $Exception) {
            }
        }

        // use default advisor as editor
        if (empty($editorId) && $Employees->getDefaultAdvisor()) {
            return $Employees->getDefaultAdvisor();
        }

        return null;
    }

    /**
     * Return the ordered by user
     *
     * @return null|QUI\Interfaces\Users\User
     */
    public function getOrderedByUser(): ?QUI\Interfaces\Users\User
    {
        if ($this->getAttribute('ordered_by')) {
            try {
                return QUI::getUsers()->get($this->getAttribute('ordered_by'));
            } catch (QUI\Exception $Exception) {
            }
        }

        return null;
    }

    /**
     * Return all fields, attributes which are still missing to post the invoice
     *
     * @return array
     */
    public function getMissingAttributes(): array
    {
        return Utils\Invoice::getMissingAttributes($this);
    }

    /**
     * Return a invoice view
     *
     * @return InvoiceView
     *
     * @throws Exception
     */
    public function getView(): InvoiceView
    {
        return new InvoiceView($this);
    }

    /**
     * @return Payment
     */
    public function getPayment(): Payment
    {
        $paymentMethod = $this->getAttribute('payment_method');

        if (empty($paymentMethod)) {
            return new Payment([]);
        }

        try {
            $Payment = QUI\ERP\Accounting\Payments\Payments::getInstance()->getPayment($paymentMethod);

            return new Payment($this->parsePaymentForPaymentData($Payment));
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return new Payment([]);
    }

    /**
     * @return array
     * @throws QUI\ERP\Exception
     */
    public function getPaidStatusInformation(): array
    {
        QUI\ERP\Accounting\Calc::calculatePayments($this);

        return [
            'paidData' => $this->getAttribute('paid_data'),
            'paidDate' => $this->getAttribute('paid_date'),
            'paid' => $this->getAttribute('paid'),
            'toPay' => $this->getAttribute('toPay')
        ];
    }

    /**
     * Return the is paid status
     *
     * @return bool
     */
    public function isPaid(): bool
    {
        try {
            if ($this->getAttribute('toPay') === false) {
                QUI\ERP\Accounting\Calc::calculatePayments($this);
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        return $this->getAttribute('toPay') <= 0;
    }

    /**
     * can a refund be made?
     *
     * @return bool
     */
    public function hasRefund(): bool
    {
        return false;
    }

    //endregion

    /**
     * Change the invoice type
     *
     * The following types can be used:
     * - Handler::TYPE_INVOICE_TEMPORARY:
     * - Handler::TYPE_INVOICE_CREDIT_NOTE:
     * - Handler::TYPE_INVOICE_REVERSAL:
     *
     * @param string|integer $type
     * @param QUI\Interfaces\Users\User|null $PermissionUser - optional
     *
     * @throws QUI\Permissions\Exception
     * @throws QUI\Exception
     */
    public function setInvoiceType($type, $PermissionUser = null)
    {
        QUI\Permissions\Permission::checkPermission(
            'quiqqer.invoice.temporary.edit',
            $PermissionUser
        );

        $type = (int)$type;

        switch ($type) {
            case Handler::TYPE_INVOICE_TEMPORARY:
            case Handler::TYPE_INVOICE_CREDIT_NOTE:
            case Handler::TYPE_INVOICE_REVERSAL:
                break;

            default:
                return;
        }

        $this->type = $type;
        $typeTitle = QUI::getLocale()->get('quiqqer/invoice', 'invoice.type.' . $type);

        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/invoice',
                'history.message.temporaryInvoice.type.changed',
                [
                    'type' => $type,
                    'typeTitle' => $typeTitle
                ]
            )
        );
    }

    /**
     * Alias for update
     * (Save the current temporary invoice data to the database)
     *
     * @param QUI\Interfaces\Users\User|null $PermissionUser
     *
     * @throws QUI\Permissions\Exception
     * @throws QUI\Lock\Exception
     * @throws QUI\Exception
     */
    public function save($PermissionUser = null)
    {
        $this->update($PermissionUser);
    }

    /**
     * Save the current temporary invoice data to the database
     *
     * @param QUI\Interfaces\Users\User|null $PermissionUser
     *
     * @throws QUI\Permissions\Exception
     * @throws QUI\Lock\Exception
     * @throws QUI\Exception
     */
    public function update($PermissionUser = null)
    {
        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        if (!$this->getCustomer() || $PermissionUser->getId() !== $this->getCustomer()->getId()) {
            QUI\Permissions\Permission::checkPermission(
                'quiqqer.invoice.temporary.edit',
                $PermissionUser
            );
        }

        $this->checkLocked();

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoiceSaveBegin',
            [$this]
        );

        // attributes
        $projectName = '';
        $date = '';

        $isBrutto = QUI\ERP\Defaults::getBruttoNettoStatus();

        if ($this->getCustomer()) {
            $isBrutto = QUI\ERP\Utils\User::getBruttoNettoUserStatus($this->getCustomer());
        }

        if ($this->getAttribute('project_name')) {
            $projectName = $this->getAttribute('project_name');
        }

        if ($this->getAttribute('time_for_payment') || $this->getAttribute('time_for_payment') === 0) {
            $timeForPayment = (int)$this->getAttribute('time_for_payment');
        } else {
            $timeForPayment = Settings::getInstance()->get('invoice', 'time_for_payment');
        }

        if ($timeForPayment < 0) {
            $timeForPayment = 0;
        }

        if (
            $this->getAttribute('date')
            && Orthos::checkMySqlDatetimeSyntax($this->getAttribute('date'))
        ) {
            $date = $this->getAttribute('date');
        }

        // address
        $contactEmail = $this->getAttribute('contactEmail') ?: false;
        $invoiceAddressId = false;

        try {
            $Customer = QUI::getUsers()->get((int)$this->getAttribute('customer_id'));

            $Address = $Customer->getAddress(
                (int)$this->getAttribute('invoice_address_id')
            );

            $invoiceAddressData = $this->getAttribute('invoice_address');
            $invoiceAddressData = json_decode($invoiceAddressData, true);

            if ($invoiceAddressData) {
                $Address->setAttributes($invoiceAddressData);
            }

            $Address->setAttribute('contactEmail', $contactEmail);

            $invoiceAddress = $Address->toJSON();
            $this->Articles->setUser($Customer);

            $invoiceAddressId = $Address->getId();
        } catch (QUI\Exception $Exception) {
            $invoiceAddress = $this->getAttribute('invoice_address');
            $invoiceAddressCheck = false;

            if (is_string($invoiceAddress)) {
                $invoiceAddressCheck = json_decode($invoiceAddress, true);
                $invoiceAddressCheck['contactEmail'] = $contactEmail;

                $invoiceAddress = json_encode($invoiceAddressCheck);
            } elseif (is_array($invoiceAddress)) {
                $invoiceAddress['contactEmail'] = $contactEmail;
                $invoiceAddress = json_encode($invoiceAddress);
            }

            if (empty($invoiceAddressCheck['id'])) {
                QUI\System\Log::addNotice($Exception->getMessage());
            } else {
                $invoiceAddressId = $invoiceAddressCheck['id'];
            }
        }

        $this->Articles->calc();
        $listCalculations = $this->Articles->getCalculations();

        // payment
        $paymentMethod = '';

        try {
            if ($this->getAttribute('payment_method')) {
                $Payments = QUI\ERP\Accounting\Payments\Payments::getInstance();
                $Payment = $Payments->getPayment($this->getAttribute('payment_method'));

                $paymentMethod = $Payment->getId();
            }
        } catch (QUI\ERP\Accounting\Payments\Exception $Exception) {
            QUI\System\Log::addNotice($Exception->getMessage());
        }

        //invoice text
        $invoiceText = $this->getAttribute('additional_invoice_text');

        if ($invoiceText === false) {
            $invoiceText = QUI::getLocale()->get('quiqqer/invoice', 'additional.invoice.text');
        }

        // extra EU vat invoice text
        if ($listCalculations['isEuVat'] && empty($listCalculations['vatArray'])) {
            $Locale = $this->getCustomer()->getLocale();

            $extraText = '<br />';
            $extraText .= $Locale->get('quiqqer/tax', 'message.EUVAT.addition', [
                'user' => $this->getCustomer()->getInvoiceName(),
                'vatId' => $this->getCustomer()->getAttribute('quiqqer.erp.euVatId')
            ]);


            $invoiceTextClean = trim(strip_tags($invoiceText));
            $invoiceTextClean = str_replace("\n", ' ', $invoiceTextClean);
            $invoiceTextClean = html_entity_decode($invoiceTextClean);
            $invoiceTextClean = preg_replace('#( ){2,}#', "$1", $invoiceTextClean);

            $extraTextClean = trim(strip_tags($extraText));
            $extraTextClean = str_replace("\n", ' ', $extraTextClean);
            $extraTextClean = html_entity_decode($extraTextClean);
            $extraTextClean = preg_replace('#( ){2,}#', "$1", $extraTextClean);

            if (mb_strpos($invoiceTextClean, $extraTextClean) === false) {
                $invoiceText .= $extraText;
            }
        }

        // Editor
        $Editor = $this->getEditor();
        $editorId = 0;
        $editorName = '';

        // use default advisor as editor
        if ($Editor) {
            $editorId = $Editor->getId();
            $editorName = $Editor->getName();
        }

        // Ordered By
        $OrderedBy = $this->getOrderedByUser();
        $orderedBy = (int)$this->getAttribute('customer_id');
        $orderedByName = '';

        if ($OrderedBy) {
            $orderedBy = $OrderedBy->getId();
            $orderedByName = $OrderedBy->getName();
        } elseif ($orderedBy) {
            try {
                $User = QUI::getUsers()->get($orderedBy);
                $orderedBy = $User->getId();
                $orderedByName = $User->getName();
            } catch (QUI\Exception $Exception) {
            }
        }

        // shipping
        $shippingId = null;
        $shippingData = null;

        if ($this->getShipping()) {
            $shippingId = $this->getShipping()->getId();
            $shippingData = $this->getShipping()->toJSON();
        }

        // delivery address
        $deliveryAddress = '';
        $deliveryAddressId = null;
        $DeliveryAddress = $this->getDeliveryAddress();

        // Save delivery address separately only if it differs from invoice address
        if ($DeliveryAddress && ($invoiceAddressId && $DeliveryAddress->getId() !== $invoiceAddressId)) {
            $deliveryAddress = $DeliveryAddress->toJSON();
            $deliveryAddressId = $DeliveryAddress->getId();

            if (empty($deliveryAddressId)) {
                $deliveryAddressId = null;
            }
        }

        // processing status
        $processingStatus = null;

        if (is_numeric($this->getAttribute('processing_status'))) {
            $processingStatus = (int)$this->getAttribute('processing_status');

            try {
                ProcessingStatus\Handler::getInstance()->getProcessingStatus($processingStatus);
            } catch (ProcessingStatus\Exception $Exception) {
                $processingStatus = null;
            }
        }

        // contact person
        $contactPerson = '';

        if ($this->getAttribute('contact_person') && $this->getAttribute('contact_person') !== '') {
            $contactPerson = Orthos::clear($this->getAttribute('contact_person'));
        }

        // order date
        $orderDate = null;

        if (
            $this->getAttribute('order_date')
            && Orthos::checkMySqlDatetimeSyntax($this->getAttribute('order_date'))
        ) {
            $orderDate = $this->getAttribute('order_date');
        }

        // Determine payment status by order (if an order is linked to the invoice)
        $paidStatus = QUI\ERP\Constants::PAYMENT_STATUS_OPEN;
        $paidDate = null;
        $paidData = '';
        $orderId = $this->getAttribute('order_id');

        if (!empty($orderId)) {
            $Orders = OrderHandler::getInstance();

            try {
                $Order = $Orders->getOrderById($orderId);
                $orderPaidStatusData = $Order->getPaidStatusInformation();

                $paidStatus = $Order->getAttribute('paid_status');
                $paidDate = $orderPaidStatusData['paidDate'];
                $paidData = \json_encode($orderPaidStatusData['paidData']);
            } catch (\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoiceSave',
            [$this]
        );

        QUI::getDataBase()->update(
            Handler::getInstance()->temporaryInvoiceTable(),
            [
                'type' => $this->getInvoiceType(),

                // user relationships
                'customer_id' => (int)$this->getAttribute('customer_id'),
                'editor_id' => $editorId,
                'editor_name' => $editorName,
                'order_id' => $orderId,
                'ordered_by' => $orderedBy,
                'order_date' => $orderDate,
                'ordered_by_name' => $orderedByName,
                'contact_person' => $contactPerson,

                // payments
                'payment_method' => $paymentMethod,
                'payment_data' => QUI\Security\Encryption::encrypt(json_encode($this->paymentData)),
                'payment_time' => null,

                // address
                'invoice_address_id' => (int)$this->getAttribute('invoice_address_id'),
                'invoice_address' => $invoiceAddress,
                'delivery_address_id' => $deliveryAddressId,
                'delivery_address' => $deliveryAddress,

                // processing
                'time_for_payment' => $timeForPayment,
                'paid_status' => $paidStatus,
                'paid_date' => $paidDate,
                'paid_data' => $paidData,
                'processing_status' => $processingStatus,
                'customer_data' => '',   // nicht in gui

                // shipping
                'shipping_id' => $shippingId,
                'shipping_data' => $shippingData,

                // invoice data
                'date' => $date,
                'data' => json_encode($this->data),
                'articles' => $this->Articles->toJSON(),
                'history' => $this->History->toJSON(),
                'comments' => $this->Comments->toJSON(),
                'additional_invoice_text' => $invoiceText,
                'project_name' => $projectName,

                // Calc data
                'isbrutto' => $isBrutto,
                'currency_data' => json_encode($this->getCurrency()->toArray()),
                'currency' => $this->getCurrency()->getCode(),
                'nettosum' => $listCalculations['nettoSum'],
                'subsum' => InvoiceUtils::roundInvoiceSum($listCalculations['subSum']),
                'sum' => InvoiceUtils::roundInvoiceSum($listCalculations['sum']),
                'vat_array' => json_encode($listCalculations['vatArray'])
            ],
            [
                'id' => $this->getCleanId()
            ]
        );

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoiceEnd',
            [$this]
        );
    }

    /**
     * Delete the temporary invoice
     *
     * @param QUI\Interfaces\Users\User|null $PermissionUser
     *
     * @throws QUI\Permissions\Exception
     * @throws QUI\Lock\Exception
     * @throws QUI\Exception
     */
    public function delete($PermissionUser = null)
    {
        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        QUI\Permissions\Permission::checkPermission(
            'quiqqer.invoice.temporary.delete',
            $PermissionUser
        );

        $this->checkLocked();

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoiceDelete',
            [$this]
        );

        QUI::getDataBase()->delete(
            Handler::getInstance()->temporaryInvoiceTable(),
            ['id' => $this->getCleanId()]
        );
    }

    /**
     * Copy the temporary invoice
     *
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @return InvoiceTemporary
     *
     * @throws QUI\Permissions\Exception
     * @throws QUI\Exception
     * @throws Exception
     */
    public function copy($PermissionUser = null): InvoiceTemporary
    {
        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        QUI\Permissions\Permission::checkPermission(
            'quiqqer.invoice.temporary.copy',
            $PermissionUser
        );

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoiceCopy',
            [$this]
        );

        $Handler = Handler::getInstance();
        $Factory = Factory::getInstance();
        $New = $Factory->createInvoice($PermissionUser);

        $currentData = QUI::getDataBase()->fetch([
            'from' => $Handler->temporaryInvoiceTable(),
            'where' => [
                'id' => $this->getCleanId()
            ],
            'limit' => 1
        ]);

        $currentData = $currentData[0];
        $currentData['hash'] = QUI\Utils\Uuid::get();

        unset($currentData['id']);
        unset($currentData['c_user']);
        unset($currentData['date']);

        QUI::getDataBase()->update(
            $Handler->temporaryInvoiceTable(),
            $currentData,
            ['id' => $New->getCleanId()]
        );

        $Copy = $Handler->getTemporaryInvoice($New->getId());

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoiceCopyEnd',
            [$this, $Copy]
        );

        return $Copy;
    }

    /**
     * Creates an invoice from the temporary invoice
     * Its post the invoice
     *
     * @param QUI\Interfaces\Users\User|null $PermissionUser
     * @return Invoice
     *
     * @throws Exception
     * @throws QUI\Permissions\Exception
     * @throws QUI\Exception
     */
    public function post($PermissionUser = null): Invoice
    {
        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        if ($PermissionUser->getId() !== $this->getCustomer()->getId()) {
            QUI\Permissions\Permission::checkPermission(
                'quiqqer.invoice.post',
                $PermissionUser
            );
        }

        $this->checkLocked();

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoicePostBegin',
            [$this]
        );

        $this->save(QUI::getUsers()->getSystemUser());

        // check all current data
        $this->validate();

        // data
        $User = QUI::getUserBySession();
        $date = date('Y-m-d H:i:s'); // invoice date, to today
        $isBrutto = QUI\ERP\Defaults::getBruttoNettoStatus();
        $Customer = $this->getCustomer();
        $Handler = Handler::getInstance();

        if (QUI\Permissions\Permission::hasPermission('quiqqer.invoice.changeDate')) {
            if (
                $this->getAttribute('date')
                && Orthos::checkMySqlDatetimeSyntax($this->getAttribute('date'))
            ) {
                $date = $this->getAttribute('date');
            }
        }

        if ($Customer) {
            $isBrutto = QUI\ERP\Utils\User::getBruttoNettoUserStatus($this->getCustomer());
        }

        // address
        try {
            $Address = $Customer->getAddress(
                (int)$this->getAttribute('invoice_address_id')
            );

            $invoiceAddress = $Address->toJSON();
        } catch (QUI\Exception $Exception) {
            $invoiceAddress = $this->getAttribute('invoice_address');

            if (!$invoiceAddress) {
                throw $Exception;
            }
        }

        // customerData
        $ErpCustomer = QUI\ERP\User::convertUserToErpUser($Customer);

        $customerData = $ErpCustomer->getAttributes();
        $customerData['erp.isNettoUser'] = $Customer->getAttribute('quiqqer.erp.isNettoUser');
        $customerData['erp.euVatId'] = $Customer->getAttribute('quiqqer.erp.euVatId');
        $customerData['erp.taxId'] = $Customer->getAttribute('quiqqer.erp.taxId');


        // Editor
        $Editor = $this->getEditor();
        $editorId = 0;
        $editorName = '';

        // use default advisor as editor
        if ($Editor) {
            $editorId = $Editor->getId();
            $editorName = $Editor->getName();
        }

        // Ordered By
        $OrderedBy = $this->getOrderedByUser();
        $orderedBy = (int)$this->getAttribute('customer_id');
        $orderedByName = '';


        // use default advisor as editor
        if ($OrderedBy) {
            $orderedBy = $OrderedBy->getId();
            $orderedByName = $OrderedBy->getName();
        } elseif ($orderedBy) {
            try {
                $User = QUI::getUsers()->get($orderedBy);
                $orderedBy = $User->getId();
                $orderedByName = $User->getName();
            } catch (QUI\Exception $Exception) {
            }
        }

        // user is customer, than the customer is the creator
        if ($User->getId() === $Customer->getId()) {
            $User = $Customer;
        }

        // payment stuff
        $Payments = QUI\ERP\Accounting\Payments\Payments::getInstance();
        $Payment = $Payments->getPayment($this->getAttribute('payment_method'));

        $paymentMethodData = $this->parsePaymentForPaymentData($Payment);
        $paymentTime = (int)$this->getAttribute('time_for_payment');

        if ($paymentTime < 0) {
            $paymentTime = 0;
        }

        $timeForPayment = strtotime(date('Y-m-d') . ' 00:00 + ' . $paymentTime . ' days');
        $timeForPayment = date('Y-m-d', $timeForPayment);
        $timeForPayment .= ' 23:59:59';


        // post and calc
        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoicePost',
            [$this]
        );

        $type = $this->getInvoiceType();

        if ($type === Handler::TYPE_INVOICE_TEMPORARY) {
            $type = Handler::TYPE_INVOICE;
        }

        // article calc
        $this->Articles->setUser($Customer);
        $this->Articles->setCurrency($this->getCurrency());
        $this->Articles->calc();

        $listCalculations = $this->Articles->getCalculations();
        $uniqueList = $this->Articles->toUniqueList()->toArray();

        $uniqueList['calculations']['sum'] = InvoiceUtils::roundInvoiceSum($uniqueList['calculations']['sum']);
        $uniqueList['calculations']['subSum'] = InvoiceUtils::roundInvoiceSum($uniqueList['calculations']['subSum']);
        $uniqueList = json_encode($uniqueList);

        //shipping
        $shippingId = null;
        $shippingData = null;

        if ($this->getShipping()) {
            $shippingId = $this->getShipping()->getId();
            $shippingData = $this->getShipping()->toJSON();
        }

        // delivery address
        $deliveryAddress = $this->getAttribute('delivery_address');

        if (empty($deliveryAddress)) {
            $DeliveryAddress = $this->getDeliveryAddress();

            if ($DeliveryAddress) {
                $deliveryAddress = $DeliveryAddress->toJSON();
            }
        }

        // processing status
        $processingStatus = null;

        if (is_numeric($this->getAttribute('processing_status'))) {
            $processingStatus = (int)$this->getAttribute('processing_status');

            try {
                ProcessingStatus\Handler::getInstance()->getProcessingStatus($processingStatus);
            } catch (ProcessingStatus\Exception $Exception) {
                $processingStatus = null;
            }
        }

        // payment custom data
        try {
            $InvoicePayment = new Payment($paymentMethodData);
            $this->customData['InvoiceInformationText'] = $InvoicePayment->getInvoiceInformationText($this);
        } catch (\Exception $Exception) {
        }

        // contact person
        $contactPerson = '';

        if ($this->getAttribute('contact_person') && $this->getAttribute('contact_person') !== '') {
            $contactPerson = Orthos::clear($this->getAttribute('contact_person'));
        }

        // order date
        $orderDate = null;

        if (
            $this->getAttribute('order_date')
            && Orthos::checkMySqlDatetimeSyntax($this->getAttribute('order_date'))
        ) {
            $orderDate = $this->getAttribute('order_date');
        }

        // check if hash already exists
        try {
            $oldHash = $this->getAttribute('hash');
            $HashCheckInvoice = Handler::getInstance()->getInvoiceByHash($oldHash);

            if (
                $HashCheckInvoice instanceof InvoiceTemporary &&
                $HashCheckInvoice->getCleanId() !== $this->getCleanId()
            ) {
                // if invoice hash exist, we need a new hash
                $this->setAttribute('hash', QUI\Utils\Uuid::get());

                $this->getComments()->addComment(
                    QUI::getLocale()->get(
                        'quiqqer/invoice',
                        'history.InvoiceTemporary.new_hash_on_post',
                        [
                            'oldHash' => $oldHash,
                            'newHash' => $this->getAttribute('hash')
                        ]
                    )
                );
            }
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        // create invoice
        $invoicePrefix = Settings::getInstance()->getInvoicePrefix();

        $paidStatusInformation = $this->getPaidStatusInformation();

        QUI::getDataBase()->insert(
            $Handler->invoiceTable(),
            [
                'type' => $type,
                'id_prefix' => $invoicePrefix,
                'global_process_id' => $this->getGlobalProcessId(),

                // user relationships
                'c_user' => $User->getId(),
                'c_username' => $User->getName(),
                'editor_id' => $editorId,
                'editor_name' => $editorName,
                'order_id' => $this->getAttribute('order_id'),
                'order_date' => $orderDate,
                'ordered_by' => $orderedBy,
                'ordered_by_name' => $orderedByName,
                'contact_person' => $contactPerson,
                'customer_id' => $this->getCustomer()->getId(),
                'customer_data' => json_encode($customerData),

                // addresses
                'invoice_address' => $invoiceAddress,
                'delivery_address' => $deliveryAddress,

                // payments
                'payment_method' => $this->getAttribute('payment_method'),
                'payment_method_data' => json_encode($paymentMethodData),
                'payment_data' => QUI\Security\Encryption::encrypt(json_encode($this->paymentData)),
                'payment_time' => null,
                'time_for_payment' => $timeForPayment,

                // paid status
                'paid_status' => $this->getAttribute('paid_status'),
                'paid_date' => $paidStatusInformation['paidDate'],
                'paid_data' => \json_encode($paidStatusInformation['paidData']),
                'processing_status' => $processingStatus,

                // shipping
                'shipping_id' => $shippingId,
                'shipping_data' => $shippingData,

                // data
                'hash' => $this->getAttribute('hash'),
                'project_name' => $this->getAttribute('project_name'),
                'date' => $date,
                'data' => json_encode($this->data),
                'additional_invoice_text' => $this->getAttribute('additional_invoice_text'),
                'transaction_invoice_text' => $this->getView()->getTransactionText(),
                'articles' => $uniqueList,
                'history' => $this->getHistory()->toJSON(),
                'comments' => $this->getComments()->toJSON(),
                'custom_data' => json_encode($this->customData),

                // calculation data
                'isbrutto' => $isBrutto === QUI\ERP\Utils\User::IS_BRUTTO_USER ? 1 : 0,
                'currency_data' => json_encode($this->getCurrency()->toArray()),
                'currency' => $this->getCurrency()->getCode(),
                'nettosum' => $listCalculations['nettoSum'],
                'nettosubsum' => $listCalculations['nettoSubSum'],
                'subsum' => InvoiceUtils::roundInvoiceSum($listCalculations['subSum']),
                'sum' => InvoiceUtils::roundInvoiceSum($listCalculations['sum']),
                'vat_array' => json_encode($listCalculations['vatArray'])
            ]
        );

        $newId = QUI::getDataBase()->getPDO()->lastInsertId('id');

        // Insert full id with prefix
        QUI::getDataBase()->update(
            $Handler->invoiceTable(),
            ['id_with_prefix' => $invoicePrefix . $newId],
            ['id' => $newId]
        );

        // if temporary invoice was a credit note
        // add history entry to original invoice
        if (isset($this->data['originalId'])) {
            try {
                $Parent = $Handler->getInvoice($this->data['originalId']);
                $Parent->addHistory(
                    QUI::getLocale()->get(
                        'quiqqer/invoice',
                        'message.create.credit.post',
                        [
                            'invoiceId' => $this->getId()
                        ]
                    )
                );
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        $this->delete(QUI::getUsers()->getSystemUser());

        // invoice payment calculation
        $Invoice = $Handler->getInvoice($newId);
        $calculation = QUI\ERP\Accounting\Calc::calculatePayments($Invoice);

        if (empty($calculation)) {
            QUI::getDataBase()->update(
                $Handler->invoiceTable(),
                ['paid_status' => $calculation['paidStatus']],
                ['id' => $newId]
            );
        } else {
            QUI::getDataBase()->update(
                $Handler->invoiceTable(),
                [
                    'paid_data' => json_encode($calculation['paidData']),
                    'paid_date' => (int)$calculation['paidDate'],
                    'paid_status' => (int)$calculation['paidStatus']
                ],
                ['id' => $newId]
            );
        }

        // set accounting currency, if it needed
        if (QUI\ERP\Currency\Conf::accountingCurrencyEnabled()) {
            $AccountingCurrency = QUI\ERP\Currency\Conf::getAccountingCurrency();

            $acData = [
                'accountingCurrency' => $AccountingCurrency->toArray(),
                'currency' => $this->getCurrency()->toArray(),
                'rate' => $this->getCurrency()->getExchangeRate($AccountingCurrency)
            ];

            $Invoice->addCustomDataEntry('accountingCurrencyData', $acData);
        }

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoicePostEnd',
            [$this, $Invoice]
        );

        // send invoice mail
        try {
            if (Settings::getInstance()->sendMailAtInvoiceCreation()) {
                $this->sendCreationMail($Invoice);
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            // @todo history info das mail nicht raus ging
        }

        return $Invoice;
    }

    /**
     * Alias for post()
     *
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @return Invoice
     *
     * @throws Exception
     * @throws QUI\Permissions\Exception
     * @throws QUI\Exception
     */
    public function createInvoice($PermissionUser = null): Invoice
    {
        return $this->post($PermissionUser);
    }

    /**
     * Verificate / Validate the invoice
     * Can the invoice be posted?
     *
     * @throws QUI\ERP\Accounting\Invoice\Exception
     */
    public function validate()
    {
        $missing = $this->getMissingAttributes();

        foreach ($missing as $field) {
            throw new Exception(Utils\Invoice::getMissingAttributeMessage($field));
        }
    }

    /**
     * Parse the Temporary invoice to an array
     *
     * @return array
     */
    public function toArray(): array
    {
        $attributes = $this->getAttributes();

        $attributes['id'] = $this->getId();
        $attributes['type'] = $this->getInvoiceType();
        $attributes['articles'] = $this->Articles->toArray();

        $attributes['globalProcessId'] = $this->getGlobalProcessId();

        return $attributes;
    }


    /**
     * @param Transaction $Transaction
     *
     * @throws QUI\Exception
     */
    public function addTransaction(Transaction $Transaction)
    {
        QUI\ERP\Debug::getInstance()->log('Invoice:: add transaction');

        if ($this->getHash() !== $Transaction->getHash()) {
            return;
        }

        $currentPaidStatus = $this->getAttribute('paid_status');

        QUI\ERP\Accounting\Calc::calculatePayments($this);

        if (
            $this->getInvoiceType() == Handler::TYPE_INVOICE_REVERSAL
            || $this->getInvoiceType() == Handler::TYPE_INVOICE_CANCEL
            || $this->getInvoiceType() == Handler::TYPE_INVOICE_CREDIT_NOTE
        ) {
            return;
        }

        if (
            $currentPaidStatus === $this->getAttribute('paid_status')
            && ($this->getAttribute('paid_status') == QUI\ERP\Constants::PAYMENT_STATUS_PAID
                || $this->getAttribute('paid_status') == QUI\ERP\Constants::PAYMENT_STATUS_CANCELED)
        ) {
            return;
        }

        QUI\ERP\Debug::getInstance()->log('Order:: add transaction start');

        $User = QUI::getUserBySession();
        $paidData = $this->getAttribute('paid_data');
        $amount = Price::validatePrice($Transaction->getAmount());
        $date = $Transaction->getDate();

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryAddTransactionBegin',
            [
                $this,
                $amount,
                $Transaction,
                $date
            ]
        );

        if (!$amount) {
            return;
        }

        if (!is_array($paidData)) {
            $paidData = json_decode($paidData, true);
        }

        if ($date === false) {
            $date = time();
        }

        $isTxAlreadyAdded = function ($txid, $paidData) {
            foreach ($paidData as $paidEntry) {
                if (!isset($paidEntry['txid'])) {
                    continue;
                }

                if ($paidEntry['txid'] == $txid) {
                    return true;
                }
            }

            return false;
        };

        // already added
        if ($isTxAlreadyAdded($Transaction->getTxId(), $paidData)) {
            return;
        }

        $isValidTimeStamp = function ($timestamp) {
            return ((string)(int)$timestamp === $timestamp)
                && ($timestamp <= PHP_INT_MAX)
                && ($timestamp >= ~PHP_INT_MAX);
        };

        if ($isValidTimeStamp($date) === false) {
            $date = strtotime($date);

            if ($isValidTimeStamp($date) === false) {
                $date = time();
            }
        }

        $this->setAttribute('paid_date', $date);


        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/invoice',
                'history.message.addPayment',
                [
                    'username' => $User->getName(),
                    'uid' => $User->getId(),
                    'txid' => $Transaction->getTxId()
                ]
            )
        );

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryAddTransaction',
            [
                $this,
                $amount,
                $Transaction,
                $date
            ]
        );

        //$this->calculatePayments();
        QUI\ERP\Accounting\Calc::calculatePayments($this);


        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryAddTransactionEnd',
            [
                $this,
                $amount,
                $Transaction,
                $date
            ]
        );
    }

    //region Article Management

    /**
     * Add an article
     *
     * @param QUI\ERP\Accounting\Article $Article
     */
    public function addArticle(QUI\ERP\Accounting\Article $Article)
    {
        $this->Articles->addArticle($Article);
    }

    /**
     * @param int $index
     */
    public function removeArticle(int $index)
    {
        $this->Articles->removeArticle($index);
    }

    /**
     * Returns the article list
     *
     * @return ArticleList
     */
    public function getArticles(): ArticleList
    {
        if ($this->Currency && $this->Currency->getCode() !== QUI\ERP\Defaults::getCurrency()->getCode()) {
            $this->Articles->setExchangeCurrency(
                $this->Currency
            );
        }

        return $this->Articles;
    }

    /**
     * Remove all articles from the invoice
     */
    public function clearArticles()
    {
        $this->Articles->clear();
    }

    /**
     * Import an article array
     *
     * @param array $articles - array of articles, eq: [0] => Array
     * (
     *       [productId] => 5
     *       [type] => QUI\ERP\Products\Product\Product
     *       [position] => 1
     *       [quantity] => 1
     *       [unitPrice] => 0
     *       [vat] =>
     *       [title] => USS Enterprise NCC-1701-D
     *       [description] => Raumschiff aus bekannter Serie
     *       [control] => package/quiqqer/products/bin/controls/invoice/Product
     * )
     */
    public function importArticles($articles = [])
    {
        if (!is_array($articles)) {
            $articles = [];
        }

        if (isset($articles['articles'])) {
            $this->importArticles($articles['articles']);

            return;
        }

        foreach ($articles as $article) {
            try {
                if (isset($article['class']) && class_exists($article['class'])) {
                    $Article = new $article['class']($article);

                    if ($Article instanceof QUI\ERP\Accounting\Article) {
                        $this->addArticle($Article);
                        continue;
                    }
                }

                $Article = new QUI\ERP\Accounting\Article($article);
                $this->addArticle($Article);
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        try {
            $this->Articles->setCurrency($this->getCurrency());
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }
    }

    //endregion

    //region Comments

    /**
     * Return the comments list object
     *
     * @return QUI\ERP\Comments
     */
    public function getComments(): QUI\ERP\Comments
    {
        return $this->Comments;
    }

    /**
     * Add a comment
     *
     * @param string $message
     *
     * @throws QUI\Permissions\Exception
     * @throws QUI\Lock\Exception
     * @throws QUI\Exception
     */
    public function addComment(string $message)
    {
        $message = strip_tags(
            $message,
            '<div><span><pre><p><br><hr>
            <ul><ol><li><dl><dt><dd><strong><em><b><i><u>
            <img><table><tbody><td><tfoot><th><thead><tr>'
        );

        $this->Comments->addComment($message);
        $this->save();

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoiceAddComment',
            [$this, $message]
        );

        $User = QUI::getUserBySession();

        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/invoice',
                'history.message.addComment',
                [
                    'username' => $User->getName(),
                    'uid' => $User->getId()
                ]
            )
        );
    }

    //endregion

    //region History

    /**
     * Return the history list object
     *
     * @return QUI\ERP\Comments
     */
    public function getHistory(): QUI\ERP\Comments
    {
        return $this->History;
    }

    /**
     * Add a history entry
     *
     * @param string $message
     * @throws QUI\Exception
     */
    public function addHistory(string $message)
    {
        $this->History->addComment($message);

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoiceAddHistory',
            [$this, $message]
        );
    }

    //endregion

    //region custom data for dev's

    /**
     * Add a custom data entry
     *
     * @param string $key
     * @param mixed $value
     *
     * @throws QUI\Database\Exception
     * @throws QUI\Exception
     * @throws QUI\ExceptionStack
     */
    public function addCustomDataEntry(string $key, $value)
    {
        $this->customData[$key] = $value;

        QUI::getDataBase()->update(
            Handler::getInstance()->temporaryInvoiceTable(),
            ['custom_data' => json_encode($this->customData)],
            ['id' => $this->getCleanId()]
        );

        QUI::getEvents()->fireEvent(
            'onQuiqqerInvoiceTemporaryAddCustomData',
            [$this, $this, $this->customData, $key, $value]
        );
    }

    /**
     * Return a wanted custom data entry
     *
     * @param $key
     * @return mixed|null
     */
    public function getCustomDataEntry($key)
    {
        if (isset($this->customData[$key])) {
            return $this->customData[$key];
        }

        return null;
    }

    /**
     * Return all custom data
     *
     * @return array|mixed
     */
    public function getCustomData(): array
    {
        return $this->customData;
    }

    //endregion

    //region Data

    /**
     * Set a data value
     *
     * @param string $key
     * @param mixed $value
     */
    public function setData(string $key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * Return a data field
     *
     * @param string $key
     * @return bool|array|mixed
     */
    public function getData(string $key)
    {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }

        return false;
    }

    /**
     * Parses the payment for the invoice payment method data field
     *
     * @param QUI\ERP\Accounting\Payments\Types\PaymentInterface $Payment
     * @return array
     */
    protected function parsePaymentForPaymentData(QUI\ERP\Accounting\Payments\Types\PaymentInterface $Payment): array
    {
        $data = $Payment->toArray();
        $Locale = new QUI\Locale();
        $languages = QUI\Translator::getAvailableLanguages();

        $data['title'] = [];
        $data['workingTitle'] = [];
        $data['description'] = [];

        foreach ($languages as $language) {
            $Locale->setCurrent($language);

            $data['title'][$language] = $Payment->getTitle($Locale);
            $data['workingTitle'][$language] = $Payment->getWorkingTitle($Locale);
            $data['description'][$language] = $Payment->getDescription($Locale);
        }

        return $data;
    }

    //endregion

    //region Payment Data

    /**
     * Set a payment data value
     *
     * @param string $key
     * @param mixed $value
     */
    public function setPaymentData(string $key, $value)
    {
        $this->paymentData[$key] = $value;
    }

    /**
     * Return a payment data field
     *
     * @param string $key
     * @return bool|array|mixed
     */
    public function getPaymentData(string $key)
    {
        if (isset($this->paymentData[$key])) {
            return $this->paymentData[$key];
        }

        return false;
    }

    //endregion

    //region LOCK

    /**
     * Lock the invoice
     * Invoice can't be edited
     *
     * @throws QUI\Lock\Exception
     * @throws QUI\Exception
     */
    public function lock()
    {
        $Package = QUI::getPackage('quiqqer/invoice');
        $key = 'temporary-invoice-' . $this->getId();

        QUI\Lock\Locker::lock($Package, $key);
    }

    /**
     * Unlock the invoice
     * Invoice can be edited
     *
     * @throws QUI\Lock\Exception
     * @throws QUI\Exception
     */
    public function unlock()
    {
        $Package = QUI::getPackage('quiqqer/invoice');
        $key = 'temporary-invoice-' . $this->getId();

        QUI\Lock\Locker::unlock($Package, $key);
    }

    /**
     * Is the invoice locked?
     *
     * @return false|mixed
     *
     * @throws QUI\Exception
     */
    public function isLocked(): bool
    {
        $Package = QUI::getPackage('quiqqer/invoice');
        $key = 'temporary-invoice-' . $this->getId();

        return QUI\Lock\Locker::isLocked($Package, $key);
    }

    /**
     * Check, if the item is locked
     *
     * @throws QUI\Lock\Exception
     * @throws QUI\Exception
     */
    public function checkLocked()
    {
        $Package = QUI::getPackage('quiqqer/invoice');
        $key = 'temporary-invoice-' . $this->getId();

        QUI\Lock\Locker::checkLocked($Package, $key);
    }

    //endregion

    //region mails

    /**
     * Send the invoice to the customer via email
     *
     * @param Invoice $Invoice
     * @throws QUI\Exception
     */
    protected function sendCreationMail(Invoice $Invoice)
    {
        if (!empty($this->getAttribute(self::SPECIAL_ATTRIBUTE_DO_NOT_SEND_CREATION_MAIL))) {
            return;
        }

        try {
            $Customer = $Invoice->getCustomer();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return;
        }

        if (!$Customer) {
            return;
        }

        $email = QUI\ERP\Customer\Utils::getInstance()->getEmailByCustomer($Customer);

        if (!empty($email)) {
            $Invoice->sendTo($email);
        }
    }

    //endregion

    //region shipping

    /**
     * Return the shipping
     *
     * @return QUI\ERP\Shipping\Types\ShippingEntry|null
     */
    public function getShipping()
    {
        if ($this->shippingId === null) {
            return null;
        }

        if (!class_exists('QUI\ERP\Shipping\Shipping')) {
            return null;
        }

        $Shipping = QUI\ERP\Shipping\Shipping::getInstance();

        try {
            return $Shipping->getShippingEntry($this->shippingId);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return null;
    }

    /**
     * Set the shipping
     *
     * @param QUI\ERP\Shipping\Api\ShippingInterface $Shipping
     */
    public function setShipping(QUI\ERP\Shipping\Api\ShippingInterface $Shipping)
    {
        $this->shippingId = $Shipping->getId();
    }

    //endregion

    //region delivery address

    /**
     * Return delivery address
     *
     * @return QUI\ERP\Address|null
     */
    public function getDeliveryAddress(): ?QUI\ERP\Address
    {
        $delivery = $this->addressDelivery;

        if (isset($delivery['id'])) {
            unset($delivery['id']);
        }

        if (empty($delivery)) {
            try {
                $Customer = QUI::getUsers()->get((int)$this->getAttribute('customer_id'));

                $Address = $Customer->getAddress(
                    (int)$this->getAttribute('invoice_address_id')
                );

                if ($Address) {
                    $addressData = $Address->getAttributes();
                    $addressData['id'] = $Address->getId();

                    return new QUI\ERP\Address($addressData, $this->getCustomer());
                }
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }
        }

        if (empty($this->addressDelivery)) {
            return null;
        }

        return new QUI\ERP\Address($this->addressDelivery, $this->getCustomer());
    }

    /**
     * Set the delivery address
     *
     * @param array|QUI\ERP\Address $address
     */
    public function setDeliveryAddress($address)
    {
        if ($address instanceof QUI\ERP\Address) {
            $this->addressDelivery = $address->getAttributes();

            return;
        }

        if (is_array($address)) {
            $this->addressDelivery = $this->parseAddressData($address);
        }
    }

    /**
     * @param array $address
     * @return array
     */
    protected function parseAddressData(array $address): array
    {
        $fields = array_flip([
            'id',
            'salutation',
            'firstname',
            'lastname',
            'street_no',
            'zip',
            'city',
            'country',
            'company',
            'isCompany'
        ]);

        $result = [];

        foreach ($address as $entry => $value) {
            if (isset($fields[$entry])) {
                $result[$entry] = $value;
            }
        }

        return $result;
    }

    /**
     * Removes any delivery address that is saved in this invoice
     *
     * @return void
     */
    public function removeDeliveryAddress()
    {
        $this->addressDelivery = false;
    }

    // endregion

    // region Attached customer files

    /**
     * Add a customer file to this invoice
     *
     * @param string $fileHash - SHA256 hash of the file basename
     * @param array $options (optional) - File options; see $defaultOptions in code for what's possible
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function addCustomerFile(string $fileHash, array $options = [])
    {
        $Customer = $this->getCustomer();

        if (empty($Customer)) {
            throw new Exception(
                QUI::getLocale()->get('quiqqer/invoice', 'exception.Invoice.addCustomerFile.no_customer')
            );
        }

        $file = CustomerFiles::getFileByHash($Customer->getId(), $fileHash);

        if (empty($file)) {
            throw new Exception(
                QUI::getLocale()->get('quiqqer/invoice', 'exception.Invoice.addCustomerFile.file_not_found')
            );
        }

        $defaultOptions = [
            'attachToEmail' => false
        ];

        // set default options
        foreach ($defaultOptions as $k => $v) {
            if (!isset($options[$k])) {
                $options[$k] = $v;
            }
        }

        // cleanup
        foreach ($options as $k => $v) {
            if (!isset($defaultOptions[$k])) {
                unset($options[$k]);
            }
        }

        $fileEntry = [
            'hash' => $fileHash,
            'options' => $options
        ];

        $customerFiles = $this->getCustomerFiles();
        $customerFiles[] = $fileEntry;

        $this->setData('customer_files', $customerFiles);
    }

    /**
     * Clear customer files
     *
     * @return void
     */
    public function clearCustomerFiles(): void
    {
        $this->setData('customer_files', null);
    }

    /**
     * Get customer files that are attached to this invoice.
     *
     * @return array - Contains file hash and file options
     */
    public function getCustomerFiles(): array
    {
        $customerFiles = $this->getData('customer_files');

        if (empty($customerFiles)) {
            return [];
        }

        return $customerFiles;
    }

    // endregion
}
