<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Factory
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;

use function json_encode;

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
     * @param bool|string $globalProcessId - hash of the process
     * @return InvoiceTemporary
     * @throws
     */
    public function createInvoice(
        QUI\Interfaces\Users\User $User = null,
        bool|string $globalProcessId = false
    ): InvoiceTemporary {
        if ($User === null) {
            $User = QUI::getUserBySession();
        }


        $hash = QUI\Utils\Uuid::get();

        // check if hash already exists, if hash exists, we cant use it twice
        try {
            Handler::getInstance()->getInvoiceByHash($hash);

            // if invoice hash exist, we need a new hash
            $hash = QUI\Utils\Uuid::get();
        } catch (QUI\Exception $Exception) {
            // nothing
        }


        $editor = 0;
        $c_user = $User->getId();

        if (!$c_user) {
            $c_user = QUI::getUsers()->getSystemUser()->getId();
        }

        if (QUI::isBackend()) {
            $editor = $c_user;
        }

        QUI::getDataBase()->insert(
            Handler::getInstance()->temporaryInvoiceTable(),
            [
                'c_user' => $c_user,
                'editor_id' => $editor,
                'editor_name' => '-',
                'hash' => $hash,
                'global_process_id' => $globalProcessId,
                'customer_id' => 0,
                'type' => Handler::TYPE_INVOICE_TEMPORARY,
                'paid_status' => QUI\ERP\Constants::PAYMENT_STATUS_OPEN,
                'time_for_payment' => (int)Settings::getInstance()->get('invoice', 'time_for_payment'),
                'currency' => QUI\ERP\Defaults::getCurrency()->getCode(),
                'currency_data' => json_encode(QUI\ERP\Defaults::getCurrency()->toArray())
            ]
        );

        $newId = QUI::getDataBase()->getPDO()->lastInsertId();

        try {
            $TemporaryInvoice = Handler::getInstance()->getTemporaryInvoice($newId);
            $TemporaryInvoice->addHistory(QUI::getLocale()->get('quiqqer/invoice', 'history.invoiceTemporary.created'));
            $TemporaryInvoice->save(QUI::getUsers()->getSystemUser());
        } catch (QUI\Permissions\Exception) {
            $TemporaryInvoice = Handler::getInstance()->getTemporaryInvoice($newId);
        }

        try {
            QUI::getEvents()->fireEvent('onQuiqqerInvoiceTemporaryInvoiceCreated', [$TemporaryInvoice]);
        } catch (\Exception $Exception) {
            QUI\System\Log::addError($Exception->getMessage());
        }

        return $TemporaryInvoice;
    }
}
