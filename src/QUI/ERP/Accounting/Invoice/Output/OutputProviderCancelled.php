<?php

namespace QUI\ERP\Accounting\Invoice\Output;

use QUI;
use QUI\Locale;

/**
 * Class OutputProviderCancelled
 *
 * Output provider for quiqqer/invoice:
 *
 * Outputs previews and PDF files for cancelled invoices
 */
class OutputProviderCancelled extends OutputProviderInvoice
{
    /**
     * Get output type
     *
     * The output type determines the type of templates/providers that are used
     * to output documents.
     *
     * @return string
     */
    public static function getEntityType()
    {
        return 'Canceled';
    }

    /**
     * Get title for the output entity
     *
     * @param Locale $Locale (optional) - If ommitted use \QUI::getLocale()
     * @return mixed
     */
    public static function getEntityTypeTitle(Locale $Locale = null)
    {
        if (empty($Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/invoice', 'OutputProvider.entity.title.Canceled');
    }
}
