<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Invoice
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;
use QUI\Permissions\Permission;
use QUI\ERP\Money\Price;
use QUI\ERP\Accounting\ArticleListUnique;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;
use QUI\ERP\Output\Output as ERPOutput;

/**
 * Class Invoice
 * - Invoice class
 * - This class present a posted invoice
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class Invoice extends QUI\QDOM
{
    /* @deprecated */
    const PAYMENT_STATUS_OPEN = QUI\ERP\Constants::PAYMENT_STATUS_OPEN;

    /* @deprecated */
    const PAYMENT_STATUS_PAID = QUI\ERP\Constants::PAYMENT_STATUS_PAID;

    /* @deprecated */
    const PAYMENT_STATUS_PART = QUI\ERP\Constants::PAYMENT_STATUS_PART;

    /* @deprecated */
    const PAYMENT_STATUS_ERROR = QUI\ERP\Constants::PAYMENT_STATUS_ERROR;

    /* @deprecated */
    const PAYMENT_STATUS_CANCELED = QUI\ERP\Constants::PAYMENT_STATUS_CANCELED;

    /* @deprecated */
    const PAYMENT_STATUS_DEBIT = QUI\ERP\Constants::PAYMENT_STATUS_DEBIT;

    //    const PAYMENT_STATUS_CANCEL = 3;
    //    const PAYMENT_STATUS_STORNO = 3; // Alias for cancel
    //    const PAYMENT_STATUS_CREATE_CREDIT = 5;

    const DUNNING_LEVEL_OPEN = 0; // No Dunning -> Keine Mahnung
    const DUNNING_LEVEL_REMIND = 1; // Payment reminding -> Zahlungserinnerung
    const DUNNING_LEVEL_DUNNING = 2; // Dunning -> Erste Mahnung
    const DUNNING_LEVEL_DUNNING2 = 3; // Second dunning -> Zweite Mahnung
    const DUNNING_LEVEL_COLLECTION = 4; // Collection -> Inkasso

    /**
     * Invoice type
     *
     * @var int
     */
    protected $type;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $globalProcessId;

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
     * @var null|integer
     */
    protected $Shipping = null;

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
        $this->setAttributes($Handler->getInvoiceData($id));

        $this->prefix = $this->getAttribute('id_prefix');

        if ($this->prefix === false) {
            $this->prefix = Settings::getInstance()->getInvoicePrefix();
        }

        $this->id   = (int)\str_replace($this->prefix, '', $id);
        $this->type = Handler::TYPE_INVOICE;

        switch ((int)$this->getAttribute('type')) {
            case Handler::TYPE_INVOICE:
            case Handler::TYPE_INVOICE_TEMPORARY:
            case Handler::TYPE_INVOICE_CREDIT_NOTE:
            case Handler::TYPE_INVOICE_REVERSAL:
            case Handler::TYPE_INVOICE_CANCEL:
                $this->type = (int)$this->getAttribute('type');
                break;
        }

        if ($this->getAttribute('data')) {
            $this->data = \json_decode($this->getAttribute('data'), true);
        }

        if ($this->getAttribute('custom_data')) {
            $this->customData = \json_decode($this->getAttribute('custom_data'), true);
        }

        if ($this->getAttribute('global_process_id')) {
            $this->globalProcessId = $this->getAttribute('global_process_id');
        }

        // invoice payment data
        if ($this->getAttribute('payment_data')) {
            $paymentData = QUI\Security\Encryption::decrypt($this->getAttribute('payment_data'));
            $paymentData = \json_decode($paymentData, true);

            if (\is_array($paymentData)) {
                $this->paymentData = $paymentData;
            }
        }

        // shipping
        if ($this->getAttribute('shipping_id')) {
            $shippingData = $this->getAttribute('shipping_data');
            $shippingData = \json_decode($shippingData, true);

            if (!\class_exists('QUI\ERP\Shipping\Types\ShippingUnique')) {
                $this->Shipping = new QUI\ERP\Shipping\Types\ShippingUnique($shippingData);
            }
        }


        // consider contact person in address
        if (!empty($this->getAttribute('invoice_address')) &&
            !empty($this->getAttribute('contact_person'))
        ) {
            $invoiceAddress = $this->getAttribute('invoice_address');
            $invoiceAddress = \json_decode($invoiceAddress, true);

            $invoiceAddress['contactPerson'] = $this->getAttribute('contact_person');
            $this->setAttribute('invoice_address', \json_encode($invoiceAddress));
        }
    }

    /**
     * Return the invoice id
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->prefix.$this->id;
    }

    /**
     * Return the hash
     * (Vorgangsnummer)
     *
     * @return string
     */
    public function getHash(): string
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
     * Returns only the integer id
     *
     * @return int
     */
    public function getCleanId(): int
    {
        return (int)\str_replace($this->prefix, '', $this->getId());
    }

    /**
     * Return the invoice view
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
     * Return the unique article list
     *
     * @return ArticleListUnique
     *
     * @throws QUI\ERP\Exception
     */
    public function getArticles(): ArticleListUnique
    {
        $articles = $this->getAttribute('articles');

        if (\is_string($articles)) {
            $articles = \json_decode($articles, true);
        }

        try {
            $articles['calculations']['currencyData'] = $this->getCurrency()->toArray();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        $List = new ArticleListUnique($articles, $this->getCustomer());
        $List->setLocale($this->getCustomer()->getLocale());


        // accounting currency, if exists
        $accountingCurrencyData = $this->getCustomDataEntry('accountingCurrencyData');

        try {
            if ($accountingCurrencyData) {
                $List->setExchangeCurrency(
                    new QUI\ERP\Currency\Currency(
                        $accountingCurrencyData['accountingCurrency']
                    )
                );

                $List->setExchangeRate($accountingCurrencyData['rate']);
            }
        } catch (QUI\Exception $Exception) {
        }

        return $List;
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
        $currency = $this->getAttribute('currency_data');

        if (!$currency) {
            return QUI\ERP\Defaults::getCurrency();
        }

        if (\is_string($currency)) {
            $currency = \json_decode($currency, true);
        }

        if (!$currency || !isset($currency['code'])) {
            return QUI\ERP\Defaults::getCurrency();
        }

        return QUI\ERP\Currency\Handler::getCurrency($currency['code']);
    }

    /**
     * @return QUI\ERP\User
     *
     * @throws QUI\ERP\Exception
     */
    public function getCustomer(): QUI\ERP\User
    {
        $invoiceAddress = $this->getAttribute('invoice_address');
        $customerData   = $this->getAttribute('customer_data');

        if (\is_string($customerData)) {
            $customerData = \json_decode($customerData, true);
        }

        // address
        if (\is_string($invoiceAddress)) {
            $invoiceAddress = \json_decode($invoiceAddress, true);
        }

        $userData = $customerData;

        if (!isset($userData['isCompany'])) {
            $userData['isCompany'] = false;

            if (isset($invoiceAddress['company'])) {
                $userData['isCompany'] = true;
            }
        }

        $userData['address'] = $invoiceAddress;

        if (!isset($userData['id'])) {
            $userData['id'] = $this->getAttribute('customer_id');
        }

        return new QUI\ERP\User($userData);
    }

    /**
     * @return QUI\ERP\User
     *
     * @throws QUI\ERP\Exception
     */
    public function getEditor(): QUI\ERP\User
    {
        return new QUI\ERP\User([
            'id'        => $this->getAttribute('editor_id'),
            'country'   => '',
            'username'  => '',
            'firstname' => '',
            'lastname'  => $this->getAttribute('editor_name'),
            'lang'      => '',
            'isCompany' => '',
            'isNetto'   => ''
        ]);
    }

    /**
     * Return the payment paid status information
     * - How many has already been paid
     * - How many must be paid
     *
     * @return array
     *
     * @throws
     */
    public function getPaidStatusInformation(): array
    {
        $oldStatus = $this->getAttribute('paid_status');

        QUI\ERP\Accounting\Calc::calculatePayments($this);

        // the status is another as calced, to we must update the invoice data
        if ($this->getAttribute('paid_status') !== $oldStatus) {
            $this->calculatePayments();
        }

        if ($this->getInvoiceType() === Handler::TYPE_INVOICE_STORNO) {
            $this->setAttribute('paid_status', QUI\ERP\Constants::PAYMENT_STATUS_CANCELED);
        }

        return [
            'paidData' => $this->getAttribute('paid_data'),
            'paidDate' => $this->getAttribute('paid_date'),
            'paid'     => $this->getAttribute('paid'),
            'toPay'    => $this->getAttribute('toPay')
        ];
    }

    /**
     * Return an entry in the payment data (decrypted)
     *
     * @param string $key
     * @return mixed|null
     */
    public function getPaymentDataEntry(string $key)
    {
        if (\array_key_exists($key, $this->paymentData)) {
            return $this->paymentData[$key];
        }

        return null;
    }

    /**
     * ALIAS for $this->getPaymentDataEntry to be consistent with InvoiceTemporary.
     *
     * @param string $key
     * @return mixed|null
     */
    public function getPaymentData(string $key)
    {
        return $this->getPaymentDataEntry($key);
    }

    /**
     * can a refund be made?
     *
     * @return bool
     */
    public function hasRefund(): bool
    {
        $transactions = InvoiceUtils::getTransactionsByInvoice($this);

        /* @var $Transaction Transaction */
        foreach ($transactions as $Transaction) {
            /* @var $Payment QUI\ERP\Accounting\Payments\Api\AbstractPayment */
            $Payment = $Transaction->getPayment();

            if ($Payment->refundSupport()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isPaid(): bool
    {
        if ($this->getAttribute('toPay') === false) {
            try {
                QUI\ERP\Accounting\Calc::calculatePayments($this);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        return $this->getAttribute('toPay') <= 0;
    }

    /**
     * Return the presentational payment method
     *
     * @return Payment
     */
    public function getPayment(): Payment
    {
        $data = $this->getAttribute('payment_method_data');

        if (!$data) {
            QUI\System\Log::addCritical(
                'Error with invoice '.$this->getId().'. No payment Data available'
            );

            return new Payment([]);
        }

        if (\is_string($data)) {
            $data = \json_decode($data, true);
        }

        return new Payment($data);
    }

    /**
     * Return the Shipping, if a shipping is set
     *
     * @return int|QUI\ERP\Shipping\Types\ShippingUnique|null
     */
    public function getShipping()
    {
        return $this->Shipping;
    }

    /**
     * Return the invoice type
     *
     * - Handler::TYPE_INVOICE
     * - Handler::TYPE_INVOICE_TEMPORARY
     * - Handler::TYPE_INVOICE_CREDIT_NOTE
     * - Handler::TYPE_INVOICE_CANCEL
     *
     * @return int
     */
    public function getInvoiceType(): int
    {
        return (int)$this->type;
    }

    /**
     * Cancel the invoice
     * (Reversal, Storno, Cancel)
     *
     * @param string $reason
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @return int - ID of the new
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public function reversal(string $reason, $PermissionUser = null): int
    {
        // is cancelation / reversal possible?
        if (!Settings::getInstance()->get('invoice', 'storno')) {
            // @todo implement credit note
            throw new Exception([
                'quiqqer/invoice',
                'exception.canceled.invoice.is.not.allowed'
            ]);
        }

        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        Permission::checkPermission(
            'quiqqer.invoice.reversal',
            $PermissionUser
        );

        if (empty($reason)) {
            throw new Exception([
                'quiqqer/invoice',
                'exception.missing.reason.in.reversal'
            ]);
        }

        // if invoice is parted paid, it could not be canceled
        $this->getPaidStatusInformation();

        if ($this->getAttribute('paid_status') == QUI\ERP\Constants::PAYMENT_STATUS_PART) {
            throw new Exception([
                'quiqqer/invoice',
                'exception.parted.invoice.cant.be.canceled'
            ]);
        }

        if ($this->getAttribute('paid_status') == QUI\ERP\Constants::PAYMENT_STATUS_CANCELED) {
            throw new Exception([
                'quiqqer/invoice',
                'exception.canceled.invoice.cant.be.canceled'
            ]);
        }


        $User = QUI::getUserBySession();

        QUI::getEvents()->fireEvent('quiqqerInvoiceReversal', [$this]);

        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/invoice',
                'history.message.reversal',
                [
                    'username' => $User->getName(),
                    'uid'      => $User->getId()
                ]
            )
        );

        $CreditNote = $this->createCreditNote(
            QUI::getUsers()->getSystemUser(),
            $this->getGlobalProcessId()
        );

        $CreditNote->setInvoiceType(Handler::TYPE_INVOICE_REVERSAL);

        // Cancellation invoice extra text
        $localeCode = QUI::getLocale()->getLocalesByLang(
            QUI::getLocale()->getCurrent()
        );

        $Formatter = new \IntlDateFormatter(
            $localeCode[0],
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::NONE
        );

        $currentDate = $this->getAttribute('date');

        if (!$currentDate) {
            $currentDate = \time();
        } else {
            $currentDate = \strtotime($currentDate);
        }

        $message = $this->getCustomer()->getLocale()->get(
            'quiqqer/invoice',
            'message.invoice.cancellationInvoice.additionalInvoiceText',
            [
                'id'   => $this->getId(),
                'date' => $Formatter->format($currentDate)
            ]
        );

        if (empty($message)) {
            $message = '';
        }

        // saving copy
        $CreditNote->setAttribute('additional_invoice_text', $message);

        $CreditNote->save(QUI::getUsers()->getSystemUser());

        try {
            Permission::checkPermission('quiqqer.invoice.canEditCancelInvoice', $PermissionUser);
        } catch (QUI\Exception $Exception) {
            $CreditNote->post(QUI::getUsers()->getSystemUser());
        }

        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/invoice',
                'history.message.reversal.created',
                [
                    'username'     => $User->getName(),
                    'uid'          => $User->getId(),
                    'creditNoteId' => $CreditNote->getId()
                ]
            )
        );

        // set the invoice status
        $this->type = Handler::TYPE_INVOICE_CANCEL;

        $CreditNote = $CreditNote->post(QUI::getUsers()->getSystemUser());

        $this->data['canceledId'] = $CreditNote->getCleanId();

        QUI::getDataBase()->update(
            Handler::getInstance()->invoiceTable(),
            [
                'type'        => $this->type,
                'data'        => \json_encode($this->data),
                'paid_status' => QUI\ERP\Constants::PAYMENT_STATUS_CANCELED
            ],
            ['id' => $this->getCleanId()]
        );

        $this->addComment($reason, QUI::getUsers()->getSystemUser());

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceReversalEnd',
            [$this]
        );

        return $CreditNote->getCleanId();
    }

    /**
     * Alias for reversal
     *
     * @param string $reason
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @return int - ID of the new
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public function cancellation(string $reason, $PermissionUser = null): int
    {
        return $this->reversal($reason, $PermissionUser);
    }

    /**
     * Alias for reversal
     *
     * @param string $reason
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @return int - ID of the new
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public function storno(string $reason, $PermissionUser = null): int
    {
        return $this->reversal($reason, $PermissionUser);
    }

    /**
     * Copy the invoice to a temporary invoice
     *
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @param false|string $globalProcessId
     * @return InvoiceTemporary
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public function copy($PermissionUser = null, $globalProcessId = false): InvoiceTemporary
    {
        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        Permission::checkPermission(
            'quiqqer.invoice.copy',
            $PermissionUser
        );


        $User = QUI::getUserBySession();

        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/invoice',
                'history.message.copy',
                [
                    'username' => $User->getName(),
                    'uid'      => $User->getId()
                ]
            )
        );

        QUI::getEvents()->fireEvent('quiqqerInvoiceCopyBegin', [$this]);

        $Handler = Handler::getInstance();
        $Factory = Factory::getInstance();
        $New     = $Factory->createInvoice($User);

        $currentData = QUI::getDataBase()->fetch([
            'from'  => $Handler->invoiceTable(),
            'where' => [
                'id' => $this->getCleanId()
            ],
            'limit' => 1
        ]);

        $currentData = $currentData[0];

        QUI::getEvents()->fireEvent('quiqqerInvoiceCopy', [$this]);

        if (empty($globalProcessId)) {
            $globalProcessId = $this->getHash();
        }


        // Invoice Address
        $invoiceAddressId = '';
        $invoiceAddress   = '';

        if ($this->getAttribute('invoice_address')) {
            try {
                $address = \json_decode($this->getAttribute('invoice_address'), true);
                $Address = new QUI\ERP\Address($address);

                $invoiceAddressId = $Address->getId();
                $invoiceAddress   = $Address->toJSON();
            } catch (\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }
        }

        QUI::getDataBase()->update(
            $Handler->temporaryInvoiceTable(),
            [
                'global_process_id'       => $globalProcessId,
                'type'                    => Handler::TYPE_INVOICE_TEMPORARY,
                'customer_id'             => $currentData['customer_id'],
                'contact_person'          => $currentData['contact_person'],
                'invoice_address_id'      => $invoiceAddressId,
                'invoice_address'         => $invoiceAddress,
                'delivery_address'        => $currentData['delivery_address'],
                'order_id'                => $currentData['order_id'],
                'project_name'            => $currentData['project_name'],
                'payment_method'          => $currentData['payment_method'],
                'payment_data'            => '',
                'payment_time'            => $currentData['payment_time'],
                'time_for_payment'        => (int)Settings::getInstance()->get('invoice', 'time_for_payment'),
                'paid_status'             => QUI\ERP\Constants::PAYMENT_STATUS_OPEN,
                'paid_date'               => null,
                'paid_data'               => null,
                'date'                    => date('Y-m-d H:i:s'),
                'data'                    => $currentData['data'],
                'additional_invoice_text' => $currentData['additional_invoice_text'],
                'articles'                => $currentData['articles'],
                'history'                 => '',
                'comments'                => '',
                'customer_data'           => $currentData['customer_data'],
                'isbrutto'                => $currentData['isbrutto'],
                'currency_data'           => $currentData['currency_data'],
                'currency'                => $currentData['currency'],
                'nettosum'                => $currentData['nettosum'],
                'nettosubsum'             => $currentData['nettosubsum'],
                'subsum'                  => $currentData['subsum'],
                'sum'                     => $currentData['sum'],
                'vat_array'               => $currentData['vat_array'],
                'processing_status'       => null
            ],
            ['id' => $New->getCleanId()]
        );

        $NewTemporaryInvoice = $Handler->getTemporaryInvoice($New->getId());

        QUI::getEvents()->fireEvent('quiqqerInvoiceCopyEnd', [$this, $NewTemporaryInvoice]);

        return $NewTemporaryInvoice;
    }

    /**
     * Create a credit note, set the invoice to credit note
     * - Gutschrift
     *
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @param bool|string $globalProcessId
     * @return InvoiceTemporary
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public function createCreditNote($PermissionUser = null, $globalProcessId = false): InvoiceTemporary
    {
        // a credit node cant create a credit note
        if ($this->getInvoiceType() === Handler::TYPE_INVOICE_CREDIT_NOTE) {
            throw new Exception([
                'quiqqer/invoice',
                'exception.credit.note.cant.create.credit.note'
            ]);
        }

        Permission::checkPermission(
            'quiqqer.invoice.createCreditNote',
            $PermissionUser
        );

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceCreateCreditNote',
            [$this]
        );

        $Copy     = $this->copy(QUI::getUsers()->getSystemUser(), $globalProcessId);
        $articles = $Copy->getArticles()->getArticles();

        // change all prices
        $ArticleList = $Copy->getArticles();
        $ArticleList->clear();
        $ArticleList->setCurrency($this->getCurrency());

        foreach ($articles as $Article) {
            $article              = $Article->toArray();
            $article['unitPrice'] = $article['unitPrice'] * -1;

            $Clone = new QUI\ERP\Accounting\Article($article);
            $ArticleList->addArticle($Clone);
        }

        $PriceFactors = $ArticleList->getPriceFactors();
        $Currency     = $this->getCurrency();

        /* @var $PriceFactor QUI\ERP\Accounting\PriceFactors\Factor */
        foreach ($PriceFactors as $PriceFactor) {
            $PriceFactor->setNettoSum($PriceFactor->getNettoSum() * -1);
            $PriceFactor->setSum($PriceFactor->getSum() * -1);
            $PriceFactor->setValue($PriceFactor->getValue() * -1);

            $PriceFactor->setNettoSumFormatted($Currency->format($PriceFactor->getNettoSum()));
            $PriceFactor->setSumFormatted($Currency->format($PriceFactor->getSum()));
            $PriceFactor->setValueText($Currency->format($PriceFactor->getValue()));
        }

        $Copy->addHistory(
            QUI::getLocale()->get('quiqqer/invoice', 'message.create.credit.from', [
                'invoiceParentId' => $this->getId(),
                'invoiceId'       => $this->getId()
            ])
        );

        // credit note extra text
        $localeCode = QUI::getLocale()->getLocalesByLang(
            QUI::getLocale()->getCurrent()
        );

        $Formatter = new \IntlDateFormatter(
            $localeCode[0],
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::NONE
        );

        $currentDate = $this->getAttribute('date');

        if (!$currentDate) {
            $currentDate = \time();
        } else {
            $currentDate = \strtotime($currentDate);
        }

        $message = $this->getCustomer()->getLocale()->get(
            'quiqqer/invoice',
            'message.invoice.creditNote.additionalInvoiceText',
            [
                'id'   => $this->getId(),
                'date' => $Formatter->format($currentDate)
            ]
        );

        $additionalText = $Copy->getAttribute('additional_invoice_text');

        if (!empty($additionalText)) {
            $additionalText .= '<br />';
        }

        $additionalText .= $message;

        // saving copy
        $Copy->setData('originalId', $this->getCleanId());
        $Copy->setData('originalIdPrefixed', $this->getId());

        $Copy->setAttribute('date', \date('Y-m-d H:i:s'));
        $Copy->setAttribute('additional_invoice_text', $additionalText);
        $Copy->setAttribute('currency_data', $this->getAttribute('currency_data'));
        $Copy->setInvoiceType(Handler::TYPE_INVOICE_CREDIT_NOTE);

        if ($this->getAttribute('invoice_address')) {
            try {
                $address = \json_decode($this->getAttribute('invoice_address'), true);
                $Address = new QUI\ERP\Address($address);

                $invoiceAddressId = $Address->getId();
                $invoiceAddress   = $Address->toJSON();

                $Copy->setAttribute('invoice_address_id', $invoiceAddressId);
                $Copy->setAttribute('invoice_address', $invoiceAddress);
            } catch (\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }
        }

        $Copy->save(QUI::getUsers()->getSystemUser());

        $this->addHistory(
            QUI::getLocale()->get('quiqqer/invoice', 'message.create.credit', [
                'invoiceParentId' => $this->getId(),
                'invoiceId'       => $Copy->getId()
            ])
        );

        $CreditNote = Handler::getInstance()->getTemporaryInvoice($Copy->getId());

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceCreateCreditNoteEnd',
            [$this, $CreditNote]
        );

        return $CreditNote;
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

        $this->calculatePayments();

        if ($this->getInvoiceType() == Handler::TYPE_INVOICE_REVERSAL
            || $this->getInvoiceType() == Handler::TYPE_INVOICE_CANCEL
            || $this->getInvoiceType() == Handler::TYPE_INVOICE_CREDIT_NOTE
        ) {
            return;
        }

        if ($currentPaidStatus === $this->getAttribute('paid_status')
            && ($this->getAttribute('paid_status') == QUI\ERP\Constants::PAYMENT_STATUS_PAID ||
                $this->getAttribute('paid_status') == QUI\ERP\Constants::PAYMENT_STATUS_CANCELED)
        ) {
            return;
        }

        QUI\ERP\Debug::getInstance()->log('Order:: add transaction start');

        $User     = QUI::getUserBySession();
        $paidData = $this->getAttribute('paid_data');
        $amount   = Price::validatePrice($Transaction->getAmount());
        $date     = $Transaction->getDate();

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceAddTransactionBegin',
            [$this, $amount, $Transaction, $date]
        );

        if (!$amount) {
            return;
        }

        if (!\is_array($paidData)) {
            $paidData = \json_decode($paidData, true);
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
            $date = \strtotime($date);

            if ($isValidTimeStamp($date) === false) {
                $date = \time();
            }
        }

        $this->setAttribute('paid_date', $date);


        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/invoice',
                'history.message.addPayment',
                [
                    'username' => $User->getName(),
                    'uid'      => $User->getId(),
                    'txid'     => $Transaction->getTxId()
                ]
            )
        );

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceAddTransaction',
            [$this, $amount, $Transaction, $date]
        );

        $this->calculatePayments();

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceAddTransactionEnd',
            [$this, $amount, $Transaction, $date]
        );
    }

    /**
     * Calculates the payments and set the new part payments
     *
     * @throws
     */
    public function calculatePayments()
    {
        $User = QUI::getUserBySession();

        // old status
        $oldPaidStatus = $this->getAttribute('paid_status');

        switch ($oldPaidStatus) {
            /**
             * Do not change paid_status if invoice is paid via direct debit.
             *
             * In this case the paid_status has be explicitly set via $this->setPaymentStatus()
             */
            case QUI\ERP\Constants::PAYMENT_STATUS_DEBIT:
                return;
        }

        QUI\ERP\Accounting\Calc::calculatePayments($this);

        switch ($this->getAttribute('paid_status')) {
            case QUI\ERP\Constants::PAYMENT_STATUS_OPEN:
            case QUI\ERP\Constants::PAYMENT_STATUS_PAID:
            case QUI\ERP\Constants::PAYMENT_STATUS_PART:
            case QUI\ERP\Constants::PAYMENT_STATUS_ERROR:
            case QUI\ERP\Constants::PAYMENT_STATUS_CANCELED:
                break;

            default:
                $this->setAttribute('paid_status', QUI\ERP\Constants::PAYMENT_STATUS_ERROR);
        }

        QUI::getDataBase()->update(
            Handler::getInstance()->invoiceTable(),
            [
                'paid_data'   => $this->getAttribute('paid_data'),
                'paid_date'   => $this->getAttribute('paid_date'),
                'paid_status' => $this->getAttribute('paid_status')
            ],
            ['id' => $this->getCleanId()]
        );

        // Payment Status has changed
        if ($oldPaidStatus != $this->getAttribute('paid_status')) {
            $this->addHistory(
                QUI::getLocale()->get(
                    'quiqqer/invoice',
                    'history.message.edit',
                    [
                        'username' => $User->getName(),
                        'uid'      => $User->getId()
                    ]
                )
            );

            QUI::getEvents()->fireEvent(
                'quiqqerInvoicePaymentStatusChanged',
                [$this, $this->getAttribute('paid_status'), $oldPaidStatus]
            );
        }
    }

    /**
     * Set the payment status of this invoice (attribute: paid_status)
     *
     * @param int $paymentStatus
     * @return void
     */
    public function setPaymentStatus(int $paymentStatus): void
    {
        $User = QUI::getUserBySession();

        // old status
        $oldPaymentStatus = (int)$this->getAttribute('paid_status');

        QUI\ERP\Accounting\Calc::calculatePayments($this);

        switch ($paymentStatus) {
            case QUI\ERP\Constants::PAYMENT_STATUS_OPEN:
            case QUI\ERP\Constants::PAYMENT_STATUS_PAID:
            case QUI\ERP\Constants::PAYMENT_STATUS_PART:
            case QUI\ERP\Constants::PAYMENT_STATUS_ERROR:
            case QUI\ERP\Constants::PAYMENT_STATUS_CANCELED:
            case QUI\ERP\Constants::PAYMENT_STATUS_DEBIT:
                break;

            default:
                $paymentStatus = QUI\ERP\Constants::PAYMENT_STATUS_ERROR;
        }

        if ($oldPaymentStatus === $paymentStatus) {
            return;
        }

        QUI::getDataBase()->update(
            Handler::getInstance()->invoiceTable(),
            [
                'paid_data'   => $this->getAttribute('paid_data'),
                'paid_date'   => $this->getAttribute('paid_date'),
                'paid_status' => $paymentStatus
            ],
            ['id' => $this->getCleanId()]
        );

        $this->setAttribute('paid_status', $paymentStatus);

        // Payment Status has changed
        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/invoice',
                'history.message.set_payment_status',
                [
                    'username'  => $User->getName(),
                    'uid'       => $User->getId(),
                    'oldStatus' => QUI::getLocale()->get('quiqqer/invoice', 'payment.status.'.$oldPaymentStatus),
                    'newStatus' => QUI::getLocale()->get('quiqqer/invoice', 'payment.status.'.$paymentStatus)
                ]
            )
        );

        QUI::getEvents()->fireEvent(
            'quiqqerInvoicePaymentStatusChanged',
            [$this, $paymentStatus, $oldPaymentStatus]
        );
    }

    /**
     * Send the invoice to a recipient
     *
     * @param string $recipient - The recipient email address
     * @param bool|string $template - pdf template
     *
     * @throws QUI\Exception
     */
    public function sendTo(string $recipient, $template = false)
    {
        $type       = $this->getInvoiceType();
        $outputType = 'Invoice';

        switch ($type) {
            case QUI\ERP\Constants::TYPE_INVOICE_CREDIT_NOTE:
                $outputType = 'CreditNote';
                break;

            case QUI\ERP\Constants::TYPE_INVOICE_CANCEL:
            case QUI\ERP\Constants::TYPE_INVOICE_STORNO:
                $outputType = 'Canceled';
                break;
        }

        ERPOutput::sendPdfViaMail(
            $this->getId(),
            $outputType,
            null,
            null,
            $template,
            $recipient
        );
    }

    //region Comments & History

    /**
     * Add a comment to the invoice
     *
     * @param string $comment
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     *
     * @throws QUI\Exception
     */
    public function addComment(string $comment, $PermissionUser = null)
    {
        if (empty($comment)) {
            return;
        }

        Permission::checkPermission(
            'quiqqer.invoice.addComment',
            $PermissionUser
        );

        $comment = \strip_tags(
            $comment,
            '<div><span><pre><p><br><hr>
            <ul><ol><li><dl><dt><dd><strong><em><b><i><u>
            <img><table><tbody><td><tfoot><th><thead><tr>'
        );

        $User     = QUI::getUserBySession();
        $comments = $this->getAttribute('comments');
        $Comments = QUI\ERP\Comments::unserialize($comments);

        $Comments->addComment($comment);
        $this->setAttribute('comments', $Comments->toJSON());

        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/invoice',
                'history.message.addComment',
                [
                    'username' => $User->getName(),
                    'uid'      => $User->getId()
                ]
            )
        );

        QUI::getDataBase()->update(
            Handler::getInstance()->invoiceTable(),
            ['comments' => $Comments->toJSON()],
            ['id' => $this->getCleanId()]
        );

        QUI::getEvents()->fireEvent(
            'onQuiqqerInvoiceAddComment',
            [$this, $comment]
        );
    }

    /**
     * Return the invoice comments
     *
     * @return QUI\ERP\Comments
     */
    public function getComments(): QUI\ERP\Comments
    {
        return QUI\ERP\Comments::unserialize(
            $this->getAttribute('comments')
        );
    }

    /**
     * Add a comment to the history
     *
     * @param string $comment
     *
     * @throws QUI\Exception
     */
    public function addHistory(string $comment)
    {
        $history = $this->getAttribute('history');
        $History = QUI\ERP\Comments::unserialize($history);

        $History->addComment($comment);

        $this->setAttribute('history', $History->toJSON());

        QUI::getDataBase()->update(
            Handler::getInstance()->invoiceTable(),
            ['history' => $History->toJSON()],
            ['id' => $this->getCleanId()]
        );

        QUI::getEvents()->fireEvent('onQuiqqerInvoiceAddHistory', [$this, $comment]);
    }

    /**
     * Return the invoice history
     *
     * @return QUI\ERP\Comments
     */
    public function getHistory(): QUI\ERP\Comments
    {
        $history = $this->getAttribute('history');

        return QUI\ERP\Comments::unserialize($history);
    }

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
            Handler::getInstance()->invoiceTable(),
            ['custom_data' => \json_encode($this->customData)],
            ['id' => $this->getCleanId()]
        );

        QUI::getEvents()->fireEvent(
            'onQuiqqerInvoiceAddCustomData',
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

    /**
     * @return array
     */
    public function toArray(): array
    {
        $attributes = $this->getAttributes();

        $attributes['globalProcessId'] = $this->getGlobalProcessId();

        return $attributes;
    }

    //region processing status

    /**
     * Set the processing status
     *
     * @param integer $statusId
     * @throws QUI\Exception
     */
    public function setProcessingStatus(int $statusId)
    {
        try {
            $Status = ProcessingStatus\Handler::getInstance()->getProcessingStatus($statusId);
        } catch (ProcessingStatus\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());

            return;
        }

        $CurrentStatus = $this->getProcessingStatus();

        try {
            QUI::getDataBase()->update(
                Handler::getInstance()->invoiceTable(),
                ['processing_status' => $Status->getId()],
                ['id' => $this->getCleanId()]
            );

            $this->setAttribute('processing_status', $Status->getId());
        } catch (QUI\DataBase\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());
        }


        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceProcessingStatusSet',
            [$this, $Status]
        );

        if ($CurrentStatus && $CurrentStatus->getId() !== $Status->getId()
            || !$CurrentStatus) {
            QUI::getEvents()->fireEvent(
                'quiqqerInvoiceProcessingStatusChange',
                [$this, $Status]
            );
        }
    }

    /**
     * Return the current processing status
     *
     * @return ProcessingStatus\Status|null
     */
    public function getProcessingStatus(): ?ProcessingStatus\Status
    {
        try {
            $Status = ProcessingStatus\Handler::getInstance()->getProcessingStatus(
                $this->getAttribute('processing_status')
            );
        } catch (ProcessingStatus\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());

            return null;
        }

        return $Status;
    }

    //endregion

    //region Data

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

    //endregion
}
