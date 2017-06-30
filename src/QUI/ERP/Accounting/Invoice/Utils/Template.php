<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Utils\Template
 */

namespace QUI\ERP\Accounting\Invoice\Utils;

use QUI;
use QUI\Package\Package;

use QUI\ERP\Accounting\Invoice\Exception;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;

/**
 * Class Template
 *
 * @package QUI\ERP\Utils
 */
class Template
{
    /**
     * @var Package
     */
    protected $Template;

    /**
     * @var QUI\Interfaces\Template\EngineInterface
     */
    protected $Engine;

    /**
     * @var Invoice|InvoiceTemporary
     */
    protected $Invoice;

    /**
     * Template constructor.
     *
     * @param Package $Template - Template package
     * @param InvoiceTemporary|Invoice $Invoice
     *
     * @throws Exception
     */
    public function __construct(Package $Template, $Invoice)
    {
        if (!($Invoice instanceof Invoice)
            && !($Invoice instanceof InvoiceTemporary)
        ) {
            throw new Exception('$Invoice must be an instance of InvoiceTemporary or Invoice');
        }

        $this->Engine   = QUI::getTemplateManager()->getEngine();
        $this->Template = $Template;
        $this->Invoice  = $Invoice;
    }

    /**
     * Render the html
     *
     * @return string
     */
    public function render()
    {
        return $this->getHTMLHeader() .
               $this->getHTMLBody() .
               $this->getHTMLFooter();
    }

    /**
     * Render the preview html
     *
     * @return string
     */
    public function renderPreview()
    {
        $output = '';
        $output .= '<style>';
        $output .= file_get_contents(dirname(__FILE__) . '/Template.Preview.css');
        $output .= '</style>';
        $output .= $this->render();

        return $output;
    }

    /**
     * @return QUI\Interfaces\Template\EngineInterface
     */
    public function getEngine()
    {
        return $this->Engine;
    }

    //region Template Output Helper

    /**
     * Return the properly template
     * @return string
     */
    protected function getTemplateType()
    {
        if ($this->Invoice->getInvoiceType() === Handler::TYPE_INVOICE_CREDIT_NOTE) {
            return 'CreditNote';
        }

        if ($this->Invoice->getInvoiceType() === Handler::TYPE_INVOICE_REVERSAL) {
            return 'Canceled';
        }

        return 'Invoice';
    }

    /**
     * Return the html header
     *
     * @return string
     */
    public function getHTMLHeader()
    {
        return $this->getTemplate('header');
    }

    /**
     * Return the html body
     *
     * @return string
     */
    public function getHTMLBody()
    {
        return $this->getTemplate('body');
    }

    /**
     * Return the html body
     *
     * @return string
     */
    public function getHTMLFooter()
    {
        return $this->getTemplate('footer');
    }

    /**
     * Helper for template check
     *
     * @param $template
     * @return string
     */
    protected function getTemplate($template)
    {
        // main folder
        $htmlFile = $this->getFile($template . '.html');
        $cssFile  = $this->getFile($template . '.css');

        $Output = new QUI\Output();
        $Output->setSetting('use-system-image-paths', true);

        $output = '';

        if (file_exists($cssFile)) {
            $output .= '<style>' . file_get_contents($cssFile) . '</style>';
        }

        if (file_exists($htmlFile)) {
            $output .= $this->getEngine()->fetch($htmlFile);
        }

        return $Output->parse($output);
    }

    /**
     * Return file
     * Checks some paths
     *
     * @param $wanted
     * @return string
     * @throws QUI\ERP\Exception
     */
    public function getFile($wanted)
    {
        $package = $this->Template->getName();
        $usrPath = USR_DIR . $package . '/template/';
        $type    = $this->getTemplateType();

        if (file_exists($usrPath . $type . '/' . $wanted)) {
            return $usrPath . $type . '/' . $wanted;
        }

        if (file_exists($usrPath . $wanted)) {
            return $usrPath . $wanted;
        }


        $optPath = OPT_DIR . $package . '/template/';

        if (file_exists($optPath . $type . '/' . $wanted)) {
            return $optPath . $type . '/' . $wanted;
        }

        if (file_exists($optPath . $wanted)) {
            return $optPath . $wanted;
        }


        QUI\System\Log::addWarning('File not found in ERP Template ' . $wanted);
        return '';
    }

    //endregion
}
