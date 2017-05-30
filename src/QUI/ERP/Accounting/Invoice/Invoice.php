<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Invoice
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;
use QUI\Permissions\Permission;
use QUI\ERP\Accounting\ArticleListUnique;
use QUI\ERP\Accounting\Payments\Api\PaymentsInterface;
use QUI\ERP\Money\Price;

/**
 * Class Invoice
 * - Invoice class
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
     * @var array
     */
    protected $data = array();

    /**
     * Invoice constructor.
     *
     * @param $id
     * @param Handler $Handler
     */
    public function __construct($id, Handler $Handler)
    {
        $this->setAttributes($Handler->getInvoiceData($id));

        $this->prefix = Settings::getInstance()->getInvoicePrefix();
        $this->id     = (int)str_replace($this->prefix, '', $id);
        $this->type   = Handler::TYPE_INVOICE;

        switch ((int)$this->getAttribute('type')) {
            case Handler::TYPE_INVOICE:
            case Handler::TYPE_INVOICE_TEMPORARY:
            case Handler::TYPE_INVOICE_CREDIT_NOTE:
            case Handler::TYPE_INVOICE_REVERSAL:
                $this->type = (int)$this->getAttribute('type');
                break;
        }

        if ($this->getAttribute('data')) {
            $this->data = json_decode($this->getAttribute('data'), true);
        }
    }

    /**
     * Return the invoice id
     *
     * @return string
     */
    public function getId()
    {
        return $this->prefix . $this->id;
    }

    /**
     * Returns only the integer id
     *
     * @return int
     */
    public function getCleanId()
    {
        return (int)str_replace($this->prefix, '', $this->getId());
    }


    /**
     * Return the invoice view
     *
     * @return InvoiceView
     */
    public function getView()
    {
        return new InvoiceView($this);
    }

    /**
     * Return the unique article list
     *
     * @return ArticleListUnique
     */
    public function getArticles()
    {
        $articles = $this->getAttribute('articles');

        if (is_string($articles)) {
            $articles = json_decode($articles, true);
        }

        return new ArticleListUnique($articles);
    }

    /**
     * Return the invoice currency
     *
     * @return QUI\ERP\Currency\Currency
     */
    public function getCurrency()
    {
        $currency = $this->getAttribute('currency_data');

        if (!$currency) {
            QUI\ERP\Defaults::getCurrency();
        }

        if (is_string($currency)) {
            $currency = json_decode($currency, true);
        }

        if (!$currency || !isset($currency['code'])) {
            QUI\ERP\Defaults::getCurrency();
        }

        return QUI\ERP\Currency\Handler::getCurrency($currency['code']);
    }

    /**
     * @return QUI\ERP\User
     */
    public function getCustomer()
    {
        $invoiceAddress = $this->getAttribute('invoice_address');
        $customerData   = $this->getAttribute('customer_data');

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

        return new QUI\ERP\User($userData);
    }

    /**
     * @return QUI\ERP\User
     */
    public function getEditor()
    {
        return new QUI\ERP\User(array(
            'id'        => '',
            'country'   => '',
            'username'  => '',
            'firstname' => '',
            'lastname'  => '',
            'lang'      => '',
            'isCompany' => ''
        ));
    }

    /**
     * Return the payment paid status information
     * - How many has already been paid
     * - How many must be paid
     *
     * @return array
     */
    public function getPaidStatusInformation()
    {
        QUI\ERP\Accounting\Calc::calculateInvoicePayments($this);

        return array(
            'paidData' => $this->getAttribute('paid_data'),
            'paidDate' => $this->getAttribute('paid_date'),
            'paid'     => $this->getAttribute('paid'),
            'toPay'    => $this->getAttribute('toPay')
        );
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
     * @throws Exception
     */
    public function reversal($reason, $PermissionUser = null)
    {
        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        Permission::checkPermission(
            'quiqqer.invoice.reversal',
            $PermissionUser
        );

        if (empty($reason)) {
            throw new Exception(array(
                'quiqqer/invoice',
                'exception.missing.reason.in.reversal'
            ));
        }

        // if invoice is parted paid, it could not be canceled
        $this->getPaidStatusInformation();

        if ($this->getAttribute('paid_status') == self::PAYMENT_STATUS_PART) {
            throw new Exception(array(
                'quiqqer/invoice',
                'exception.parted.invoice.cant.be.canceled'
            ));
        }

        if ($this->getAttribute('paid_status') == self::PAYMENT_STATUS_CANCELED) {
            throw new Exception(array(
                'quiqqer/invoice',
                'exception.canceled.invoice.cant.be.canceled'
            ));
        }


        $User = QUI::getUserBySession();

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceReversal',
            array($this)
        );


        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/invoice',
                'history.message.reversal',
                array(
                    'username' => $User->getName(),
                    'uid'      => $User->getId()
                )
            )
        );

        $CreditNote = $this->createCreditNote(QUI::getUsers()->getSystemUser());
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
                array(
                    'username'     => $User->getName(),
                    'uid'          => $User->getId(),
                    'creditNoteId' => $CreditNote->getId()
                )
            )
        );

        // set the invoice status
        $this->type = Handler::TYPE_INVOICE_CANCEL;

        $this->data['canceledId'] = $CreditNote->getId();

        QUI::getDataBase()->update(
            Handler::getInstance()->invoiceTable(),
            array(
                'type'        => $this->type,
                'data'        => json_encode($this->data),
                'paid_status' => self::PAYMENT_STATUS_CANCELED
            ),
            array('id' => $this->getCleanId())
        );

        $this->addComment($reason, QUI::getUsers()->getSystemUser());


        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceReversalEnd',
            array($this)
        );

        return $CreditNote->getId();
    }

    /**
     * Alias for reversal
     *
     * @param string $reason
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @return int - ID of the new
     * @throws Exception
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
     * @throws Exception
     */
    public function storno($reason, $PermissionUser = null)
    {
        return $this->reversal($reason, $PermissionUser);
    }

    /**
     * Copy the invoice to a temporary invoice
     *
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @return InvoiceTemporary
     */
    public function copy($PermissionUser = null)
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
                array(
                    'username' => $User->getName(),
                    'uid'      => $User->getId()
                )
            )
        );

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceCopyBegin',
            array($this)
        );

        $Handler = Handler::getInstance();
        $Factory = Factory::getInstance();
        $New     = $Factory->createInvoice($User);

        $currentData = QUI::getDataBase()->fetch(array(
            'from'  => $Handler->invoiceTable(),
            'where' => array(
                'id' => $this->getCleanId()
            ),
            'limit' => 1
        ));

        $currentData = $currentData[0];

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceCopy',
            array($this)
        );

        QUI::getDataBase()->update(
            $Handler->temporaryInvoiceTable(),
            array(
                'type'                    => Handler::TYPE_INVOICE_TEMPORARY,
                'customer_id'             => $currentData['customer_id'],
                'invoice_address_id'      => '',
                'invoice_address'         => $currentData['invoice_address'],
                'delivery_address_id'     => '',
                'delivery_address'        => $currentData['delivery_address'],
                'order_id'                => $currentData['order_id'],
                'project_name'            => $currentData['project_name'],
                'payment_method'          => $currentData['payment_method'],
                'payment_data'            => '',
                'payment_time'            => '',
                'time_for_payment'        => '',
                'paid_status'             => self::PAYMENT_STATUS_OPEN,
                'paid_date'               => '',
                'paid_data'               => '',
                'date'                    => $currentData['date'],
                'data'                    => $currentData['data'],
                'additional_invoice_text' => $currentData['additional_invoice_text'],
                'articles'                => $currentData['articles'],
                'history'                 => '',
                'comments'                => '',
                'customer_data'           => $currentData['customer_data'],
                'isbrutto'                => $currentData['isbrutto'],
                'currency_data'           => $currentData['currency_data'],
                'nettosum'                => $currentData['nettosum'],
                'nettosubsum'             => $currentData['nettosubsum'],
                'subsum'                  => $currentData['subsum'],
                'sum'                     => $currentData['sum'],
                'vat_array'               => $currentData['vat_array'],
                'processing_status'       => ''
            ),
            array('id' => $New->getCleanId())
        );

        $NewTemporaryInvoice = $Handler->getTemporaryInvoice($New->getId());


        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceCopyEnd',
            array($this, $NewTemporaryInvoice)
        );

        return $NewTemporaryInvoice;
    }

    /**
     * Create a credit note, set the invoice to credit note
     * - Gutschrift
     *
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @return InvoiceTemporary
     */
    public function createCreditNote($PermissionUser = null)
    {
        Permission::checkPermission(
            'quiqqer.invoice.createCreditNote',
            $PermissionUser
        );

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceCreateCreditNote',
            array($this)
        );

        $Copy     = $this->copy(QUI::getUsers()->getSystemUser());
        $articles = $Copy->getArticles()->getArticles();

        // change all prices
        $ArticleList = $Copy->getArticles();
        $ArticleList->clear();

        foreach ($articles as $Article) {
            /* @var $Article QUI\ERP\Accounting\Article */
            $article              = $Article->toArray();
            $article['unitPrice'] = $article['unitPrice'] * -1;

            $Clone = new QUI\ERP\Accounting\Article($article);
            $ArticleList->addArticle($Clone);
        }

        $Copy->addHistory(
            QUI::getLocale()->get('quiqqer/invoice', 'message.create.credit.from', array(
                'invoiceParentId' => $this->getId()
            ))
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
            $currentDate = time();
        } else {
            $currentDate = strtotime($currentDate);
        }

        $message = QUI::getLocale()->get(
            'quiqqer/invoice',
            'message.invoice.creditNote.additionalInvoiceText',
            array(
                'id'   => $this->getId(),
                'date' => $Formatter->format($currentDate)
            )
        );

        $additionalText = $Copy->getAttribute('additional_invoice_text');

        if (!empty($additionalText)) {
            $additionalText .= '<br />';
        }

        $additionalText .= $message;

        // saving copy
        $Copy->setData('originalId', $this->getCleanId());
        $Copy->setAttribute('date', date('Y-m-d H:i:s'));
        $Copy->setAttribute('additional_invoice_text', $additionalText);
        $Copy->setInvoiceType(Handler::TYPE_INVOICE_CREDIT_NOTE);
        $Copy->save(QUI::getUsers()->getSystemUser());

        $this->addHistory(
            QUI::getLocale()->get('quiqqer/invoice', 'message.create.credit', array(
                'invoiceParentId' => $this->getId(),
                'invoiceId'       => $Copy->getId()
            ))
        );

        return Handler::getInstance()->getTemporaryInvoice($Copy->getId());
    }

    /**
     * Add a payment to the invoice
     *
     * @param string|int|float $amount - Payment amount
     * @param PaymentsInterface $PaymentMethod - Payment method
     * @param int|string|bool $date - optional, unix timestamp
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     */
    public function addPayment($amount, PaymentsInterface $PaymentMethod, $date = false, $PermissionUser = null)
    {
        Permission::checkPermission(
            'quiqqer.invoice.addPayment',
            $PermissionUser
        );


        QUI\ERP\Accounting\Calc::calculateInvoicePayments($this);

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


        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceAddPaymentBegin',
            array($this, $amount, $PaymentMethod, $date)
        );

        $User     = QUI::getUserBySession();
        $paidData = $this->getAttribute('paid_data');
        $amount   = Price::validatePrice($amount);

        if (!$amount) {
            return;
        }

        if (!is_array($paidData)) {
            $paidData = json_decode($paidData, true);
        }

        if ($date === false) {
            $date = time();
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

        $paidData[] = array(
            'amount'  => $amount,
            'payment' => $PaymentMethod->getName(),
            'date'    => $date
        );

        $this->setAttribute('paid_data', json_encode($paidData));
        $this->setAttribute('paid_date', $date);

        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/invoice',
                'history.message.addPayment',
                array(
                    'username' => $User->getName(),
                    'uid'      => $User->getId(),
                    'payment'  => $PaymentMethod->getTitle()
                )
            )
        );

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceAddPayment',
            array($this, $amount, $PaymentMethod, $date)
        );

        $this->calculatePayments();

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceAddPaymentEnd',
            array($this, $amount, $PaymentMethod, $date)
        );
    }

    /**
     * Calculates the payments and set the new part payments
     */
    protected function calculatePayments()
    {
        $User = QUI::getUserBySession();

        // old status
        $oldPaidStatus = $this->getAttribute('paid_status');

        QUI\ERP\Accounting\Calc::calculateInvoicePayments($this);

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
                array(
                    'username' => $User->getName(),
                    'uid'      => $User->getId()
                )
            )
        );

        QUI::getDataBase()->update(
            Handler::getInstance()->invoiceTable(),
            array(
                'paid_data'   => $this->getAttribute('paid_data'),
                'paid_date'   => $this->getAttribute('paid_date'),
                'paid_status' => $this->getAttribute('paid_status')
            ),
            array('id' => $this->getCleanId())
        );

        // Payment Status has changed
        if ($oldPaidStatus != $this->getAttribute('paid_status')) {
            QUI::getEvents()->fireEvent(
                'onQuiqqerInvoiceAddComment',
                array($this, $this->getAttribute('paid_status'), $oldPaidStatus)
            );
        }
    }

    /**
     * Send the invoice to a recipient
     *
     * @param string $recipient - The recipient email address
     */
    public function sendTo($recipient)
    {
        $View    = $this->getView();
        $pdfFile = $View->toPDF()->createPDF();

        $Mailer = QUI::getMailManager()->getMailer();
        $Mailer->setSubject('Rechnungs Nr:' . $this->getId());
        $Mailer->addRecipient($recipient);
        $Mailer->setBody($View->toHTML());
        $Mailer->addAttachment($pdfFile);

        $Mailer->send();

        unlink($pdfFile);
    }

    //region Comments & History

    /**
     * Add a comment to the invoice
     *
     * @param string $comment
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     */
    public function addComment($comment, $PermissionUser = null)
    {
        Permission::checkPermission(
            'quiqqer.invoice.addComment',
            $PermissionUser
        );

        $User     = QUI::getUserBySession();
        $comments = $this->getAttribute('comments');
        $Comments = Comments::unserialize($comments);

        $Comments->addComment($comment);

        $this->setAttribute('comments', $Comments->toJSON());

        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/invoice',
                'history.message.addComment',
                array(
                    'username' => $User->getName(),
                    'uid'      => $User->getId()
                )
            )
        );

        QUI::getDataBase()->update(
            Handler::getInstance()->invoiceTable(),
            array('comments' => $Comments->toJSON()),
            array('id' => $this->getCleanId())
        );

        QUI::getEvents()->fireEvent(
            'onQuiqqerInvoiceAddComment',
            array($this, $comment)
        );
    }

    /**
     * Add a comment to the history
     *
     * @param string $comment
     */
    public function addHistory($comment)
    {
        $history = $this->getAttribute('history');
        $History = Comments::unserialize($history);

        $History->addComment($comment);

        $this->setAttribute('history', $History->toJSON());

        QUI::getDataBase()->update(
            Handler::getInstance()->invoiceTable(),
            array('history' => $History->toJSON()),
            array('id' => $this->getCleanId())
        );

        QUI::getEvents()->fireEvent('onQuiqqerInvoiceAddHistory', array($this, $comment));
    }

    //endregion

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->getAttributes();
    }
}
