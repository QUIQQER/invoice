<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Invoice
 */

namespace QUI\ERP\Accounting\Invoice;

use IntlDateFormatter;
use QUI;
use QUI\ERP\Accounting\ArticleListUnique;
use QUI\ERP\Accounting\Calculations;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;
use QUI\ERP\Address as ErpAddress;
use QUI\ERP\Exception;
use QUI\ERP\Money\Price;
use QUI\ERP\Output\Output as ERPOutput;
use QUI\ERP\Shipping\Api\ShippingInterface;
use QUI\ExceptionStack;
use QUI\Interfaces\Users\User;
use QUI\ERP\ErpEntityInterface;
use QUI\ERP\ErpTransactionsInterface;
use QUI\ERP\ErpCopyInterface;
use QUI\Permissions\Permission;

use function array_key_exists;
use function class_exists;
use function date;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_encode;
use function strip_tags;
use function strtotime;
use function time;

/**
 * Class Invoice
 * - Invoice class
 * - This class present a posted invoice
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class Invoice extends QUI\QDOM implements ErpEntityInterface, ErpTransactionsInterface, ErpCopyInterface
{
    use QUI\ERP\ErpEntityCustomerFiles;
    use QUI\ERP\ErpEntityData;

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
    protected int $type;

    /**
     * @var string
     */
    protected mixed $prefix;

    /**
     * @var int
     */
    protected int $id;

    /**
     * @var string
     */
    protected string $id_with_prefix = '';

    /**
     * @var string
     */
    protected mixed $globalProcessId;

    /**
     * @var array
     */
    protected array $data = [];

    /**
     * variable data for developers
     *
     * @var array
     */
    protected array $customData = [];

    /**
     * @var array
     */
    protected array $paymentData = [];

    /**
     * @var null|ShippingInterface
     */
    protected ShippingInterface|null $Shipping = null;

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
        $invoiceData = $Handler->getInvoiceData($id);
        $this->setAttributes($invoiceData);

        $this->prefix = $this->getAttribute('id_prefix');

        if ($this->prefix === false) {
            $this->prefix = Settings::getInstance()->getInvoicePrefix();
        }

        $this->id = (int)$invoiceData['id'];
        $this->type = QUI\ERP\Constants::TYPE_INVOICE;

        if (!empty($invoiceData['id_with_prefix'])) {
            $this->id_with_prefix = $invoiceData['id_with_prefix'];
        } else {
            $this->id_with_prefix = $this->prefix . $this->id;
        }

        switch ((int)$this->getAttribute('type')) {
            case QUI\ERP\Constants::TYPE_INVOICE:
            case QUI\ERP\Constants::TYPE_INVOICE_TEMPORARY:
            case QUI\ERP\Constants::TYPE_INVOICE_CREDIT_NOTE:
            case QUI\ERP\Constants::TYPE_INVOICE_REVERSAL:
            case QUI\ERP\Constants::TYPE_INVOICE_CANCEL:
                $this->type = (int)$this->getAttribute('type');
                break;
        }

        if ($this->getAttribute('data')) {
            $this->data = json_decode($this->getAttribute('data'), true) ?? [];
        }

        if ($this->getAttribute('custom_data')) {
            $this->customData = json_decode($this->getAttribute('custom_data'), true) ?? [];
        }

        // fallback customer files
        if (!empty($this->data['customer_files']) && empty($this->customData['customer_files'])) {
            $this->customData['customer_files'] = $this->data['customer_files'];
        }

        if ($this->getAttribute('global_process_id')) {
            $this->globalProcessId = $this->getAttribute('global_process_id');
        } else {
            $this->globalProcessId = $this->getUUID();
        }

        // invoice payment data
        if ($this->getAttribute('payment_data')) {
            $paymentData = QUI\Security\Encryption::decrypt($this->getAttribute('payment_data'));
            $paymentData = json_decode($paymentData, true);

            $this->paymentData = $paymentData ?? [];
        }

        // shipping
        if ($this->getAttribute('shipping_id')) {
            $shippingData = $this->getAttribute('shipping_data');
            $shippingData = json_decode($shippingData, true);

            if (class_exists('QUI\ERP\Shipping\Types\ShippingUnique')) {
                $this->Shipping = new QUI\ERP\Shipping\Types\ShippingUnique($shippingData);
            }
        }


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
    }

    /**
     * Return the invoice id
     * (Rechnungs-ID)
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     * @deprecated use getPrefixedNumber()
     */
    public function getPrefixedId(): string
    {
        return $this->getPrefixedNumber();
    }

    /**
     * Rechnungsnummer
     *
     * @return string
     */
    public function getPrefixedNumber(): string
    {
        return $this->id_with_prefix;
    }

    /**
     * alias for getUUID()
     * (Vorgangsnummer)
     *
     * @return string
     * @deprecated use getUUID()
     */
    public function getHash(): string
    {
        return $this->getUUID();
    }

    /**
     * Return the uuid of the invoice
     * (Vorgangsnummer)
     *
     * @return string
     */
    public function getUUID(): string
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
     * @deprecated use getId()
     */
    public function getCleanId(): int
    {
        return $this->getId();
    }

    /**
     * Return the invoice view
     *
     * @return InvoiceView
     *
     * @throws \QUI\ERP\Accounting\Invoice\Exception
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
     * @throws QUI\ERP\Exception|QUI\Exception
     */
    public function getArticles(): ArticleListUnique
    {
        $articles = $this->getAttribute('articles');

        if (is_string($articles)) {
            $articles = json_decode($articles, true);
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
        } catch (QUI\Exception) {
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

        return $Currency;
    }

    /**
     * @return QUI\ERP\User
     *
     * @throws QUI\ERP\Exception|QUI\Exception
     */
    public function getCustomer(): QUI\ERP\User
    {
        $invoiceAddress = $this->getAttribute('invoice_address');
        $customerData = $this->getAttribute('customer_data');

        if (is_string($customerData)) {
            $customerData = json_decode($customerData, true);
        }

        // address
        if (is_string($invoiceAddress)) {
            $invoiceAddress = json_decode($invoiceAddress, true);
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

        // Country fallback
        if (empty($userData['country'])) {
            if (!empty($invoiceAddress['country'])) {
                $userData['country'] = $invoiceAddress['country'];
            } else {
                $userData['country'] = QUI\ERP\Defaults::getCountry()->getCode();
            }
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
        $params = [
            'country' => '',
            'username' => '',
            'firstname' => '',
            'lastname' => $this->getAttribute('editor_name'),
            'lang' => '',
            'isCompany' => '',
            'isNetto' => ''
        ];

        if (is_numeric($this->getAttribute('editor_id'))) {
            $params['id'] = $this->getAttribute('editor_id');
        }

        if (is_string($this->getAttribute('editor_id'))) {
            $params['uuid'] = $this->getAttribute('editor_id');
        }

        return new QUI\ERP\User($params);
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

        // the status is another as canceled, to we must update the invoice data
        if ($this->getAttribute('paid_status') !== $oldStatus) {
            $this->calculatePayments();
        }

        if ($this->getInvoiceType() === QUI\ERP\Constants::TYPE_INVOICE_STORNO) {
            //$this->setAttribute('paid_status', QUI\ERP\Constants::PAYMENT_STATUS_CANCELED);
        }

        return [
            'paidData' => $this->getAttribute('paid_data'),
            'paidDate' => $this->getAttribute('paid_date'),
            'paid' => $this->getAttribute('paid'),
            'toPay' => $this->getAttribute('toPay')
        ];
    }

    /**
     * Return an entry in the payment data (decrypted)
     *
     * @param string $key
     * @return mixed|null
     */
    public function getPaymentDataEntry(string $key): mixed
    {
        if (array_key_exists($key, $this->paymentData)) {
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
    public function getPaymentData(string $key): mixed
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

            if ($Payment && $Payment->refundSupport()) {
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
                'Error with invoice ' . $this->getUUID() . '. No payment Data available'
            );

            return new Payment([]);
        }

        if (is_string($data)) {
            $data = json_decode($data, true);
        }

        return new Payment($data);
    }

    /**
     * Return the Shipping, if a shipping is set
     *
     * @return int|QUI\ERP\Shipping\Types\ShippingUnique|null
     */
    public function getShipping(): int|QUI\ERP\Shipping\Types\ShippingUnique|null
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
        return $this->type;
    }

    /**
     * Cancel the invoice
     * (Reversal, Storno, Cancel)
     *
     * @param string $reason
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @return ?QUI\ERP\ErpEntityInterface
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public function reversal(
        string $reason = '',
        QUI\Interfaces\Users\User $PermissionUser = null
    ): ?QUI\ERP\ErpEntityInterface {
        // is canceled / reversal possible?
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
                    'uid' => $User->getUUID()
                ]
            )
        );

        $Reversal = $this->createReversal(
            QUI::getUsers()->getSystemUser(),
            $this->getGlobalProcessId()
        );

        //$CreditNote->setInvoiceType(Handler::TYPE_INVOICE_REVERSAL);

        // Cancellation invoice extra text
        $localeCode = QUI::getLocale()->getLocalesByLang(
            QUI::getLocale()->getCurrent()
        );

        $Formatter = new IntlDateFormatter(
            $localeCode[0],
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE
        );

        $currentDate = $this->getAttribute('date');

        if (!$currentDate) {
            $currentDate = time();
        } else {
            $currentDate = strtotime($currentDate);
        }

        $message = $this->getCustomer()->getLocale()->get(
            'quiqqer/invoice',
            'message.invoice.cancellationInvoice.additionalInvoiceText',
            [
                'id' => $this->getPrefixedNumber(),
                'date' => $Formatter->format($currentDate)
            ]
        );

        if (empty($message)) {
            $message = '';
        }

        // saving copy
        $Reversal->setAttribute('additional_invoice_text', $message);

        $Reversal->save(QUI::getUsers()->getSystemUser());

        try {
            Permission::checkPermission('quiqqer.invoice.canEditCancelInvoice', $PermissionUser);
        } catch (QUI\Exception) {
            $Reversal->post(QUI::getUsers()->getSystemUser());
        }

        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/invoice',
                'history.message.reversal.created',
                [
                    'username' => $User->getName(),
                    'uid' => $User->getUUID(),
                    'creditNoteId' => $Reversal->getUUID()
                ]
            )
        );

        // set the invoice status
        $this->type = QUI\ERP\Constants::TYPE_INVOICE_CANCEL;

        $Reversal = $Reversal->post(QUI::getUsers()->getSystemUser());

        $this->data['canceledId'] = $Reversal->getUUID();

        QUI::getDataBase()->update(
            Handler::getInstance()->invoiceTable(),
            [
                'type' => $this->type,
                'data' => json_encode($this->data),
                'paid_status' => QUI\ERP\Constants::PAYMENT_STATUS_CANCELED
            ],
            ['hash' => $this->getUUID()]
        );

        $this->addComment($reason, QUI::getUsers()->getSystemUser());

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceReversalEnd',
            [$this, $Reversal]
        );

        return $Reversal;
    }

    /**
     * Alias for reversal
     *
     * @param string $reason
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @return int|string - ID of the new
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public function cancellation(string $reason, QUI\Interfaces\Users\User $PermissionUser = null): int|string
    {
        return $this->reversal($reason, $PermissionUser)->getUUID();
    }

    /**
     * Alias for reversal
     *
     * @param string $reason
     * @param null|User $PermissionUser
     * @return int|string - ID of the new
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public function storno(string $reason, QUI\Interfaces\Users\User $PermissionUser = null): int|string
    {
        return $this->reversal($reason, $PermissionUser)->getUUID();
    }

    /**
     * Copy the invoice to a temporary invoice
     *
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @param bool|string $globalProcessId
     * @return InvoiceTemporary
     *
     * @throws Exception
     * @throws QUI\Exception
     * @throws QUI\Permissions\Exception
     */
    public function copy(
        QUI\Interfaces\Users\User $PermissionUser = null,
        bool|string $globalProcessId = false
    ): InvoiceTemporary {
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
                    'uid' => $User->getId()
                ]
            )
        );

        QUI::getEvents()->fireEvent('quiqqerInvoiceCopyBegin', [$this]);

        $Handler = Handler::getInstance();
        $Factory = Factory::getInstance();
        $New = $Factory->createInvoice($User);

        $currentData = QUI::getDataBase()->fetch([
            'from' => $Handler->invoiceTable(),
            'where' => [
                'hash' => $this->getUUID()
            ],
            'limit' => 1
        ]);

        $currentData = $currentData[0];

        QUI::getEvents()->fireEvent('quiqqerInvoiceCopy', [$this]);

        if (empty($globalProcessId)) {
            $globalProcessId = QUI\Utils\Uuid::get();
        }

        // Invoice Address
        $invoiceAddressId = '';
        $invoiceAddress = '';

        if ($this->getAttribute('invoice_address')) {
            try {
                $address = json_decode($this->getAttribute('invoice_address'), true);
                $Address = new QUI\ERP\Address($address);

                $invoiceAddressId = $Address->getUUID();
                $invoiceAddress = $Address->toJSON();
            } catch (\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }
        }

        QUI::getDataBase()->update(
            $Handler->temporaryInvoiceTable(),
            [
                'global_process_id' => $globalProcessId,
                'type' => QUI\ERP\Constants::TYPE_INVOICE_TEMPORARY,
                'customer_id' => $currentData['customer_id'],
                'contact_person' => $currentData['contact_person'],
                'invoice_address_id' => $invoiceAddressId,
                'invoice_address' => $invoiceAddress,
                'delivery_address' => $currentData['delivery_address'],
                'order_id' => $currentData['order_id'] ?: null,
                'project_name' => $currentData['project_name'],
                'payment_method' => $currentData['payment_method'],
                'payment_data' => '',
                'payment_time' => $currentData['payment_time'],
                'time_for_payment' => (int)Settings::getInstance()->get('invoice', 'time_for_payment'),
                'paid_status' => QUI\ERP\Constants::PAYMENT_STATUS_OPEN,
                'paid_date' => null,
                'paid_data' => null,
                'date' => date('Y-m-d H:i:s'),
                'data' => $currentData['data'],
                'additional_invoice_text' => $currentData['additional_invoice_text'],
                'articles' => $currentData['articles'],
                'history' => '',
                'comments' => '',
                'customer_data' => $currentData['customer_data'],
                'isbrutto' => $currentData['isbrutto'],
                'currency_data' => $currentData['currency_data'],
                'currency' => $currentData['currency'],
                'nettosum' => $currentData['nettosum'],
                'nettosubsum' => $currentData['nettosubsum'],
                'subsum' => $currentData['subsum'],
                'sum' => $currentData['sum'],
                'vat_array' => $currentData['vat_array'],
                'processing_status' => null
            ],
            ['id' => $New->getCleanId()]
        );

        $NewTemporaryInvoice = $Handler->getTemporaryInvoice($New->getUUID());

        QUI::getEvents()->fireEvent('quiqqerInvoiceCopyEnd', [
            $this,
            $NewTemporaryInvoice
        ]);

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
    public function createCreditNote(
        QUI\Interfaces\Users\User $PermissionUser = null
    ): InvoiceTemporary {
        // a credit node cant create a credit note
        if ($this->getInvoiceType() === QUI\ERP\Constants::TYPE_INVOICE_CREDIT_NOTE) {
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

        $Copy = $this->copy(QUI::getUsers()->getSystemUser(), $this->getGlobalProcessId());
        $articles = $Copy->getArticles()->getArticles();

        // change all prices
        $ArticleList = $Copy->getArticles();
        $ArticleList->clear();
        $ArticleList->setCurrency($this->getCurrency());

        $priceKeyList = [
            'price',
            'basisPrice',
            'nettoPriceNotRounded',
            'sum',
            'nettoSum',
            'nettoSubSum',
            'nettoPrice',
            'nettoBasisPrice',
            'unitPrice',
        ];

        foreach ($articles as $Article) {
            $article = $Article->toArray();

            foreach ($priceKeyList as $priceKey) {
                if (isset($article[$priceKey])) {
                    $article[$priceKey] = $article[$priceKey] * -1;
                }
            }

            if (isset($article['calculated'])) {
                foreach ($priceKeyList as $priceKey) {
                    if (isset($article['calculated'][$priceKey])) {
                        $article['calculated'][$priceKey] = $article['calculated'][$priceKey] * -1;
                    }
                }
            }

            if (isset($article['calculated']['vatArray'])) {
                $article['calculated']['vatArray']['sum'] = $article['calculated']['vatArray']['sum'] * -1;
            }

            $Clone = new QUI\ERP\Accounting\Article($article);
            $ArticleList->addArticle($Clone);
        }

        $PriceFactors = $ArticleList->getPriceFactors();
        $Currency = $this->getCurrency();

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
                'invoiceParentId' => $this->getUUID(),
                'invoiceId' => $this->getUUID()
            ])
        );

        // credit note extra text
        $localeCode = QUI::getLocale()->getLocalesByLang(
            QUI::getLocale()->getCurrent()
        );

        $Formatter = new IntlDateFormatter(
            $localeCode[0],
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE
        );

        $currentDate = $this->getAttribute('date');

        if (!$currentDate) {
            $currentDate = time();
        } else {
            $currentDate = strtotime($currentDate);
        }

        $message = $this->getCustomer()->getLocale()->get(
            'quiqqer/invoice',
            'message.invoice.creditNote.additionalInvoiceText',
            [
                'id' => $this->getUUID(),
                'date' => $Formatter->format($currentDate)
            ]
        );

        $additionalText = $Copy->getAttribute('additional_invoice_text');

        if (!empty($additionalText)) {
            $additionalText .= '<br />';
        }

        $additionalText .= $message;

        // saving copy
        $Copy->setData('originalId', $this->getId());
        $Copy->setData('originalIdPrefixed', $this->getPrefixedNumber());
        $Copy->setData('originalInvoice', $this->getReferenceData());

        $Copy->setAttribute('date', date('Y-m-d H:i:s'));
        $Copy->setAttribute('additional_invoice_text', $additionalText);
        $Copy->setAttribute('currency_data', $this->getAttribute('currency_data'));
        $Copy->setInvoiceType(QUI\ERP\Constants::TYPE_INVOICE_CREDIT_NOTE, QUI::getUsers()->getSystemUser());

        if ($this->getAttribute('invoice_address')) {
            try {
                $address = json_decode($this->getAttribute('invoice_address'), true);
                $Address = new QUI\ERP\Address($address);

                $invoiceAddressId = $Address->getUUID();
                $invoiceAddress = $Address->toJSON();

                $Copy->setAttribute('invoice_address_id', $invoiceAddressId);
                $Copy->setAttribute('invoice_address', $invoiceAddress);
            } catch (\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }
        }

        $Copy->save(QUI::getUsers()->getSystemUser());

        $this->addHistory(
            QUI::getLocale()->get('quiqqer/invoice', 'message.create.credit', [
                'invoiceParentId' => $this->getUUID(),
                'invoiceId' => $Copy->getUUID()
            ])
        );

        $CreditNote = Handler::getInstance()->getTemporaryInvoice($Copy->getUUID());

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceCreateCreditNoteEnd',
            [
                $this,
                $CreditNote
            ]
        );

        return $CreditNote;
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
    public function createReversal(
        QUI\Interfaces\Users\User $PermissionUser = null,
        bool|string $globalProcessId = false
    ): InvoiceTemporary {
        Permission::checkPermission('quiqqer.invoice.reversal', $PermissionUser);

        QUI::getEvents()->fireEvent('quiqqerInvoiceCreateReversal', [$this]);

        $Copy = $this->copy(QUI::getUsers()->getSystemUser(), $globalProcessId);
        $articles = $Copy->getArticles()->getArticles();

        // change all prices
        $ArticleList = $Copy->getArticles();
        $ArticleList->clear();
        $ArticleList->setCurrency($this->getCurrency());

        $priceKeyList = [
            'price',
            'basisPrice',
            'nettoPriceNotRounded',
            'sum',
            'nettoSum',
            'nettoSubSum',
            'nettoPrice',
            'nettoBasisPrice',
            'unitPrice',
        ];

        foreach ($articles as $Article) {
            $article = $Article->toArray();

            foreach ($priceKeyList as $priceKey) {
                if (isset($article[$priceKey])) {
                    $article[$priceKey] = $article[$priceKey] * -1;
                }
            }

            if (isset($article['calculated'])) {
                foreach ($priceKeyList as $priceKey) {
                    if (isset($article['calculated'][$priceKey])) {
                        $article['calculated'][$priceKey] = $article['calculated'][$priceKey] * -1;
                    }
                }
            }

            if (isset($article['calculated']['vatArray'])) {
                $article['calculated']['vatArray']['sum'] = $article['calculated']['vatArray']['sum'] * -1;
            }

            $Clone = new QUI\ERP\Accounting\Article($article);
            $ArticleList->addArticle($Clone);
        }

        $PriceFactors = $ArticleList->getPriceFactors();
        $Currency = $this->getCurrency();

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
            QUI::getLocale()->get('quiqqer/invoice', 'message.create.reversal.from', [
                'invoiceParentId' => $this->getUUID(),
                'invoiceId' => $this->getUUID()
            ])
        );

        // credit note extra text
        $localeCode = QUI::getLocale()->getLocalesByLang(
            QUI::getLocale()->getCurrent()
        );

        $Formatter = new IntlDateFormatter(
            $localeCode[0],
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE
        );

        $currentDate = $this->getAttribute('date');

        if (!$currentDate) {
            $currentDate = time();
        } else {
            $currentDate = strtotime($currentDate);
        }

        if ($this->getInvoiceType() === QUI\ERP\Constants::TYPE_INVOICE_CREDIT_NOTE) {
            $message = $this->getCustomer()->getLocale()->get(
                'quiqqer/invoice',
                'message.invoice.reversal.additionalInvoiceText.creditNote',
                [
                    'id' => $this->getPrefixedNumber(),
                    'date' => $Formatter->format($currentDate)
                ]
            );
        } else {
            $message = $this->getCustomer()->getLocale()->get(
                'quiqqer/invoice',
                'message.invoice.reversal.additionalInvoiceText',
                [
                    'id' => $this->getPrefixedNumber(),
                    'date' => $Formatter->format($currentDate)
                ]
            );
        }


        $additionalText = $Copy->getAttribute('additional_invoice_text');

        if (!empty($additionalText)) {
            $additionalText .= '<br />';
        }

        $additionalText .= $message;

        // saving copy
        $Copy->setData('originalId', $this->getId());
        $Copy->setData('originalIdPrefixed', $this->getPrefixedNumber());
        $Copy->setData('originalInvoice', $this->getReferenceData());

        $Copy->setAttribute('date', date('Y-m-d H:i:s'));
        $Copy->setAttribute('additional_invoice_text', $additionalText);
        $Copy->setAttribute('currency_data', $this->getAttribute('currency_data'));
        $Copy->setInvoiceType(QUI\ERP\Constants::TYPE_INVOICE_REVERSAL, QUI::getUsers()->getSystemUser());

        if ($this->getAttribute('invoice_address')) {
            try {
                $address = json_decode($this->getAttribute('invoice_address'), true);
                $Address = new QUI\ERP\Address($address);

                $invoiceAddressId = $Address->getUUID();
                $invoiceAddress = $Address->toJSON();

                $Copy->setAttribute('invoice_address_id', $invoiceAddressId);
                $Copy->setAttribute('invoice_address', $invoiceAddress);
            } catch (\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }
        }

        $Copy->save(QUI::getUsers()->getSystemUser());

        $this->addHistory(
            QUI::getLocale()->get('quiqqer/invoice', 'message.create.reversal', [
                'invoiceParentId' => $this->getUUID(),
                'invoiceId' => $Copy->getUUID()
            ])
        );

        $Reversal = Handler::getInstance()->getTemporaryInvoice($Copy->getUUID());

        QUI::getEvents()->fireEvent('quiqqerInvoiceCreateReversalEnd', [$this, $Reversal]);

        return $Reversal;
    }

    /**
     * Links an existing transaction (i.e. a transaction that was originally created for a different entity than
     * this invoice) to this invoice.
     *
     * @param Transaction $Transaction
     * @return void
     *
     * @throws QUI\Database\Exception
     * @throws QUI\Exception
     */
    public function linkTransaction(Transaction $Transaction): void
    {
        if ($this->isTransactionIdAddedToInvoice($Transaction->getTxId())) {
            return;
        }

        if ($Transaction->isHashLinked($this->getUUID())) {
            return;
        }

        if (
            $this->getInvoiceType() == QUI\ERP\Constants::TYPE_INVOICE_REVERSAL
            || $this->getInvoiceType() == QUI\ERP\Constants::TYPE_INVOICE_CANCEL
            || $this->getInvoiceType() == QUI\ERP\Constants::TYPE_INVOICE_CREDIT_NOTE
        ) {
            return;
        }

        $currentPaidStatus = $this->getAttribute('paid_status');
        $this->calculatePayments();

        if (
            $currentPaidStatus === $this->getAttribute('paid_status')
            && ($this->getAttribute('paid_status') == QUI\ERP\Constants::PAYMENT_STATUS_PAID
                || $this->getAttribute('paid_status') == QUI\ERP\Constants::PAYMENT_STATUS_CANCELED)
        ) {
            return;
        }

        QUI\ERP\Debug::getInstance()->log('Invoice :: link transaction');

        $Transaction->addLinkedHash($this->getUUID());

        $this->calculatePayments();

        $User = QUI::getUserBySession();

        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/invoice',
                'history.message.linkTransaction',
                [
                    'username' => $User->getName(),
                    'uid' => $User->getId(),
                    'txId' => $Transaction->getTxId(),
                    'txAmount' => $Transaction->getAmountFormatted()
                ]
            )
        );
    }

    /**
     * @param Transaction $Transaction
     *
     * @throws QUI\Exception
     */
    public function addTransaction(Transaction $Transaction): void
    {
        QUI\ERP\Debug::getInstance()->log('Invoice:: add transaction');

        if ($this->getUUID() !== $Transaction->getHash()) {
            return;
        }

        $currentPaidStatus = $this->getAttribute('paid_status');

        $this->calculatePayments();

        if (
            $this->getInvoiceType() == QUI\ERP\Constants::TYPE_INVOICE_REVERSAL
            || $this->getInvoiceType() == QUI\ERP\Constants::TYPE_INVOICE_CANCEL
            || $this->getInvoiceType() == QUI\ERP\Constants::TYPE_INVOICE_CREDIT_NOTE
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
        $amount = Price::validatePrice($Transaction->getAmount());
        $date = $Transaction->getDate();

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceAddTransactionBegin',
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

        if (empty($date)) {
            $date = time();
        }

        // already added
        if ($this->isTransactionIdAddedToInvoice($Transaction->getTxId())) {
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
            'quiqqerInvoiceAddTransaction',
            [
                $this,
                $amount,
                $Transaction,
                $date
            ]
        );

        $this->calculatePayments();

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceAddTransactionEnd',
            [
                $this,
                $amount,
                $Transaction,
                $date
            ]
        );
    }

    /**
     * @param string $txId
     * @return bool
     */
    protected function isTransactionIdAddedToInvoice(string $txId): bool
    {
        $paidData = $this->getAttribute('paid_data');

        if (is_string($paidData)) {
            $paidData = json_decode($paidData, true);
        }

        if (!is_array($paidData)) {
            $paidData = [];
        }

        foreach ($paidData as $paidEntry) {
            if (!isset($paidEntry['txid'])) {
                continue;
            }

            if ($paidEntry['txid'] == $txId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculates the payments and set the new part payments
     *
     * @throws
     */
    public function calculatePayments(): void
    {
        $User = QUI::getUserBySession();

        // old status
        $oldPaidStatus = $this->getAttribute('paid_status');

        /**
         * Do not change paid_status if invoice is paid via direct debit.
         *
         * In this case the paid_status has to be explicitly set via $this->setPaymentStatus()
         */
        if ($oldPaidStatus == QUI\ERP\Constants::PAYMENT_STATUS_DEBIT) {
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
                'paid_data' => $this->getAttribute('paid_data'),
                'paid_date' => $this->getAttribute('paid_date'),
                'paid_status' => $this->getAttribute('paid_status')
            ],
            ['id' => $this->getId()]
        );

        // Payment Status has changed
        if ($oldPaidStatus != $this->getAttribute('paid_status')) {
            $this->addHistory(
                QUI::getLocale()->get(
                    'quiqqer/invoice',
                    'history.message.edit',
                    [
                        'username' => $User->getName(),
                        'uid' => $User->getId()
                    ]
                )
            );

            QUI::getEvents()->fireEvent(
                'quiqqerInvoicePaymentStatusChanged',
                [
                    $this,
                    $this->getAttribute('paid_status'),
                    $oldPaidStatus
                ]
            );
        }
    }

    /**
     * Set the payment status of this invoice (attribute: paid_status)
     *
     * @param int $paymentStatus
     * @return void
     *
     * @throws Exception
     * @throws QUI\Database\Exception
     * @throws QUI\Exception
     * @throws ExceptionStack
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
                'paid_data' => $this->getAttribute('paid_data'),
                'paid_date' => $this->getAttribute('paid_date'),
                'paid_status' => $paymentStatus
            ],
            ['id' => $this->getId()]
        );

        $this->setAttribute('paid_status', $paymentStatus);

        // Payment Status has changed
        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/invoice',
                'history.message.set_payment_status',
                [
                    'username' => $User->getName(),
                    'uid' => $User->getUUID(),
                    'oldStatus' => QUI::getLocale()->get('quiqqer/erp', 'payment.status.' . $oldPaymentStatus),
                    'newStatus' => QUI::getLocale()->get('quiqqer/erp', 'payment.status.' . $paymentStatus)
                ]
            )
        );

        QUI::getEvents()->fireEvent(
            'quiqqerInvoicePaymentStatusChanged',
            [
                $this,
                $paymentStatus,
                $oldPaymentStatus
            ]
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
    public function sendTo(string $recipient, bool|string $template = false): void
    {
        $type = $this->getInvoiceType();
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
            $this->getUUID(),
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
    public function addComment(string $comment, QUI\Interfaces\Users\User $PermissionUser = null): void
    {
        if (empty($comment)) {
            return;
        }

        Permission::checkPermission(
            'quiqqer.invoice.addComment',
            $PermissionUser
        );

        $comment = strip_tags(
            $comment,
            '<div><span><pre><p><br><hr>
            <ul><ol><li><dl><dt><dd><strong><em><b><i><u>
            <img><table><tbody><td><tfoot><th><thead><tr>'
        );

        $User = QUI::getUserBySession();
        $comments = $this->getAttribute('comments');
        $Comments = QUI\ERP\Comments::unserialize($comments);

        $Comments->addComment(
            $comment,
            false,
            'quiqqer/invoice',
            Factory::ERP_INVOICE_ICON,
            false,
            $this->getUUID()
        );

        $this->setAttribute('comments', $Comments->toJSON());

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

        QUI::getDataBase()->update(
            Handler::getInstance()->invoiceTable(),
            ['comments' => $Comments->toJSON()],
            ['id' => $this->getId()]
        );

        QUI::getEvents()->fireEvent(
            'onQuiqqerInvoiceAddComment',
            [
                $this,
                $comment
            ]
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
    public function addHistory(string $comment): void
    {
        $history = $this->getAttribute('history');
        $History = QUI\ERP\Comments::unserialize($history);

        $History->addComment(
            $comment,
            false,
            'quiqqer/invoice',
            Factory::ERP_INVOICE_ICON,
            false,
            $this->getUUID()
        );

        $this->setAttribute('history', $History->toJSON());

        QUI::getDataBase()->update(
            Handler::getInstance()->invoiceTable(),
            ['history' => $History->toJSON()],
            ['id' => $this->getId()]
        );

        QUI::getEvents()->fireEvent('onQuiqqerInvoiceAddHistory', [
            $this,
            $comment
        ]);
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
    public function addCustomDataEntry(string $key, mixed $value): void
    {
        $this->customData[$key] = $value;

        QUI::getDataBase()->update(
            Handler::getInstance()->invoiceTable(),
            ['custom_data' => json_encode($this->customData)],
            ['id' => $this->getId()]
        );

        QUI::getEvents()->fireEvent(
            'onQuiqqerInvoiceAddCustomData',
            [
                $this,
                $this,
                $this->customData,
                $key,
                $value
            ]
        );
    }

    /**
     * Return a wanted custom data entry
     *
     * @param $key
     * @return mixed|null
     */
    public function getCustomDataEntry($key): mixed
    {
        if (isset($this->customData[$key])) {
            return $this->customData[$key];
        }

        return null;
    }

    /**
     * Return all custom data
     *
     * @return array
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

        $attributes['uuid'] = $this->getUUID();
        $attributes['entityType'] = $this->getType();
        $attributes['globalProcessId'] = $this->getGlobalProcessId();
        $attributes['prefixedNumber'] = $this->getPrefixedNumber();

        return $attributes;
    }

    //region processing status

    /**
     * Set the processing status
     *
     * @param integer $statusId
     * @throws QUI\Exception
     */
    public function setProcessingStatus(int $statusId): void
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
                ['id' => $this->getId()]
            );

            $this->setAttribute('processing_status', $Status->getId());
        } catch (QUI\DataBase\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());
        }


        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceProcessingStatusSet',
            [
                $this,
                $Status
            ]
        );

        if ($CurrentStatus && $CurrentStatus->getId() !== $Status->getId() || !$CurrentStatus) {
            QUI::getEvents()->fireEvent(
                'quiqqerInvoiceProcessingStatusChange',
                [
                    $this,
                    $Status
                ]
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
            return ProcessingStatus\Handler::getInstance()->getProcessingStatus(
                $this->getAttribute('processing_status')
            );
        } catch (ProcessingStatus\Exception $Exception) {
            QUI\System\Log::addDebug($Exception->getMessage());

            return null;
        }
    }

    //endregion

    //region Data

    /**
     * Return a data field
     *
     * @param string $key
     * @return bool|array|mixed
     */
    public function getData(string $key): mixed
    {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }

        return false;
    }

    //endregion

    /**
     * Get the price calculation
     *
     * @return Calculations
     * @throws Exception|QUI\Exception
     */
    public function getPriceCalculation(): Calculations
    {
        $Articles = $this->getArticles();

        return new Calculations(
            $Articles->getCalculations(),
            $Articles->getArticles()
        );
    }

    /**
     * Retrieves the invoice address of the object.
     *
     * @return ErpAddress|null The invoice address, or null if no invoice address is available.
     */
    public function getInvoiceAddress(): ?ErpAddress
    {
        if ($this->getAttribute('invoice_address')) {
            try {
                return new QUI\ERP\Address(
                    json_decode($this->getAttribute('invoice_address'), true)
                );
            } catch (\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }
        }

        return null;
    }

    /**
     * Returns the delivery address for the current object.
     *
     * @return ErpAddress|null Returns an instance of ErpAddress if the 'invoice_address' attribute is set. Returns null otherwise.
     */
    public function getDeliveryAddress(): ?ErpAddress
    {
        if ($this->getAttribute('delivery_address')) {
            try {
                return new QUI\ERP\Address(
                    json_decode($this->getAttribute('delivery_address'), true)
                );
            } catch (\Exception $Exception) {
                QUI\System\Log::addDebug($Exception->getMessage());
            }
        }

        return null;
    }

    /**
     * this method is there to comply with the interface
     * customer is not changeable at an invoice
     *
     * @param $User
     * @return void
     */
    public function setCustomer($User)
    {
        // customer is not changeable at an invoice
    }
}
