<?php

namespace QUI\ERP\Accounting\Invoice\Output;

use QUI;
use QUI\ERP\Output\OutputProviderInterface;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

/**
 * Class OutputProviderInvoiceTemporary
 *
 * Output provider for quiqqer/invoice:
 *
 * Outputs previews and PDF files for temporary invoices
 */
class OutputProviderInvoiceTemporary extends OutputProviderInvoice
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
        return 'InvoiceTemporary';
    }
}
