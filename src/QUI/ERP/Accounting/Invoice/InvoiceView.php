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
        if ($Invoice instanceof Invoice
            || $Invoice instanceof InvoiceTemporary
        ) {
            $this->Invoice = $Invoice;

            return;
        }

        throw new Exception('$Invoice must be an instance of Invoice or InvoiceTemporary');
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
        $Plugin   = QUI::getPackage('quiqqer/invoice');
        $Config   = $Plugin->getConfig();
        $fileName = QUI::getLocale()->get('quiqqer/invoice', 'pdf.download.name');

        if ($Config->get('invoiceDownload', 'useDate')) {
            $localeCode = QUI::getLocale()->getLocalesByLang(
                QUI::getLocale()->getCurrent()
            );

            $Formatter = new \IntlDateFormatter(
                $localeCode[0],
                \IntlDateFormatter::SHORT,
                \IntlDateFormatter::NONE
            );

            $cDate = $this->Invoice->getAttribute('date');
            $cDate = strtotime($cDate);

            $date = $Formatter->format($cDate);
            $date = preg_replace('/[^0-9]/', '_', $date);
            $date = trim($date, '_');

            $fileName .= '_';
            $fileName .= $date;
        }

        $fileName .= '.pdf';

        $Document = new QUI\HtmlToPdf\Document([
            'marginTop'    => 30, // dies muss variabel sein
            'filename'     => $fileName,
            'marginBottom' => 80 // dies muss variabel sein
        ]);

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

        return QUI::getPackage($template);
    }

    /**
     * @return Utils\Template
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    protected function getTemplate()
    {
        $Template = new Utils\Template(
            $this->getTemplatePackage(),
            $this->Invoice
        );

        $Engine    = $Template->getEngine();
        $Customer  = $this->Invoice->getCustomer();
        $Editor    = $this->Invoice->getEditor();
        $addressId = $this->Invoice->getAttribute('invoice_address_id');

        $this->setAttributes($this->Invoice->getAttributes());

        try {
            $Address = $Customer->getAddress($addressId);
        } catch (QUI\Exception $Exception) {
            $Address = null;
        }

        // list calculation
        $Articles = $this->Invoice->getArticles();

        if (get_class($Articles) !== QUI\ERP\Accounting\ArticleListUnique::class) {
            $Articles->setUser($Customer);
            $Articles = $Articles->toUniqueList();
        }

        $Engine->assign([
            'this'        => $this,
            'ArticleList' => $Articles,
            'Customer'    => $Customer,
            'Editor'      => $Editor,
            'Address'     => $Address
        ]);

        return $Template;
    }
}
