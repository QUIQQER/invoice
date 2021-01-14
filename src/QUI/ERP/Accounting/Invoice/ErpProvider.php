<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\ErpProvider
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;
use QUI\Controls\Sitemap\Map;
use QUI\Controls\Sitemap\Item;
use QUI\ERP\Api\AbstractErpProvider;

/**
 * Class ErpProvider
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class ErpProvider extends AbstractErpProvider
{
    /**
     * @param \QUI\Controls\Sitemap\Map $Map
     */
    public static function addMenuItems(Map $Map)
    {
        $Accounting = $Map->getChildrenByName('accounting');

        if ($Accounting === null) {
            $Accounting = new Item([
                'icon'     => 'fa fa-book',
                'name'     => 'accounting',
                'text'     => ['quiqqer/invoice', 'erp.panel.accounting.text'],
                'opened'   => true,
                'priority' => 1
            ]);

            $Map->appendChild($Accounting);
        }

        $Invoice = new Item([
            'icon'     => 'fa fa-file-text-o',
            'name'     => 'invoice',
            'text'     => ['quiqqer/invoice', 'erp.panel.invoice.text'],
            'opened'   => true,
            'priority' => 2
        ]);

        $Invoice->appendChild(
            new Item([
                'icon'    => 'fa fa-plus',
                'name'    => 'invoice-create',
                'text'    => ['quiqqer/invoice', 'erp.panel.invoice.create.text'],
                'require' => 'package/quiqqer/invoice/bin/backend/utils/ErpMenuInvoiceCreate'
            ])
        );

        $Invoice->appendChild(
            new Item([
                'icon'    => 'fa fa-file-text-o',
                'name'    => 'invoice-drafts',
                'text'    => ['quiqqer/invoice', 'erp.panel.invoice.drafts.text'],
                'require' => 'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoices'
            ])
        );

        $Invoice->appendChild(
            new Item([
                'icon'    => 'fa fa-file-text-o',
                'name'    => 'invoice-journal',
                'text'    => ['quiqqer/invoice', 'erp.panel.invoice.journal.text'],
                'require' => 'package/quiqqer/invoice/bin/backend/controls/panels/Journal'
            ])
        );

        $Accounting->appendChild($Invoice);
    }

    /**
     * @return array
     */
    public static function getNumberRanges()
    {
        return [
            new NumberRanges\Invoice(),
            new NumberRanges\TemporaryInvoice()
        ];
    }

    /**
     * @return array[]
     */
    public static function getMailLocale(): array
    {
        return [
            [
                'title'       => QUI::getLocale()->get('quiqqer/invoice', 'invoice.send.mail.title'),
                'description' => QUI::getLocale()->get('quiqqer/invoice', 'invoice.send.mail.description'),
                'subject'     => ['quiqqer/invoice', 'invoice.send.mail.subject'],
                'content'     => ['quiqqer/invoice', 'invoice.send.mail.message'],

                'subject.description' => ['quiqqer/invoice', 'invoice.send.mail.subject.description'],
                'content.description' => ['quiqqer/invoice', 'invoice.send.mail.message.description']
            ],

            [
                'title'       => QUI::getLocale()->get('quiqqer/invoice', 'invoice.cancelled.title'),
                'description' => QUI::getLocale()->get('quiqqer/invoice', 'invoice.cancelled.description'),
                'subject'     => ['quiqqer/invoice', 'invoice.cancelled.send.mail.subject'],
                'content'     => ['quiqqer/invoice', 'invoice.cancelled.send.mail.message'],

                'subject.description' => ['quiqqer/invoice', 'invoice.cancelled.subject.description'],
                'content.description' => ['quiqqer/invoice', 'invoice.cancelled.message.description']
            ],

            [
                'title'       => QUI::getLocale()->get('quiqqer/invoice', 'invoice.credit_note.title'),
                'description' => QUI::getLocale()->get('quiqqer/invoice', 'invoice.credit_note.description'),
                'subject'     => ['quiqqer/invoice', 'invoice.credit_note.send.mail.subject'],
                'content'     => ['quiqqer/invoice', 'invoice.credit_note.send.mail.message'],

                'subject.description' => ['quiqqer/invoice', 'invoice.credit_note.subject.description'],
                'content.description' => ['quiqqer/invoice', 'invoice.credit_note.message.description']
            ]
        ];
    }
}
