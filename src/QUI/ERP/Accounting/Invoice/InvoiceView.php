<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\InvoiceView
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;
use QUI\ERP\Output\Output as ERPOutput;

/**
 * Class InvoiceView
 * View for a invoice
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class InvoiceView extends QUI\QDOM
{
    /**
     * @var Invoice|InvoiceTemporary
     */
    protected $Invoice;

    /**
     * InvoiceView constructor.
     *
     * @param Invoice|InvoiceTemporary $Invoice
     *
     * @throws Exception
     */
    public function __construct($Invoice)
    {
        if ($Invoice instanceof Invoice || $Invoice instanceof InvoiceTemporary) {
            $this->Invoice = $Invoice;

            return;
        }

        throw new Exception('$Invoice must be an instance of Invoice or InvoiceTemporary');
    }

    /**
     * @return QUI\ERP\Accounting\ArticleList|QUI\ERP\Accounting\ArticleListUnique
     */
    public function getArticles()
    {
        return $this->Invoice->getArticles();
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->Invoice->getId();
    }

    /**
     * @return string
     */
    public function getHash()
    {
        return $this->Invoice->getHash();
    }

    /**
     * @return QUI\ERP\Currency\Currency
     */
    public function getCurrency()
    {
        try {
            return $this->Invoice->getCurrency();
        } catch (QUI\Exception $Exception) {
            return QUI\ERP\Defaults::getCurrency();
        }
    }

    /**
     * @return false|null|QUI\ERP\User|QUI\Users\Nobody|QUI\Users\SystemUser|QUI\Users\User
     */
    public function getCustomer()
    {
        try {
            return $this->Invoice->getCustomer();
        } catch (QUI\Exception $Exception) {
            return null;
        }
    }

    /**
     * @param null|QUI\Locale $Locale
     * @return mixed
     */
    public function getDate($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        $date      = $this->Invoice->getAttribute('date');
        $Formatter = $Locale->getDateFormatter();

        return $Formatter->format(\strtotime($date));
    }

    /**
     * @return string
     */
    public function getDownloadLink()
    {
        return QUI\ERP\Output\Output::getDocumentPdfDownloadUrl($this->getHash(), $this->getOutputType());
    }

    /**
     * @return array
     */
    public function getPaidStatusInformation()
    {
        return $this->Invoice->getPaidStatusInformation();
    }

    /**
     * @return Payment
     */
    public function getPayment()
    {
        return $this->Invoice->getPayment();
    }

    /**
     * @return Invoice|InvoiceTemporary
     */
    public function getInvoice()
    {
        return $this->Invoice;
    }

    /**
     * @return mixed
     */
    public function isPaid()
    {
        return $this->Invoice->isPaid();
    }

    /**
     * Return preview HTML
     * Like HTML or PDF with extra stylesheets to preview the view in DIN A4
     *
     * @return string
     */
    public function previewHTML()
    {
        try {
            $previewHtml = ERPOutput::getDocumentHtml($this->getId(), $this->getOutputType(), null, null, null, true);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            $previewHtml = '';
        }

        QUI::getLocale()->resetCurrent();

        return $previewHtml;
    }

    /**
     * return string
     */
    public function previewOnlyArticles()
    {
        try {
            $output = '';
            $output .= '<style>';
            $output .= \file_get_contents(\dirname(__FILE__).'/Utils/Template.Articles.Preview.css');
            $output .= '</style>';
            $output .= $this->getArticles()->toHTML();

            $previewHtml = $output;
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            $previewHtml = '';
        }

        QUI::getLocale()->resetCurrent();

        return $previewHtml;
    }

    /**
     * Output the invoice as HTML
     *
     * @return string
     */
    public function toHTML()
    {
        try {
            return QUI\ERP\Output\Output::getDocumentHtml($this->Invoice->getId(), $this->getOutputType());
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        return '';
    }

    /**
     * Output the invoice as PDF Document
     *
     * @return QUI\HtmlToPdf\Document
     *
     * @throws QUI\Exception
     */
    public function toPDF()
    {
        return QUI\ERP\Output\Output::getDocumentPdf($this->Invoice->getId(), $this->getOutputType());
    }

    /**
     * Get text descriping the transaction
     *
     * This may be a payment date or a "successfull payment" text, depening on the
     * existing invoice transactions.
     *
     * @return string
     */
    public function getTransactionText()
    {
        // Posted invoice
        if ($this->Invoice instanceof Invoice) {
            return $this->Invoice->getAttribute('transaction_invoice_text') ?: '';
        }

        try {
            $Locale = QUI::getLocale();

            if ($this->Invoice->getCustomer()) {
                $Locale = $this->Invoice->getCustomer()->getLocale();
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return '';
        }

        // Temporary invoice (draft)
        $Transactions = QUI\ERP\Accounting\Payments\Transactions\Handler::getInstance();
        $transactions = $Transactions->getTransactionsByHash($this->Invoice->getHash());

        if (empty($transactions)) {
            // Time for payment text
            $Formatter      = $Locale->getDateFormatter();
            $timeForPayment = $this->Invoice->getAttribute('time_for_payment');

            // temporary invoice, the time for payment are days
            if ($this->Invoice instanceof QUI\ERP\Accounting\Invoice\InvoiceTemporary) {
                $timeForPayment = (int)$timeForPayment;

                if ($timeForPayment < 0) {
                    $timeForPayment = 0;
                }

                if ($timeForPayment) {
                    $timeForPayment = \strtotime('+'.$timeForPayment.' day');
                    $timeForPayment = $Formatter->format($timeForPayment);
                } else {
                    $timeForPayment = $Locale->get('quiqqer/invoice', 'additional.invoice.text.timeForPayment.0');
                }
            } else {
                $timeForPayment = \strtotime($timeForPayment);

                if (\date('Y-m-d') === \date('Y-m-d', $timeForPayment)) {
                    $timeForPayment = $Locale->get('quiqqer/invoice', 'additional.invoice.text.timeForPayment.0');
                } else {
                    $timeForPayment = $Formatter->format($timeForPayment);
                }
            }

            return $Locale->get(
                'quiqqer/invoice',
                'additional.invoice.text.timeForPayment',
                [
                    'timeForPayment' => $timeForPayment
                ]
            );
        }

        /* @var $Transaction QUI\ERP\Accounting\Payments\Transactions\Transaction */
        $Transaction = \array_pop($transactions);
        $Payment     = $Transaction->getPayment(); // payment method
        $PaymentType = $this->getPayment(); // payment method

        $payment   = $Payment->getTitle();
        $Formatter = $Locale->getDateFormatter();

        if ($PaymentType->getPaymentType() === $Payment->getClass()) {
            $payment = $PaymentType->getTitle($Locale);
        }

        return $Locale->get('quiqqer/invoice', 'invoice.view.payment.transaction.text', [
            'date'    => $Formatter->format(\strtotime($Transaction->getDate())),
            'payment' => $payment
        ]);
    }

    /**
     * Get type string used for Invoice output (i.e. PDF / print)
     *
     * @return string
     */
    protected function getOutputType()
    {
        switch ($this->Invoice->getInvoiceType()) {
            case Handler::TYPE_INVOICE_CREDIT_NOTE:
                return 'CreditNote';
                break;

            case Handler::TYPE_INVOICE_CANCEL:
                return 'Canceled';
                break;

            default:
                return 'Invoice';
        }
    }
}
