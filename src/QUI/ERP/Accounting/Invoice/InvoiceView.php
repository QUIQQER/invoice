<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\InvoiceView
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
        return $this->getTemplate()->renderPreview();
    }

    /**
     * Output the invoice as HTML
     *
     * @return string
     */
    public function toHTML()
    {
        return $this->getTemplate()->render();
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

        $Template = $this->getTemplate();

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
     */
    protected function getTemplatePackage()
    {
        $template = $this->getAttribute('template');
        $Settings = Settings::getInstance();

        if (empty($template)) {
            $template = $Settings->getDefaultTemplate();
        }

        return QUI::getPackage($template);
    }

    /**
     * @return QUI\ERP\Utils\Template
     */
    protected function getTemplate()
    {
        $Template = new QUI\ERP\Utils\Template(
            $this->getTemplatePackage(),
            QUI\ERP\Utils\Template::TYPE_INVOICE
        );

        $Engine   = $Template->getEngine();
        $Customer = $this->Invoice->getCustomer();
        $Editor   = $this->Invoice->getEditor();

        $addressId = $this->Invoice->getAttribute('invoice_address_id');

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
            'Editor'      => $Editor,
            'Address'     => $Address
        ));

        return $Template;
    }
}
