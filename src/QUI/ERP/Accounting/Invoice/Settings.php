<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Settings
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;
use QUI\Utils\Singleton;

use function reset;
use function PHP81_BC\strftime;

/**
 * Class Settings
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class Settings extends Singleton
{
    /**
     * @var ?string
     */
    protected ?string $invoicePrefix = null;

    /**
     * @var ?string
     */
    protected ?string $temporaryInvoicePrefix = null;

    /**
     * @var array
     */
    protected array $settings = [];

    /**
     * Settings constructor.
     */
    public function __construct()
    {
        try {
            $Config = QUI::getPackage('quiqqer/invoice')->getConfig();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);

            return;
        }

        $this->settings = $Config->toArray();
    }

    /**
     * Return the setting
     *
     * @param string $section
     * @param string $key
     *
     * @return mixed
     */
    public function get(string $section, string $key): mixed
    {
        if (isset($this->settings[$section][$key])) {
            return $this->settings[$section][$key];
        }

        return false;
    }

    /**
     * Return the setting
     *
     * @param string $section
     * @param string $key
     * @param mixed $value
     */
    public function set(string $section, string $key, mixed $value): void
    {
        $this->settings[$section][$key] = $value;
    }

    //region easier queries

    /**
     * Should mails be sent when an invoice is created?
     *
     * @return bool
     */
    public function sendMailAtInvoiceCreation(): bool
    {
        if (!isset($this->settings['invoice']['sendMailAtCreation'])) {
            return false;
        }

        return !!$this->settings['invoice']['sendMailAtCreation'];
    }

    //endregion

    //region getter

    /**
     * Return the invoice prefix
     * eq: PREFIX-10022 (default = INV-)
     *
     * @return string
     */
    public function getInvoicePrefix(): string
    {
        if ($this->invoicePrefix !== null) {
            return strftime($this->invoicePrefix);
        }

        try {
            $Package = QUI::getPackage('quiqqer/invoice');
            $Config = $Package->getConfig();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return 'INV-';
        }

        $setting = $Config->getValue('invoice', 'prefix');

        $this->invoicePrefix = 'INV-';

        if (!empty($setting)) {
            $this->invoicePrefix = $setting;
        }

        return strftime($this->invoicePrefix);
    }

    /**
     * Return the temporary invoice prefix
     * eq: PREFIX-10022 (default = EDIT-)
     *
     * @return string
     */
    public function getTemporaryInvoicePrefix(): string
    {
        if ($this->temporaryInvoicePrefix !== null) {
            return strftime($this->temporaryInvoicePrefix);
        }

        try {
            $Package = QUI::getPackage('quiqqer/invoice');
            $Config = $Package->getConfig();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return 'EDIT-';
        }

        $setting = $Config->getValue('temporaryInvoice', 'prefix');

        $this->temporaryInvoicePrefix = 'EDIT-';

        if (!empty($setting)) {
            $this->temporaryInvoicePrefix = $setting;
        }

        return strftime($this->temporaryInvoicePrefix);
    }

    /**
     * Return all available invoice templates
     *
     * @return array
     *
     * @throws QUI\Exception
     */
    public function getAvailableTemplates(): array
    {
        $result = [];
        $packages = QUI::getPackageManager()->getInstalled();
        $default = Settings::get('invoice', 'template');

        $defaultIsDisabled = Settings::get('invoice', 'deactivateDefaultTemplate');

        foreach ($packages as $package) {
            $Package = QUI::getPackage($package['name']);
            $composer = $Package->getComposerData();

            if ($defaultIsDisabled && $Package->getName() === 'quiqqer/invoice-accounting-template') {
                continue;
            }

            if (!isset($composer['type'])) {
                continue;
            }

            if ($composer['type'] !== 'quiqqer-invoice-template') {
                continue;
            }

            $result[] = [
                'name' => $Package->getName(),
                'title' => $Package->getTitle(),
                'default' => $Package->getName() === $default ? 1 : 0
            ];
        }

        return $result;
    }

    /**
     * Return the default invoice template
     *
     * @return string
     *
     * @throws QUI\Exception
     */
    public function getDefaultTemplate(): string
    {
        $Package = QUI::getPackage('quiqqer/invoice');
        $Config = $Package->getConfig();

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

    /**
     * Check if EPC QR code shall be included.
     *
     * @return bool
     */
    public function isIncludeQrCode(): bool
    {
        try {
            $Config = QUI::getPackage('quiqqer/invoice')->getConfig();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return false;
        }

        return !empty($Config->getValue('invoice', 'includeQrCode'));
    }

    //endregion
}
