<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;

/**
 * Class InvoiceView
 * View for a invoice
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class InvoiceView extends QUI\QDOM
{
    /**
     * @var Invoice|TemporaryInvoice
     */
    protected $Invoice;

    /**
     * InvoiceView constructor.
     *
     * @param Invoice|TemporaryInvoice $Invoice
     *
     * @throws Exception
     */
    public function __construct($Invoice)
    {
        if ($Invoice instanceof Invoice
            || $Invoice instanceof TemporaryInvoice
        ) {
            $this->Invoice = $Invoice;
            return;
        }

        throw new Exception('$Invoice must be an instance of Invoice or TemporaryInvoice');
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->Invoice->getId();
    }

    /**
     * Return preview HTML
     * Like HTML or PDF with extra stylesheets to preview the view in DIN A4
     *
     * @return string
     */
    public function previewHTML()
    {
        $output = '';

        $output .= '<style>';
        $output .= file_get_contents(dirname(__FILE__) . '/InvoiceView.HTML.Preview.css');
        $output .= '</style>';
        $output .= $this->getHTMLHeader();
        $output .= $this->getHTMLBody();
        $output .= $this->getHTMLFooter();

        return $output;
    }

    /**
     * Output the invoice as HTML
     *
     * @return string
     */
    public function toHTML()
    {
        return $this->getHTMLHeader() .
               $this->getHTMLBody() .
               $this->getHTMLFooter();
    }

    /**
     * Output the invoice as PDF Document
     *
     * @return QUI\HtmlToPdf\Document
     */
    public function toPDF()
    {
        $localeCode = QUI::getLocale()->getLocalesByLang(
            QUI::getLocale()->getCurrent()
        );

        $Formatter = new \IntlDateFormatter(
            $localeCode[0],
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::NONE
        );

        $date = $Formatter->format(time());
        $date = preg_replace('/[^0-9]/', '_', $date);
        $date = trim($date, '_');

        $fileName = QUI::getLocale()->get('quiqqer/invoice', 'pdf.export.name') . '_';
        $fileName .= $date;
        $fileName .= '.pdf';

        $Document = new QUI\HtmlToPdf\Document(array(
            'marginTop'    => 30, // dies muss variabel sein
            'filename'     => $fileName,
            'marginBottom' => 80 // dies muss variabel sein
        ));

        try {
            $Document->setHeaderHTML($this->getHTMLHeader());
            $Document->setContentHTML($this->getHTMLBody());
            $Document->setFooterHTML($this->getHTMLFooter());
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        return $Document;
    }


    //region Template Output Helper

    /**
     * @return QUI\Interfaces\Template\EngineInterface
     */
    protected function getHTMLEngine()
    {
        $Engine = QUI::getTemplateManager()->getEngine();

        $customerId = $this->Invoice->getAttribute('customer_id');
        $addressId  = $this->Invoice->getAttribute('invoice_address_id');

        $Customer = QUI::getUsers()->get($customerId);

        $this->setAttributes($this->Invoice->getAttributes());

        try {
            $Address = $Customer->getAddress($addressId);
        } catch (QUI\Exception $Exception) {
            $Address = null;
        }


        // list calculation
        $Calc     = new QUI\ERP\Accounting\Calc($Customer);
        $Articles = $this->Invoice->getArticles();

        $Articles->calc($Calc);

        $Engine->assign(array(
            'this'        => $this,
            'ArticleList' => $Articles,
            'Customer'    => $Customer,
            'Address'     => $Address
        ));

        return $Engine;
    }

    /**
     * Return the html header
     *
     * @return string
     */
    protected function getHTMLHeader()
    {
        $Engine = $this->getHTMLEngine();
        $path   = QUI::getPackage('quiqqer/invoice')->getDir();

        return $Engine->fetch($path . '/template/header.html');
    }

    /**
     * Return the html body
     *
     * @return string
     */
    protected function getHTMLBody()
    {
        $Engine = $this->getHTMLEngine();
        $path   = QUI::getPackage('quiqqer/invoice')->getDir();

        return $Engine->fetch($path . '/template/body.html');
    }

    /**
     * Return the html body
     *
     * @return string
     */
    protected function getHTMLFooter()
    {
        $Engine = $this->getHTMLEngine();
        $path   = QUI::getPackage('quiqqer/invoice')->getDir();

        return $Engine->fetch($path . '/template/footer.html');
    }

    //endregion
}
