<?php

namespace QUI\ERP\Accounting\Invoice;

use QUI;
use Ramsey\Uuid\Uuid;

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
     * @return TemporaryInvoice
     */
    public function createInvoice($User = null)
    {
        if ($User === null) {
            $User = QUI::getUserBySession();
        }

        QUI\Permissions\Permission::checkPermission('quiqqer.invoice.create', $User);

        QUI::getDataBase()->insert(
            Handler::getInstance()->temporaryInvoiceTable(),
            array(
                'c_user' => $User->getId(),
                'hash'   => Uuid::uuid1()->toString()
            )
        );

        $newId = QUI::getDataBase()->getPDO()->lastInsertId();

        return Handler::getInstance()->getTemporaryInvoice($newId);
    }
}
