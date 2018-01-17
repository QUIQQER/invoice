<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Factory
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;

/**
 * Class Factory
 * - Creates Temporary invoices
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class Factory extends QUI\Utils\Singleton
{
    /**
     * Creates a new temporary invoice
     *
     * @param QUI\Interfaces\Users\User|null $User
     * @param string|bool $hash - hash of the process
     * @return InvoiceTemporary
     * @throws
     */
    public function createInvoice($User = null, $hash = false)
    {
        if ($User === null) {
            $User = QUI::getUserBySession();
        }

        QUI\Permissions\Permission::checkPermission('quiqqer.invoice.create', $User);

        if ($hash === false) {
            $hash = QUI\Utils\Uuid::get();
        }

        $c_user = $User->getId();

        if (!$c_user) {
            $c_user = QUI::getUsers()->getSystemUser()->getId();
        }

        QUI::getDataBase()->insert(
            Handler::getInstance()->temporaryInvoiceTable(),
            array(
                'c_user'      => $c_user,
                'editor_id'   => $c_user,
                'editor_name' => '-',
                'hash'        => $hash,
                'customer_id' => 0,
                'type'        => Handler::TYPE_INVOICE_TEMPORARY,
                'paid_status' => QUI\ERP\Order\Order::PAYMENT_STATUS_OPEN
            )
        );

        $newId = QUI::getDataBase()->getPDO()->lastInsertId();

        return Handler::getInstance()->getTemporaryInvoice($newId);
    }
}
