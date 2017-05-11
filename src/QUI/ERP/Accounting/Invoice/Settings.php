<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Settings
 */

namespace QUI\ERP\Accounting\Invoice;

use function DusanKasan\Knapsack\first;
use QUI;
use QUI\Utils\Singleton;

class Settings extends Singleton
{
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

        $template = $Config->getValue('invoice', 'quiqqer/invoice-accounting-template');

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
