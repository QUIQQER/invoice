<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\InvoiceView
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;
use QUI\ERP\Accounting\ArticleListUnique;
use QUI\ERP\Output\Output as ERPOutput;

use function array_pop;
use function date;
use function dirname;
use function file_get_contents;
use function strtotime;

/**
 * Class InvoiceView
 * View for an invoice
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class InvoiceView extends QUI\QDOM
{
    /**
     * @var Invoice|InvoiceTemporary
     */
    protected InvoiceTemporary|Invoice $Invoice;

    /**
     * InvoiceView constructor.
     *
     * @param Invoice|InvoiceTemporary $Invoice
     *
     * @throws Exception
     */
    public function __construct(Invoice|InvoiceTemporary $Invoice)
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
    public function getArticles(): QUI\ERP\Accounting\ArticleList|QUI\ERP\Accounting\ArticleListUnique
    {
        try {
            return $this->Invoice->getArticles();
        } catch (\Exception) {
            return new ArticleListUnique();
        }
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->Invoice->getPrefixedNumber();
    }

    /**
     * @return string
     */
    public function getHash(): string
    {
        return $this->Invoice->getUUID();
    }

    /**
     * @return QUI\ERP\Currency\Currency
     */
    public function getCurrency(): QUI\ERP\Currency\Currency
    {
        try {
            return $this->Invoice->getCurrency();
        } catch (QUI\Exception) {
            return QUI\ERP\Defaults::getCurrency();
        }
    }

    /**
     * @return false|null|QUI\ERP\User|QUI\Users\Nobody|QUI\Users\SystemUser|QUI\Users\User
     */
    public function getCustomer(): bool|QUI\Users\SystemUser|QUI\Users\Nobody|QUI\ERP\User|QUI\Users\User|null
    {
        try {
            return $this->Invoice->getCustomer();
        } catch (QUI\Exception) {
            return null;
        }
    }

    /**
     * @param null|QUI\Locale $Locale
     * @return string|bool
     */
    public function getDate(QUI\Locale $Locale = null): string|bool
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        $date = $this->Invoice->getAttribute('date');
        $Formatter = $Locale->getDateFormatter();

        return $Formatter->format(strtotime($date));
    }

    /**
     * @param $dateString
     * @param null $Locale
     * @return false|string
     */
    public function formatDate($dateString, $Locale = null): bool|string
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->getDateFormatter()->format(strtotime($dateString));
    }

    /**
     * @return string
     */
    public function getDownloadLink(): string
    {
        return QUI\ERP\Output\Output::getDocumentPdfDownloadUrl($this->getHash(), $this->getOutputType());
    }

    /**
     * @return array
     */
    public function getPaidStatusInformation(): array
    {
        try {
            return $this->Invoice->getPaidStatusInformation();
        } catch (\Exception) {
            return [];
        }
    }

    /**
     * @return Payment
     */
    public function getPayment(): Payment
    {
        return $this->Invoice->getPayment();
    }

    /**
     * @return Invoice|InvoiceTemporary
     */
    public function getInvoice(): Invoice|InvoiceTemporary
    {
        return $this->Invoice;
    }

    /**
     * @return mixed
     */
    public function isPaid(): mixed
    {
        return $this->Invoice->isPaid();
    }

    /**
     * Return preview HTML
     * Like HTML or PDF with extra stylesheets to preview the view in DIN A4
     *
     * @return string
     */
    public function previewHTML(): string
    {
        try {
            $previewHtml = ERPOutput::getDocumentHtml(
                $this->Invoice->getUUID(),
                $this->getOutputType(),
                null,
                null,
                null,
                true
            );
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
    public function previewOnlyArticles(): string
    {
        try {
            $output = '<style>';
            $output .= file_get_contents(dirname(__FILE__) . '/Utils/Template.Articles.Preview.css');
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
    public function toHTML(): string
    {
        try {
            return QUI\ERP\Output\Output::getDocumentHtml($this->Invoice->getCleanId(), $this->getOutputType());
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
    public function toPDF(): QUI\HtmlToPdf\Document
    {
        return QUI\ERP\Output\Output::getDocumentPdf($this->Invoice->getCleanId(), $this->getOutputType());
    }

    /**
     * Get text descriping the transaction
     *
     * This may be a payment date or a "successfull payment" text, depening on the
     * existing invoice transactions.
     *
     * @return string
     */
    public function getTransactionText(): string
    {
        // Posted invoice
        if ($this->Invoice instanceof Invoice) {
            return $this->Invoice->getAttribute('transaction_invoice_text') ?: '';
        }

        if (
            class_exists('QUI\ERP\Accounting\Payments\Methods\AdvancePayment\Payment')
            && $this->Invoice->getPayment()->getPaymentType(
            ) === QUI\ERP\Accounting\Payments\Methods\AdvancePayment\Payment::class
        ) {
            return '';
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
        $transactions = $Transactions->getTransactionsByHash($this->Invoice->getUUID());

        if (empty($transactions)) {
            // Time for payment text
            $Formatter = $Locale->getDateFormatter();
            $timeForPayment = $this->Invoice->getAttribute('time_for_payment');

            // temporary invoice, the time for payment are days
            if ($this->Invoice instanceof QUI\ERP\Accounting\Invoice\InvoiceTemporary) {
                $timeForPayment = (int)$timeForPayment;

                if ($timeForPayment < 0) {
                    $timeForPayment = 0;
                }

                if ($timeForPayment) {
                    $timeForPayment = strtotime('+' . $timeForPayment . ' day');
                    $timeForPayment = $Formatter->format($timeForPayment);
                } else {
                    $timeForPayment = $Locale->get('quiqqer/invoice', 'additional.invoice.text.timeForPayment.0');
                }
            } else {
                $timeForPayment = strtotime($timeForPayment);

                if (date('Y-m-d') === date('Y-m-d', $timeForPayment)) {
                    $timeForPayment = $Locale->get('quiqqer/invoice', 'additional.invoice.text.timeForPayment.0');
                } else {
                    $timeForPayment = $Formatter->format($timeForPayment);
                }
            }

            return $Locale->get(
                'quiqqer/invoice',
                'additional.invoice.text.timeForPayment.inclVar',
                [
                    'timeForPayment' => $timeForPayment
                ]
            );
        }

        /* @var $Transaction QUI\ERP\Accounting\Payments\Transactions\Transaction */
        $Transaction = array_pop($transactions);
        $Payment = $Transaction->getPayment(); // payment method
        $PaymentType = $this->getPayment(); // payment method

        $payment = $Payment->getTitle();
        $Formatter = $Locale->getDateFormatter();

        if ($PaymentType->getPaymentType() === $Payment->getClass()) {
            $payment = $PaymentType->getTitle($Locale);
        }

        return $Locale->get('quiqqer/invoice', 'invoice.view.payment.transaction.text', [
            'date' => $Formatter->format(strtotime($Transaction->getDate())),
            'payment' => $payment
        ]);
    }

    /**
     * Get type string used for Invoice output (i.e. PDF / print)
     *
     * @return string
     */
    protected function getOutputType(): string
    {
        return match ($this->Invoice->getInvoiceType()) {
            QUI\ERP\Constants::TYPE_INVOICE_CREDIT_NOTE => 'CreditNote',
            QUI\ERP\Constants::TYPE_INVOICE_CANCEL, QUI\ERP\Constants::TYPE_INVOICE_REVERSAL => 'Canceled',
            default => 'Invoice',
        };
    }

    /**
     * @return bool
     */
    public function isDraft(): bool
    {
        return $this->Invoice instanceof InvoiceTemporary;
    }
}
