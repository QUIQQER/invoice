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
        return $this->Invoice->getCurrency();
    }

    /**
     * @return false|null|QUI\ERP\User|QUI\Users\Nobody|QUI\Users\SystemUser|QUI\Users\User
     */
    public function getCustomer()
    {
        return $this->Invoice->getCustomer();
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

        return $Formatter->format(strtotime($date));
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
     * Return preview HTML
     * Like HTML or PDF with extra stylesheets to preview the view in DIN A4
     *
     * @return string
     */
    public function previewHTML()
    {
        try {
            return $this->getTemplate()->renderPreview();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return '';
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
            QUI\System\Log::writeDebugException($Exception);
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

        $Customer  = $this->Invoice->getCustomer();
        $Editor    = $this->Invoice->getEditor();
        $addressId = $this->Invoice->getAttribute('invoice_address_id');

        $Engine = $Template->getEngine();
        $Locale = $Customer->getLocale();

        $this->setAttributes($this->Invoice->getAttributes());

        try {
            $Address = $Customer->getAddress($addressId);
            $Address->clearMail();
            $Address->clearPhone();
        } catch (QUI\Exception $Exception) {
            $Address = null;
        }

        // list calculation
        $Articles = $this->Invoice->getArticles();

        if (get_class($Articles) !== QUI\ERP\Accounting\ArticleListUnique::class) {
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
            $timeForPayment = strtotime('+'.$timeForPayment.' day');
            $timeForPayment = $Formatter->format($timeForPayment);
        } else {
            $timeForPayment = $Formatter->format(strtotime($timeForPayment));
        }

        // get transactions
        $Transactions = QUI\ERP\Accounting\Payments\Transactions\Handler::getInstance();
        $transactions = $Transactions->getTransactionsByHash($this->Invoice->getHash());

        if (!empty($transactions)) {
            /* @var $Transaction QUI\ERP\Accounting\Payments\Transactions\Transaction */
            $Transaction = array_pop($transactions);
            $Payment     = $Transaction->getPayment();
            $Formatter   = $Locale->getDateFormatter();

            $transactionText = $Locale->get('quiqqer/invoice', 'invoice.view.payment.transaction.text', [
                'date'    => $Formatter->format(strtotime($Transaction->getDate())),
                'payment' => $Payment->getTitle()
            ]);
        }

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
