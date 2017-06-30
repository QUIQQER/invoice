<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Settings
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;
use QUI\Utils\Singleton;

/**
 * Class Settings
 * @package QUI\ERP\Accounting\Invoice
 */
class Settings extends Singleton
{
    /**
     * @var string
     */
    protected $invoicePrefix = null;

    /**
     * @var string
     */
    protected $temporaryInvoicePrefix = null;

    /**
     * Return the invoice prefix
     * eq: PREFIX-10022 (default = INV-)
     *
     * @return string
     */
    public function getInvoicePrefix()
    {
        if ($this->invoicePrefix !== null) {
            return $this->invoicePrefix;
        }

        $Package = QUI::getPackage('quiqqer/invoice');
        $Config  = $Package->getConfig();
        $setting = $Config->getValue('invoice', 'prefix');

        $this->invoicePrefix = 'INV-';

        if (!empty($setting)) {
            $this->invoicePrefix = $setting;
        }

        return $this->invoicePrefix;
    }

    /**
     * Return the temporary invoice prefix
     * eq: PREFIX-10022 (default = EDIT-)
     *
     * @return string
     */
    public function getTemporaryInvoicePrefix()
    {
        if ($this->temporaryInvoicePrefix !== null) {
            return $this->temporaryInvoicePrefix;
        }

        $Package = QUI::getPackage('quiqqer/invoice');
        $Config  = $Package->getConfig();
        $setting = $Config->getValue('temporaryInvoice', 'prefix');

        $this->temporaryInvoicePrefix = 'EDIT-';

        if (!empty($setting)) {
            $this->temporaryInvoicePrefix = $setting;
        }

        return $this->temporaryInvoicePrefix;
    }

    /**
     * Return all available invoice templates
     *
     * @return array
     */
    public function getAvailableTemplates()
    {
        $result   = array();
        $packages = QUI::getPackageManager()->getInstalled();

        foreach ($packages as $package) {
            $Package  = QUI::getPackage($package['name']);
            $composer = $Package->getComposerData();

            if (!isset($composer['type'])) {
                continue;
            }

            if ($composer['type'] !== 'quiqqer-invoice-template') {
                continue;
            }

            $result[] = array(
                'name'  => $Package->getName(),
                'title' => $Package->getTitle()
            );
        }

        return $result;
    }

    /**
     * Return the default invoice template
     *
     * @return string
     */
    public function getDefaultTemplate()
    {
        $Package = QUI::getPackage('quiqqer/invoice');
        $Config  = $Package->getConfig();

        $template = $Config->getValue('invoice', 'template');

        if (!empty($template)) {
            return $template;
        }

        $available = $this->getAvailableTemplates();

        if (empty($available)) {
            return '';
        }

        $first = reset($available);

        return $first['name'];
    }
}
