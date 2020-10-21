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
        $Invoice = self::getEntity($entityId);

        return $Invoice->getCustomer()->getLocale()->get('quiqqer/invoice', 'invoice.credit_note.send.mail.subject', [
            'invoiceId' => $Invoice->getId()
        ]);
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

        $user = $Customer->getName();
        $user = \trim($user);

        if (empty($user)) {
            $user = $Customer->getAddress()->getName();
        }

        return $Customer->getLocale()->get('quiqqer/invoice', 'invoice.credit_note.send.mail.message', [
            'invoiceId' => $Invoice->getId(),
            'user'      => $user,
            'address'   => $Customer->getAddress()->render(),
            'company'   => self::getCompanyName()
        ]);
    }
}
