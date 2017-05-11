<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Invoice
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;
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
    const PAYMENT_STATUS_CANCEL = 3;
    const PAYMENT_STATUS_ERROR = 4;
    const PAYMENT_STATUS_DEBIT = 11;

    const PAYMENT_STATUS_STORNO = 3; // Alias for cancel

    const DUNNING_LEVEL_OPEN = 0; // No Dunning -> Keine Mahnung
    const DUNNING_LEVEL_REMIND = 1; // Payment reminding -> Zahlungserinnerung
    const DUNNING_LEVEL_DUNNING = 2; // Dunning -> Erste Mahnung
    const DUNNING_LEVEL_DUNNING2 = 3; // Second dunning -> Zweite Mahnung
    const DUNNING_LEVEL_COLLECTION = 4; // Collection -> Inkasso

    /**
     * @var string
     */
    const ID_PREFIX = 'INV-';

    /**
     * @var int
     */
    protected $id;

    /**
     * Invoice constructor.
     *
     * @param $id
     * @param Handler $Handler
     */
    public function __construct($id, Handler $Handler)
    {
        $this->setAttributes($Handler->getInvoiceData($id));

        $this->id = (int)str_replace(self::ID_PREFIX, '', $id);
    }

    /**
     * Return the invoice id
     *
     * @return string
     */
    public function getId()
    {
        return self::ID_PREFIX . $this->id;
    }

    /**
     * Returns only the integer id
     *
     * @return int
     */
    public function getCleanId()
    {
        return (int)str_replace(self::ID_PREFIX, '', $this->getId());
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
        return new ArticleListUnique($this->getAttribute('articles'));
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
     * Storno
     */
    public function cancel()
    {
        $User = QUI::getUserBySession();

        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/invoice',
                'history.message.cancel',
                array(
                    'username' => $User->getName(),
                    'uid'      => $User->getId()
                )
            )
        );

        // @todo wenn umgesetzt wird, vorher mor fragen
        // -> Gutschrift erzeugen
    }

    /**
     * Alias for cancel
     */
    public function storno()
    {
        $this->cancel();
    }

    /**
     * Copy the invoice to a temporary invoice
     *
     * @param null $User
     * @return TemporaryInvoice
     */
    public function copy($User = null)
    {
        // @todo permissions
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

        QUI::getDataBase()->update(
            $Handler->temporaryInvoiceTable(),
            array(
                'customer_id'             => $currentData['customer_id'],
                'invoice_address_id'      => '',
                'invoice_address'         => $currentData['invoice_address'],
                'delivery_address_id'     => '',
                'delivery_address'        => $currentData['delivery_address'],
                'order_id'                => $currentData['order_id'],
                'project_name'            => $currentData['project_name'],
                'payment_method'          => $currentData['payment_method'],
                'payment_data'            => $currentData['payment_data'],
                'payment_time'            => $currentData['payment_time'],
                'time_for_payment'        => $currentData['time_for_payment'],
                'paid_status'             => $currentData['paid_status'],
                'paid_date'               => $currentData['paid_date'],
                'paid_data'               => $currentData['paid_data'],
                'date'                    => $currentData['date'],
                'data'                    => $currentData['data'],
                'additional_invoice_text' => $currentData['additional_invoice_text'],
                'articles'                => $currentData['articles'],
                'history'                 => $currentData['history'],
                'comments'                => $currentData['comments'],
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

        return $Handler->getTemporaryInvoice($New->getId());
    }

    /**
     * @todo Gutschrift
     */
    public function createCredit()
    {

    }

    /**
     * Add a payment to the invoice
     *
     * @param string|int|float $amount - Payment amount
     * @param PaymentsInterface $Payment - Payment method
     * @param int|string|bool $date - optional, unix timestamp
     *
     * @todo event einführen
     */
    public function addPayment($amount, PaymentsInterface $Payment, $date = false)
    {
        // @todo permissions

        QUI\ERP\Accounting\Calc::calculateInvoicePayments($this);

        if ($this->getAttribute('paid_status') == self::PAYMENT_STATUS_PAID) {
            return;
        }

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
            'payment' => $Payment->getName(),
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
                    'payment'  => $Payment->getTitle()
                )
            )
        );

        $this->calculatePayments();
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
            case self::PAYMENT_STATUS_CANCEL:
            case self::PAYMENT_STATUS_STORNO:
            case self::PAYMENT_STATUS_ERROR:
            case self::PAYMENT_STATUS_DEBIT:
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

        if ($oldPaidStatus != $this->getAttribute('paid_status')) {
            // Payment Status has changed
            // @todo fire event
        }
    }

    //region Comments & History

    /**
     * Add a comment to the invoice
     *
     * @param string $comment
     */
    public function addComment($comment)
    {
        // @todo permissions

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
