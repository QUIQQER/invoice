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

/**
 * Class Invoice
 * - Invoice class
 * - This class present a posted invoice
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class Invoice extends QUI\QDOM
{
    const PAYMENT_STATUS_OPEN = 0;
    const PAYMENT_STATUS_PAID = 1;
    const PAYMENT_STATUS_PART = 2;
    const PAYMENT_STATUS_ERROR = 4;
    const PAYMENT_STATUS_CANCELED = 5;
    const PAYMENT_STATUS_DEBIT = 11;

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
    }

    /**
     * Return the invoice id
     *
     * @return string
     */
    public function getId()
    {
        return $this->prefix.$this->id;
    }

    /**
     * Return the hash
     * (Vorgangsnummer)
     *
     * @return string
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
    public function getGlobalProcessId()
    {
        return $this->globalProcessId;
    }

    /**
     * Returns only the integer id
     *
     * @return int
     */
    public function getCleanId()
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
    public function getView()
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
    public function getArticles()
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

        return new ArticleListUnique($articles);
    }

    /**
     * Return the invoice currency
     *
     * @return QUI\ERP\Currency\Currency
     *
     * @throws QUI\Exception
     */
    public function getCurrency()
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
    public function getCustomer()
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
    public function getEditor()
    {
        return new QUI\ERP\User([
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

    /**
     * Return the payment paid status information
     * - How many has already been paid
     * - How many must be paid
     *
     * @return array
     *
     * @throws
     */
    public function getPaidStatusInformation()
    {
        $oldStatus = $this->getAttribute('paid_status');

        QUI\ERP\Accounting\Calc::calculatePayments($this);

        // the status is another as calced, to we must update the invoice data
        if ($this->getAttribute('paid_status') !== $oldStatus) {
            $this->calculatePayments();
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
    public function getPaymentDataEntry($key)
    {
        if (\array_key_exists($key, $this->paymentData)) {
            return $this->paymentData[$key];
        }

        return null;
    }

    /**
     * can a refund be made?
     *
     * @return bool
     */
    public function hasRefund()
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
    public function isPaid()
    {
        return $this->getAttribute('toPay') <= 0;
    }

    /**
     * Return the presentational payment method
     *
     * @return Payment
     */
    public function getPayment()
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
     * Return the invoice type
     *
     * - Handler::TYPE_INVOICE
     * - Handler::TYPE_INVOICE_TEMPORARY
     * - Handler::TYPE_INVOICE_CREDIT_NOTE
     * - Handler::TYPE_INVOICE_CANCEL
     *
     * @return int
     */
    public function getInvoiceType()
    {
        return $this->type;
    }

    /**
     * Cancel the invoice
     * (Reversal, Storno, Cancel)
     *
     * @param string $reason
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @return int - ID of the new
     *
     * @throws
     */
    public function reversal($reason, $PermissionUser = null)
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

        if ($this->getAttribute('paid_status') == self::PAYMENT_STATUS_PART) {
            throw new Exception([
                'quiqqer/invoice',
                'exception.parted.invoice.cant.be.canceled'
            ]);
        }

        if ($this->getAttribute('paid_status') == self::PAYMENT_STATUS_CANCELED) {
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

        $this->data['canceledId'] = $CreditNote->getId();

        QUI::getDataBase()->update(
            Handler::getInstance()->invoiceTable(),
            [
                'type'        => $this->type,
                'data'        => \json_encode($this->data),
                'paid_status' => self::PAYMENT_STATUS_CANCELED
            ],
            ['id' => $this->getCleanId()]
        );

        $this->addComment($reason, QUI::getUsers()->getSystemUser());


        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceReversalEnd',
            [$this]
        );

        $CreditNote->post(QUI::getUsers()->getSystemUser());


        return $CreditNote->getId();
    }

    /**
     * Alias for reversal
     *
     * @param string $reason
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @return int - ID of the new
     *
     * @throws
     */
    public function cancellation($reason, $PermissionUser = null)
    {
        return $this->reversal($reason, $PermissionUser);
    }

    /**
     * Alias for reversal
     *
     * @param string $reason
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @return int - ID of the new
     * @throws
     */
    public function storno($reason, $PermissionUser = null)
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
     * @throws
     */
    public function copy($PermissionUser = null, $globalProcessId = false)
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

        QUI::getDataBase()->update(
            $Handler->temporaryInvoiceTable(),
            [
                'global_process_id'       => $globalProcessId,
                'type'                    => Handler::TYPE_INVOICE_TEMPORARY,
                'customer_id'             => $currentData['customer_id'],
                'invoice_address'         => $currentData['invoice_address'],
                'delivery_address'        => $currentData['delivery_address'],
                'order_id'                => $currentData['order_id'],
                'project_name'            => $currentData['project_name'],
                'payment_method'          => $currentData['payment_method'],
                'payment_data'            => '',
                'payment_time'            => $currentData['payment_time'],
                'time_for_payment'        => (int)Settings::getInstance()->get('invoice', 'time_for_payment'),
                'paid_status'             => self::PAYMENT_STATUS_OPEN,
                'paid_date'               => null,
                'paid_data'               => null,
                'date'                    => $currentData['date'],
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
     * @throws
     */
    public function createCreditNote($PermissionUser = null, $globalProcessId = false)
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
            /* @var $Article QUI\ERP\Accounting\Article */
            $article              = $Article->toArray();
            $article['unitPrice'] = $article['unitPrice'] * -1;

            $Clone = new QUI\ERP\Accounting\Article($article);
            $ArticleList->addArticle($Clone);
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

        $message = QUI::getLocale()->get(
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
        $Copy->setAttribute('date', \date('Y-m-d H:i:s'));
        $Copy->setAttribute('additional_invoice_text', $additionalText);
        $Copy->setAttribute('currency_data', $this->getAttribute('currency_data'));
        $Copy->setInvoiceType(Handler::TYPE_INVOICE_CREDIT_NOTE);
        $Copy->save(QUI::getUsers()->getSystemUser());

        $this->addHistory(
            QUI::getLocale()->get('quiqqer/invoice', 'message.create.credit', [
                'invoiceParentId' => $this->getId(),
                'invoiceId'       => $Copy->getId()
            ])
        );

        return Handler::getInstance()->getTemporaryInvoice($Copy->getId());
    }

    /**
     * @param Transaction $Transaction
     * @throws
     */
    public function addTransaction(Transaction $Transaction)
    {
        QUI\ERP\Debug::getInstance()->log('Invoice:: add transaction');

        if ($this->getHash() !== $Transaction->getHash()) {
            return;
        }

        QUI\ERP\Accounting\Calc::calculatePayments($this);

        if ($this->getInvoiceType() == Handler::TYPE_INVOICE_REVERSAL
            || $this->getInvoiceType() == Handler::TYPE_INVOICE_CANCEL
            || $this->getInvoiceType() == Handler::TYPE_INVOICE_CREDIT_NOTE
        ) {
            return;
        }

        if ($this->getAttribute('paid_status') == self::PAYMENT_STATUS_PAID ||
            $this->getAttribute('paid_status') == self::PAYMENT_STATUS_CANCELED
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

        function isTxAlreadyAdded($txid, $paidData)
        {
            foreach ($paidData as $paidEntry) {
                if (!isset($paidEntry['txid'])) {
                    continue;
                }

                if ($paidEntry['txid'] == $txid) {
                    return true;
                }
            }

            return false;
        }

        // already added
        if (isTxAlreadyAdded($Transaction->getTxId(), $paidData)) {
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

        QUI\ERP\Accounting\Calc::calculatePayments($this);

        switch ($this->getAttribute('paid_status')) {
            case self::PAYMENT_STATUS_OPEN:
            case self::PAYMENT_STATUS_PAID:
            case self::PAYMENT_STATUS_PART:
            case self::PAYMENT_STATUS_ERROR:
            case self::PAYMENT_STATUS_DEBIT:
            case self::PAYMENT_STATUS_CANCELED:
                break;

            default:
                $this->setAttribute('paid_status', self::PAYMENT_STATUS_ERROR);
        }

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
            QUI::getEvents()->fireEvent(
                'onQuiqqerInvoiceAddComment',
                [$this, $this->getAttribute('paid_status'), $oldPaidStatus]
            );
        }
    }

    /**
     * Send the invoice to a recipient
     *
     * @param string $recipient - The recipient email address
     * @param bool|string $template - pdf template
     *
     * @throws QUI\Exception
     */
    public function sendTo($recipient, $template = false)
    {
        $View = $this->getView();

        if ($template) {
            $View->setAttribute('template', $template);
        }

        $pdfFile = $View->toPDF()->createPDF();

        $Mailer = QUI::getMailManager()->getMailer();
        $Mailer->addRecipient($recipient);

        // invoice pdf file
        $dir     = \dirname($pdfFile);
        $newFile = $dir.'/'.InvoiceUtils::getInvoiceFilename($this).'.pdf';

        if ($newFile !== $pdfFile && \file_exists($newFile)) {
            \unlink($newFile);
        }

        \rename($pdfFile, $newFile);

        $user = $this->getCustomer()->getName();
        $user = \trim($user);

        if (empty($user)) {
            $user = $this->getCustomer()->getAddress()->getName();
        }

        // mail send
        $Mailer->addAttachment($newFile);

        $Mailer->setBody(
            QUI::getLocale()->get('quiqqer/invoice', 'invoice.send.mail.message', [
                'invoiceId' => $this->getId(),
                'user'      => $user,
                'address'   => $this->getCustomer()->getAddress()->render(),
                'invoice'   => $View->getTemplate()->getHTMLBody()
            ])
        );

        $Mailer->setSubject(
            QUI::getLocale()->get('quiqqer/invoice', 'invoice.send.mail.subject', [
                'invoiceId' => $this->getId()
            ])
        );

        $Mailer->send();

        $this->addHistory(
            QUI::getLocale()->get('quiqqer/invoice', 'message.add.history.sent.to', [
                'recipient' => $recipient
            ])
        );

        if (\file_exists($pdfFile)) {
            \unlink($pdfFile);
        }
    }

    //region Comments & History

    /**
     * Add a comment to the invoice
     *
     * @param string $comment
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     *
     * @throws
     */
    public function addComment($comment, $PermissionUser = null)
    {
        Permission::checkPermission(
            'quiqqer.invoice.addComment',
            $PermissionUser
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
    public function getComments()
    {
        $comments = $this->getAttribute('comments');

        return QUI\ERP\Comments::unserialize($comments);
    }

    /**
     * Add a comment to the history
     *
     * @param string $comment
     *
     * @throws QUI\Exception
     */
    public function addHistory($comment)
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
    public function getHistory()
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
    public function addCustomDataEntry($key, $value)
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
    public function getCustomData()
    {
        return $this->customData;
    }

    //endregion

    /**
     * @return array
     */
    public function toArray()
    {
        $attributes = $this->getAttributes();

        $attributes['globalProcessId'] = $this->getGlobalProcessId();

        return $attributes;
    }
}
