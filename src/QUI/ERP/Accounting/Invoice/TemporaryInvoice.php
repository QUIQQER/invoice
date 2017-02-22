<?php

namespace QUI\ERP\Accounting\Invoice;

use QUI;

/**
 * Class TemporaryInvoice
 * - Temporary Invoice / Invoice class
 * - A temporary invoice is a non posted invoice, it is not a real invoice
 * - To create a correct invoice from the temporary invoice, the post method must be used
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class TemporaryInvoice extends QUI\QDOM
{
    /**
     * @var string
     */
    const ID_PREFIX = 'EDIT-';

    /**
     * @var string
     */
    protected $id;

    /**
     * Invoice constructor.
     *
     * @param $id
     * @param Handler $Handler
     */
    public function __construct($id, Handler $Handler)
    {
        $this->setAttributes($Handler->getTemporaryInvoiceData($id));

        $this->id = (int)str_replace(self::ID_PREFIX, '', $id);
    }

    /**
     * @return string
     */
    public function getId()
    {
        return self::ID_PREFIX . $this->id;
    }

    /**
     * Returns only the integer id
     *
     * @return int
     */
    public function getCleanId()
    {
        return (int)str_replace(self::ID_PREFIX, '', $this->getId());
    }

    /**
     * Save the current temporary invoice data to the database
     *
     * @param QUI\Interfaces\Users\User|null $User
     * @throws QUI\Permissions\Exception
     */
    public function save($User = null)
    {
        QUI\Permissions\Permission::checkPermission('quiqqer.invoice.edit', $User);
//            customer_id
//            order_id
//            hash
//
//            payment_method
//            payment_data
//            payment_time
//            payment_address
//            delivery_address
//            time_for_payment
//
//            paid_status
//            paid_date
//            paid_data
//
//            canceled
//            date
//            c_user
//            data
//            products
//            history
//            customer_data
//            isbrutto
//            currency_data
//
//            nettosum
//            subsum
//            sum
//            vat_data
//            processing_status

        QUI::getDataBase()->update(
            Handler::getInstance()->temporaryInvoiceTable(),
            array(
                'customer_id'       => (int)$this->getAttribute('customer_id'),
                'address_id'        => (int)$this->getAttribute('address_id'),
                'order_id'          => (int)$this->getAttribute('order_id') || '',
                'payment_method'    => $this->getAttribute('payment_method'),
                'payment_data'      => '',
                'payment_time'      => '',
                'payment_address'   => '',
                'delivery_address'  => '',
                'time_for_payment'  => '',
                'paid_status'       => '',
                'paid_date'         => '',
                'paid_data'         => '',
                'canceled'          => '',
                'date'              => '',
                'data'              => '',
                'products'          => '',
                'history'           => '',
                'customer_data'     => '',
                'isbrutto'          => '',
                'currency_data'     => '',
                'nettosum'          => '',
                'subsum'            => '',
                'sum'               => '',
                'vat_data'          => '',
                'processing_status' => ''

            ),
            array(
                'id' => $this->getCleanId()
            )
        );
    }

    /**
     * Delete the temporary invoice
     *
     * @param QUI\Interfaces\Users\User|null $User
     * @throws QUI\Permissions\Exception
     */
    public function delete($User = null)
    {
        QUI\Permissions\Permission::checkPermission('quiqqer.invoice.delete', $User);


        QUI::getDataBase()->delete(
            Handler::getInstance()->temporaryInvoiceTable(),
            array(
                'id' => $this->getCleanId()
            )
        );
    }

    /**
     * Copy the temporary invoice
     *
     * @param null $User
     * @return TemporaryInvoice
     */
    public function copy($User = null)
    {
        $Handler = Handler::getInstance();
        $New     = $Handler->create($User);

        $currentData = QUI::getDataBase()->fetch(array(
            'from'  => $Handler->temporaryInvoiceTable(),
            'where' => array(
                'id' => $this->getCleanId()
            ),
            'limit' => 1
        ));

        $currentData = $currentData[0];

        unset($currentData['id']);
        unset($currentData['c_user']);
        unset($currentData['date']);

        QUI::getDataBase()->update(
            $Handler->temporaryInvoiceTable(),
            $currentData,
            array('id' => $New->getCleanId())
        );

        return $Handler->getTemporaryInvoice($New->getId());
    }

    /**
     * Creates an invoice from the temporary invoice
     * Its post the invoice
     *
     * @param QUI\Interfaces\Users\User|null $User
     * @return Invoice
     * @throws Exception|QUI\Permissions\Exception|QUI\Exception
     */
    public function post($User = null)
    {
        QUI\Permissions\Permission::checkPermission('quiqqer.invoice.post', $User);

        // check all current data

        // user and user-address check
        $customerId = $this->getAttribute('customer_id');
        $addressId  = $this->getAttribute('address_id');

        $Customer = QUI::getUsers()->get($customerId);
        $Address  = $Customer->getAddress($addressId);


        // create invoice
    }

    /**
     * Parse the Temporary invoice to an array
     *
     * @return array
     */
    public function toArray()
    {
        $attributes       = $this->getAttributes();
        $attributes['id'] = $this->getId();

        return $attributes;
    }
}
