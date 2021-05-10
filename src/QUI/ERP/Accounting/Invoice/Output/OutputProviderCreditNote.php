<?php

namespace QUI\ERP\Accounting\Invoice\Output;

use QUI;
use QUI\Locale;

/**
 * Class OutputProviderCreditNote
 *
 * Output provider for quiqqer/invoice:
 *
 * Outputs previews and PDF files for credit notes
 */
class OutputProviderCreditNote extends OutputProviderInvoice
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
        return 'CreditNote';
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

        return $Locale->get('quiqqer/invoice', 'OutputProvider.entity.title.CreditNote');
    }

    /**
     * Get e-mail subject when document is sent via mail
     *
     * @param string|int $entityId
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getMailSubject($entityId)
    {
        $Invoice  = self::getEntity($entityId);
        $Customer = $Invoice->getCustomer();

        // Additional mail placeholders for cancelled invoice
        $mailVars           = OutputProviderInvoice::getInvoiceLocaleVar($Invoice, $Customer);
        $cancelledInvoiceId = $Invoice->getData('originalIdPrefixed');

        if (empty($cancelledInvoiceId)) {
            $cancelledInvoiceId = $Invoice->getData('originalId');

            try {
                $CancelledInvoice = QUI\ERP\Accounting\Invoice\Handler::getInstance()->get(
                    $cancelledInvoiceId
                );

                $cancelledInvoiceId = $CancelledInvoice->getId();
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        $mailVars['creditedInvoiceId'] = $cancelledInvoiceId;

        return $Invoice->getCustomer()->getLocale()->get(
            'quiqqer/invoice',
            'invoice.credit_note.send.mail.subject',
            $mailVars
        );
    }

    /**
     * Get e-mail body when document is sent via mail
     *
     * @param string|int $entityId
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getMailBody($entityId)
    {
        $Invoice  = self::getEntity($entityId);
        $Customer = $Invoice->getCustomer();

        // Additional mail placeholders for cancelled invoice
        $mailVars           = OutputProviderInvoice::getInvoiceLocaleVar($Invoice, $Customer);
        $cancelledInvoiceId = $Invoice->getData('originalIdPrefixed');

        if (empty($cancelledInvoiceId)) {
            $cancelledInvoiceId = $Invoice->getData('originalId');

            try {
                $CancelledInvoice = QUI\ERP\Accounting\Invoice\Handler::getInstance()->get(
                    $cancelledInvoiceId
                );

                $cancelledInvoiceId = $CancelledInvoice->getId();
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        $mailVars['creditedInvoiceId'] = $cancelledInvoiceId;

        return $Customer->getLocale()->get(
            'quiqqer/invoice',
            'invoice.credit_note.send.mail.message',
            $mailVars
        );
    }
}
