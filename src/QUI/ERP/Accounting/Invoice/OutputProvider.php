<?php

namespace QUI\ERP\Accounting\Invoice;

use QUI;
use QUI\ERP\Output\OutputProviderInterface;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

/**
 * Class OutputProvider
 *
 * Output provider for quiqqer/invoice:
 *
 * Outputs previews and PDF files for invoices and temporary invoices
 */
class OutputProvider implements OutputProviderInterface
{
    /**
     * Get preview HTML of an entity output
     *
     * @param string|int $entityId
     * @param string $template
     * @return string - Preview HTML
     *
     * @throws QUI\Exception
     */
    public static function getPreview($entityId, string $template)
    {
        $Invoice = InvoiceUtils::getInvoiceByString($entityId);
        return $Invoice->getView()->previewHTML();
    }

    /**
     * Get PDF file of an entity output
     *
     * @param string|int $entityId
     * @param string $template
     * @return QUI\HtmlToPdf\Document
     *
     * @throws QUI\Exception
     */
    public static function getPDFDocument($entityId, string $template)
    {
        $Invoice = InvoiceUtils::getInvoiceByString($entityId);
        $View    = $Invoice->getView();
        $View->setAttribute('template', $template);

        return $View->toPDF();
    }

    /**
     * Get e-mail address of the document recipient
     *
     * @param string|int $entityId
     * @return string|false - E-Mail address or false if no e-mail address available
     *
     * @throws QUI\Exception
     */
    public static function getEmailAddress($entityId)
    {
        $Invoice  = InvoiceUtils::getInvoiceByString($entityId);
        $Customer = $Invoice->getCustomer();

        if (empty($Customer)) {
            return false;
        }

        $email = $Customer->getAttribute('email');

        return $email ?: false;
    }
}
