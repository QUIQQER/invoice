<?php

namespace QUI\ERP\Accounting\Invoice\Output;

use QUI;
use QUI\ERP\Output\OutputProviderInterface;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;
use QUI\ERP\Output\OutputTemplate;

/**
 * Class OutputProvider
 *
 * Output provider for quiqqer/invoice:
 *
 * Outputs previews and PDF files for invoices
 */
class OutputProviderInvoice implements OutputProviderInterface
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
        return 'Invoice';
    }

    /**
     * Fill the OutputTemplate with appropriate entity data
     *
     * @param string|int $entityId
     * @param OutputTemplate $Template
     * @return array
     */
    public static function getTemplateData($entityId, OutputTemplate $Template)
    {
        $Invoice     = InvoiceUtils::getInvoiceByString($entityId);
        $InvoiceView = $Invoice->getView();
        $Customer    = $Invoice->getCustomer();

        if (!$Customer) {
            $Customer = new QUI\ERP\User([
                'id'        => '',
                'country'   => '',
                'username'  => '',
                'firstname' => '',
                'lastname'  => '',
                'lang'      => '',
                'isCompany' => '',
                'isNetto'   => ''
            ]);
        }

        $Editor    = $Invoice->getEditor();
        $addressId = $Invoice->getAttribute('invoice_address_id');
        $address   = [];

        if ($Invoice->getAttribute('invoice_address')) {
            $address = \json_decode($Invoice->getAttribute('invoice_address'), true);
        }

        $Engine = $Template->getEngine();
        $Locale = $Customer->getLocale();

//        $this->setAttributes($Invoice->getAttributes());

        if (!empty($address)) {
            $Address = new QUI\ERP\Address($address);
            $Address->clearMail();
            $Address->clearPhone();
        } else {
            try {
                $Address = $Customer->getAddress($addressId);
                $Address->clearMail();
                $Address->clearPhone();
            } catch (QUI\Exception $Exception) {
                $Address = null;
            }
        }

        // list calculation
        $Articles = $Invoice->getArticles();

        if (\get_class($Articles) !== QUI\ERP\Accounting\ArticleListUnique::class) {
            $Articles->setUser($Customer);
            $Articles = $Articles->toUniqueList();
        }

        // Delivery address
        $DeliveryAddress = false;
        $deliveryAddress = $Invoice->getAttribute('delivery_address');

        if (!empty($deliveryAddress)) {
            $DeliveryAddress = new QUI\ERP\Address(\json_decode($deliveryAddress, true));
            $DeliveryAddress->clearMail();
            $DeliveryAddress->clearPhone();
        }

        QUI::getLocale()->setTemporaryCurrent($Customer->getLang());

        $InvoiceView->setAttributes($Invoice->getAttributes());

        return [
            'this'            => $InvoiceView,
            'ArticleList'     => $Articles,
            'Customer'        => $Customer,
            'Editor'          => $Editor,
            'Address'         => $Address,
            'DeliveryAddress' => $DeliveryAddress,
            'Payment'         => $Invoice->getPayment(),
            'transaction'     => $InvoiceView->getTransactionText(),
            'projectName'     => $Invoice->getAttribute('project_name'),
            'useShipping'     => QUI::getPackageManager()->isInstalled('quiqqer/shipping')
        ];
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
