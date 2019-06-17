<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\InvoiceView
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

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
        return URL_OPT_DIR.'quiqqer/invoice/bin/frontend/downloadInvoice.php?hash='.$this->getHash();
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
            $previewHtml = $this->getTemplate()->renderPreview();
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
            return $this->getTemplate()->render();
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
        $Customer = $this->Invoice->getCustomer();

        $Document = new QUI\HtmlToPdf\Document([
            'marginTop'         => 30, // dies ist variabel durch quiqqerInvoicePdfCreate
            'filename'          => InvoiceUtils::getInvoiceFilename($this->Invoice).'.pdf',
            'marginBottom'      => 80,  // dies ist variabel durch quiqqerInvoicePdfCreate,
            'pageNumbersPrefix' => $Customer->getLocale()->get('quiqqer/htmltopdf', 'footer.page.prefix'),
        ]);

        $Template = $this->getTemplate();

        QUI::getEvents()->fireEvent(
            'quiqqerInvoicePdfCreate',
            [$this, $Document, $Template]
        );

        try {
            $Document->setHeaderHTML($Template->getHTMLHeader());
            $Document->setContentHTML($Template->getHTMLBody());
            $Document->setFooterHTML($Template->getHTMLFooter());
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        return $Document;
    }

    /**
     * Return the template package
     *
     * @return QUI\Package\Package
     *
     * @throws QUI\Exception
     */
    protected function getTemplatePackage()
    {
        $template = $this->getAttribute('template');
        $Settings = Settings::getInstance();

        if (empty($template)) {
            $template = $Settings->getDefaultTemplate();
        }

        try {
            return QUI::getPackage($template);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        return QUI::getPackage($Settings->getDefaultTemplate());
    }

    /**
     * @return Utils\Template
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public function getTemplate()
    {
        $Template = new Utils\Template(
            $this->getTemplatePackage(),
            $this->Invoice
        );

        $Customer = $this->Invoice->getCustomer();

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

        $Editor    = $this->Invoice->getEditor();
        $addressId = $this->Invoice->getAttribute('invoice_address_id');
        $address   = [];

        if ($this->Invoice->getAttribute('invoice_address')) {
            $address = \json_decode($this->Invoice->getAttribute('invoice_address'), true);
        }

        $Engine = $Template->getEngine();
        $Locale = $Customer->getLocale();

        $this->setAttributes($this->Invoice->getAttributes());

        if (!empty($address)) {
            $Address = new QUI\ERP\Address($address);
            $Address->clearMail();
            $Address->clearPhone();
        } else {
            try {
                $Address = $Customer->getAddress($addressId);
                $Address->clearMail();
                $Address->clearPhone();
            } catch (QUI\Exception $Exception) {
                $Address = null;
            }
        }

        // list calculation
        $Articles = $this->Invoice->getArticles();

        if (\get_class($Articles) !== QUI\ERP\Accounting\ArticleListUnique::class) {
            $Articles->setUser($Customer);
            $Articles = $Articles->toUniqueList();
        }

        // time for payment
        $Formatter       = $Locale->getDateFormatter();
        $timeForPayment  = $this->Invoice->getAttribute('time_for_payment');
        $transactionText = '';

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

        // get transactions
        $Transactions = QUI\ERP\Accounting\Payments\Transactions\Handler::getInstance();
        $transactions = $Transactions->getTransactionsByHash($this->Invoice->getHash());

        if (!empty($transactions)) {
            /* @var $Transaction QUI\ERP\Accounting\Payments\Transactions\Transaction */
            $Transaction = \array_pop($transactions);
            $Payment     = $Transaction->getPayment(); // payment method
            $PaymentType = $this->getPayment(); // payment method

            $payment   = $Payment->getTitle();
            $Formatter = $Locale->getDateFormatter();

            if ($PaymentType->getPaymentType() === $Payment->getClass()) {
                $payment = $PaymentType->getTitle($Locale);
            }

            $transactionText = $Locale->get('quiqqer/invoice', 'invoice.view.payment.transaction.text', [
                'date'    => $Formatter->format(\strtotime($Transaction->getDate())),
                'payment' => $payment
            ]);
        }

        QUI::getLocale()->setTemporaryCurrent($Customer->getLang());

        $Engine->assign([
            'this'           => $this,
            'ArticleList'    => $Articles,
            'Customer'       => $Customer,
            'Editor'         => $Editor,
            'Address'        => $Address,
            'Payment'        => $this->Invoice->getPayment(),
            'timeForPayment' => $timeForPayment,
            'transaction'    => $transactionText
        ]);

        return $Template;
    }
}
