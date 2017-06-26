<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\InvoiceTemporary
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;
use QUI\Utils\Security\Orthos;
use QUI\ERP\Accounting\ArticleList;

/**
 * Class InvoiceTemporary
 * - Temporary Invoice / Invoice class
 * - A temporary invoice is a non posted invoice, it is not a real invoice
 * - To create a correct invoice from the temporary invoice, the post method must be used
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class InvoiceTemporary extends QUI\QDOM
{
    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var int
     */
    protected $type;

    /**
     * @var array
     */
    protected $data = array();

    /**
     * @var array
     */
    protected $articles = array();

    /**
     * @var ArticleList
     */
    protected $Articles;

    /**
     * @var Comments
     */
    protected $Comments;

    /**
     * @var Comments
     */
    protected $History;

    /**
     * Invoice constructor.
     *
     * @param $id
     * @param Handler $Handler
     */
    public function __construct($id, Handler $Handler)
    {
        $data = $Handler->getTemporaryInvoiceData($id);

        $this->prefix = Settings::getInstance()->getTemporaryInvoicePrefix();
        $this->id     = (int)str_replace($this->prefix, '', $id);

        $this->Articles = new ArticleList();
        $this->History  = new Comments();
        $this->Comments = new Comments();

        if (isset($data['articles'])) {
            $articles = json_decode($data['articles'], true);

            if ($articles) {
                try {
                    $this->Articles = new ArticleList($articles);
                } catch (QUI\Exception $Exception) {
                    QUI\System\Log::addError($Exception->getMessage());
                }
            }

            unset($data['articles']);
        }

        if (isset($data['history'])) {
            $this->History = Comments::unserialize($data['history']);
        }

        if (isset($data['comments'])) {
            $this->Comments = Comments::unserialize($data['comments']);
        }

        // invoice extra data
        $this->data = json_decode($data['data'], true);

        if (!is_array($this->data)) {
            $this->data = array();
        }


        // invoice type
        $this->type = Handler::TYPE_INVOICE_TEMPORARY;

        switch ((int)$data['type']) {
            case Handler::TYPE_INVOICE:
            case Handler::TYPE_INVOICE_TEMPORARY:
            case Handler::TYPE_INVOICE_CREDIT_NOTE:
            case Handler::TYPE_INVOICE_REVERSAL:
                $this->type = (int)$data['type'];
                break;
        }

        // database attributes
        $data['time_for_payment'] = (int)$data['time_for_payment'];

        $this->setAttributes($data);
    }

    //region Getter

    /**
     * @return string
     */
    public function getId()
    {
        return $this->prefix . $this->id;
    }

    /**
     * Returns only the integer id
     *
     * @return int
     */
    public function getCleanId()
    {
        return (int)str_replace($this->prefix, '', $this->getId());
    }

    /**
     * Return the invoice type
     * - Handler::TYPE_INVOICE
     * - Handler::TYPE_INVOICE_TEMPORARY
     * - Handler::TYPE_INVOICE_CREDIT_NOTE
     * - Handler::TYPE_INVOICE_REVERSAL
     *
     * @return int
     */
    public function getInvoiceType()
    {
        return $this->type;
    }

    /**
     * Return the customer
     *
     * @return false|null|QUI\Users\Nobody|QUI\Users\SystemUser|QUI\Users\User
     */
    public function getCustomer()
    {
        $uid = $this->getAttribute('customer_id');

        try {
            return QUI::getUsers()->get($uid);
        } catch (QUI\Users\Exception $Exception) {
            QUI\System\Log::addWarning($Exception->getMessage());
        }

        return null;
    }

    /**
     * Return the editor user
     *
     * @return null|QUI\Interfaces\Users\User
     */
    public function getEditor()
    {
        $Employees = QUI\ERP\Employee\Employees::getInstance();

        if ($this->getAttribute('editor_id')) {
            try {
                $Editor    = QUI::getUsers()->get($this->getAttribute('editor_id'));
                $isInGroup = $Editor->isInGroup($Employees->getEmployeeGroup()->getId());

                if ($isInGroup) {
                    return $Editor;
                }
            } catch (QUI\Exception $Exception) {
            }
        }

        // use default advisor as editor
        if (empty($editorId) && $Employees->getDefaultAdvisor()) {
            return $Employees->getDefaultAdvisor();
        }

        return null;
    }

    /**
     * Return the ordered by user
     *
     * @return null|QUI\Interfaces\Users\User
     */
    public function getOrderedByUser()
    {
        if ($this->getAttribute('ordered_by')) {
            try {
                return QUI::getUsers()->get($this->getAttribute('ordered_by'));
            } catch (QUI\Exception $Exception) {
            }
        }

        return null;
    }

    /**
     * Return all fields, attributes which are still missing to post the invoice
     *
     * @return array
     */
    public function getMissingAttributes()
    {
        return Utils\Invoice::getMissingAttributes($this);
    }

    /**
     * Return a invoice view
     *
     * @return InvoiceView
     */
    public function getView()
    {
        return new InvoiceView($this);
    }

    //endregion

    /**
     * Change the invoice type
     *
     * The following types can be used:
     * - Handler::TYPE_INVOICE_TEMPORARY:
     * - Handler::TYPE_INVOICE_CREDIT_NOTE:
     * - Handler::TYPE_INVOICE_REVERSAL:
     *
     * @param string|integer $type
     * @param QUI\Interfaces\Users\User|null $PermissionUser - optional
     */
    public function setInvoiceType($type, $PermissionUser = null)
    {
        QUI\Permissions\Permission::checkPermission(
            'quiqqer.invoice.temporary.edit',
            $PermissionUser
        );

        $type = (int)$type;

        switch ($type) {
            case Handler::TYPE_INVOICE_TEMPORARY:
            case Handler::TYPE_INVOICE_CREDIT_NOTE:
            case Handler::TYPE_INVOICE_REVERSAL:
                break;

            default:
                return;
        }

        $this->type = $type;

        $this->addHistory(
            QUI::getLocale()->get(
                'quiqqer/invoice',
                'history.message.temporaryInvoice.type.changed',
                array(
                    'type' => $type
                )
            )
        );
    }

    /**
     * Save the current temporary invoice data to the database
     *
     * @param QUI\Interfaces\Users\User|null $PermissionUser
     * @throws QUI\Permissions\Exception
     */
    public function save($PermissionUser = null)
    {
        QUI\Permissions\Permission::checkPermission(
            'quiqqer.invoice.temporary.edit',
            $PermissionUser
        );

        $this->checkLocked();

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoiceSaveBegin',
            array($this)
        );

        $this->Articles->calc();
        $listCalculations = $this->Articles->getCalculations();

        // attributes
        $projectName    = '';
        $timeForPayment = '';
        $paymentMethod  = '';
        $date           = '';

        $isBrutto = QUI\ERP\Defaults::getBruttoNettoStatus();

        if ($this->getCustomer()) {
            $isBrutto = QUI\ERP\Utils\User::getBruttoNettoUserStatus($this->getCustomer());
        }

        if ($this->getAttribute('project_name')) {
            $projectName = $this->getAttribute('project_name');
        }

        if ($this->getAttribute('time_for_payment')) {
            $timeForPayment = (int)$this->getAttribute('time_for_payment');
        }

        if ($this->getAttribute('date')
            && Orthos::checkMySqlDatetimeSyntax($this->getAttribute('date'))
        ) {
            $date = $this->getAttribute('date');
        }

        // address
        try {
            $Customer = QUI::getUsers()->get((int)$this->getAttribute('customer_id'));
            $Address  = $Customer->getAddress(
                (int)$this->getAttribute('invoice_address_id')
            );

            $invoiceAddress = $Address->toJSON();
        } catch (QUI\Exception $Exception) {
            $invoiceAddress      = $this->getAttribute('invoice_address');
            $invoiceAddressCheck = false;

            if (is_string($invoiceAddress)) {
                $invoiceAddressCheck = json_decode($invoiceAddress, true);
            }

            if (!$invoiceAddressCheck) {
                QUI\System\Log::addNotice($Exception->getMessage());
            }
        }

        // payment
        try {
            if ($this->getAttribute('payment_method')) {
                $Payments = QUI\ERP\Accounting\Payments\Payments::getInstance();
                $Payment  = $Payments->getPayment($this->getAttribute('payment_method'));

                $paymentMethod = $Payment->getName();
            }
        } catch (QUI\ERP\Accounting\Payments\Exception $Exception) {
            QUI\System\Log::addNotice($Exception->getMessage());
        }

        // Editor
        $Editor     = $this->getEditor();
        $editorId   = '';
        $editorName = '';

        // use default advisor as editor
        if ($Editor) {
            $editorId   = $Editor->getId();
            $editorName = $Editor->getName();
        }

        // Ordered By
        $OrderedBy     = $this->getOrderedByUser();
        $orderedBy     = '';
        $orderedByName = '';

        if ($OrderedBy) {
            $orderedBy     = $OrderedBy->getId();
            $orderedByName = $OrderedBy->getName();
        }


        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoiceSave',
            array($this)
        );

        QUI::getDataBase()->update(
            Handler::getInstance()->temporaryInvoiceTable(),
            array(
                'type'                    => $this->getInvoiceType(),

                // user relationships
                'customer_id'             => (int)$this->getAttribute('customer_id'),
                'editor_id'               => $editorId,
                'editor_name'             => $editorName,
                'order_id'                => (int)$this->getAttribute('order_id'),
                'ordered_by'              => $orderedBy,
                'ordered_by_name'         => $orderedByName,

                // payments
                'payment_method'          => $paymentMethod,
                'payment_data'            => '',
                'payment_time'            => '',

                // address
                'invoice_address_id'      => (int)$this->getAttribute('invoice_address_id'),
                'invoice_address'         => $invoiceAddress,
                'delivery_address_id'     => '',
                'delivery_address'        => '',

                // processing
                'time_for_payment'        => $timeForPayment,
                'paid_status'             => '', // nicht in gui
                'paid_date'               => '', // nicht in gui
                'paid_data'               => '', // nicht in gui
                'processing_status'       => '',
                'customer_data'           => '',  // nicht in gui

                // invoice data
                'date'                    => $date,
                'data'                    => json_encode($this->data),
                'articles'                => $this->Articles->toJSON(),
                'history'                 => $this->History->toJSON(),
                'comments'                => $this->Comments->toJSON(),
                'additional_invoice_text' => $this->getAttribute('additional_invoice_text'),
                'project_name'            => $projectName,

                // Calc data
                'isbrutto'                => $isBrutto,
                'currency_data'           => json_encode($listCalculations['currencyData']),
                'nettosum'                => $listCalculations['nettoSum'],
                'subsum'                  => $listCalculations['subSum'],
                'sum'                     => $listCalculations['sum'],
                'vat_array'               => json_encode($listCalculations['vatArray'])
            ),
            array(
                'id' => $this->getCleanId()
            )
        );

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoiceEnd',
            array($this)
        );
    }

    /**
     * Delete the temporary invoice
     *
     * @param QUI\Interfaces\Users\User|null $PermissionUser
     * @throws QUI\Permissions\Exception
     */
    public function delete($PermissionUser = null)
    {
        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        QUI\Permissions\Permission::checkPermission(
            'quiqqer.invoice.temporary.delete',
            $PermissionUser
        );

        $this->checkLocked();

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoiceDelete',
            array($this)
        );

        QUI::getDataBase()->delete(
            Handler::getInstance()->temporaryInvoiceTable(),
            array('id' => $this->getCleanId())
        );
    }

    /**
     * Copy the temporary invoice
     *
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @return InvoiceTemporary
     */
    public function copy($PermissionUser = null)
    {
        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        QUI\Permissions\Permission::checkPermission(
            'quiqqer.invoice.temporary.copy',
            $PermissionUser
        );

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoiceCopy',
            array($this)
        );

        $Handler = Handler::getInstance();
        $Factory = Factory::getInstance();
        $New     = $Factory->createInvoice($PermissionUser);

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

        $Copy = $Handler->getTemporaryInvoice($New->getId());

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoiceCopyEnd',
            array($this, $Copy)
        );

        return $Copy;
    }

    /**
     * Creates an invoice from the temporary invoice
     * Its post the invoice
     *
     * @param QUI\Interfaces\Users\User|null $PermissionUser
     * @return Invoice
     * @throws Exception|QUI\Permissions\Exception|QUI\Exception
     */
    public function post($PermissionUser = null)
    {
        if ($PermissionUser === null) {
            $PermissionUser = QUI::getUserBySession();
        }

        QUI\Permissions\Permission::checkPermission(
            'quiqqer.invoice.post',
            $PermissionUser
        );

        $this->checkLocked();

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoicePostBegin',
            array($this)
        );

        $this->save(QUI::getUsers()->getSystemUser());

        // check all current data
        $this->validate();

        // data
        $User     = QUI::getUserBySession();
        $date     = date('y-m-d H:i:s');
        $isBrutto = QUI\ERP\Defaults::getBruttoNettoStatus();
        $Customer = $this->getCustomer();
        $Handler  = Handler::getInstance();


        if ($this->getAttribute('date')
            && Orthos::checkMySqlDatetimeSyntax($this->getAttribute('date'))
        ) {
            $date = $this->getAttribute('date');
        }

        if ($Customer) {
            $isBrutto = QUI\ERP\Utils\User::getBruttoNettoUserStatus($this->getCustomer());
        }

        // address
        try {
            $Address = $Customer->getAddress(
                (int)$this->getAttribute('invoice_address_id')
            );

            $invoiceAddress = $Address->toJSON();
        } catch (QUI\Exception $Exception) {
            $invoiceAddress = $this->getAttribute('invoice_address');

            if (!$invoiceAddress) {
                throw $Exception;
            }
        }

        // customerData
        $ErpCustomer = QUI\ERP\User::convertUserToErpUser($Customer);

        $customerData                    = $ErpCustomer->getAttributes();
        $customerData['erp.isNettoUser'] = $Customer->getAttribute('quiqqer.erp.isNettoUser');
        $customerData['erp.euVatId']     = $Customer->getAttribute('quiqqer.erp.euVatId');
        $customerData['erp.taxNumber']   = $Customer->getAttribute('quiqqer.erp.taxNumber');


        // Editor
        $Editor     = $this->getEditor();
        $editorId   = '';
        $editorName = '';

        // use default advisor as editor
        if ($Editor) {
            $editorId   = $Editor->getId();
            $editorName = $Editor->getName();
        }

        // Ordered By
        $OrderedBy     = $this->getOrderedByUser();
        $orderedBy     = '';
        $orderedByName = '';

        // use default advisor as editor
        if ($OrderedBy) {
            $orderedBy     = $OrderedBy->getId();
            $orderedByName = $OrderedBy->getName();
        }

        // time for payment
        $paymentTime = (int)$this->getAttribute('time_for_payment');

        $timeForPayment = date(
            'Y-m-d',
            strtotime(
                date('Y-m-d') . ' 00:00 + ' . $paymentTime . ' days'
            )
        );

        $timeForPayment .= ' 23:59.59';


        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoicePost',
            array($this)
        );

        $type = $this->getInvoiceType();

        if ($type === Handler::TYPE_INVOICE_TEMPORARY) {
            $type = Handler::TYPE_INVOICE;
        }

        // article calc
        $this->Articles->setUser($Customer);
        $this->Articles->calc();
        $listCalculations = $this->Articles->getCalculations();

        // create invoice
        QUI::getDataBase()->insert(
            $Handler->invoiceTable(),
            array(
                'type'                    => $type,
                'id_prefix'               => Settings::getInstance()->getInvoicePrefix(),

                // user relationships
                'c_user'                  => $User->getId(),
                'c_username'              => $User->getName(),
                'editor_id'               => $editorId,
                'editor_name'             => $editorName,
                'order_id'                => $this->getAttribute('order_id'),
                'ordered_by'              => $orderedBy,
                'ordered_by_name'         => $orderedByName,
                'customer_id'             => $this->getCustomer()->getId(),
                'customer_data'           => json_encode($customerData),

                // addresses
                'invoice_address'         => $invoiceAddress,
                'delivery_address'        => $this->getAttribute('delivery_address'),

                // payments
                'payment_method'          => $this->getAttribute('payment_method'),
                'payment_data'            => '', // <!-- muss verschlüsselt sein -->
                'payment_time'            => '',
                'time_for_payment'        => $timeForPayment,

                // paid status
                'paid_status'             => Invoice::PAYMENT_STATUS_OPEN,
                'paid_date'               => '',
                'paid_data'               => '',

                // data
                'hash'                    => $this->getAttribute('hash'),
                'project_name'            => $this->getAttribute('project_name'),
                'date'                    => $date,
                'data'                    => json_encode($this->data),
                'additional_invoice_text' => $this->getAttribute('additional_invoice_text'),
                'articles'                => $this->Articles->toUniqueList()->toJSON(),
                'history'                 => $this->getHistory()->toJSON(),
                'comments'                => $this->getComments()->toJSON(),

                // calculation data
                'isbrutto'                => $isBrutto,
                'currency_data'           => json_encode($listCalculations['currencyData']),
                'nettosum'                => $listCalculations['nettoSum'],
                'nettosubsum'             => $listCalculations['nettoSubSum'],
                'subsum'                  => $listCalculations['subSum'],
                'sum'                     => $listCalculations['sum'],
                'vat_array'               => json_encode($listCalculations['vatArray'])
            )
        );

        $newId = QUI::getDataBase()->getPDO()->lastInsertId('id');


        // if temporary invoice was a credit note
        // add history entry to original invoice
        if (isset($this->data['originalId'])) {
            try {
                $Parent = $Handler->getInvoice($this->data['originalId']);
                $Parent->addHistory(
                    QUI::getLocale()->get(
                        'quiqqer/invoice',
                        'message.create.credit.post',
                        array(
                            'invoiceId' => $this->getId()
                        )
                    )
                );
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        $this->delete(QUI::getUsers()->getSystemUser());
        $Invoice = $Handler->getInvoice($newId);

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoicePostEnd',
            array($this, $Invoice)
        );

        return $Invoice;
    }

    /**
     * Alias for post()
     *
     * @param null|QUI\Interfaces\Users\User $PermissionUser
     * @return Invoice
     */
    public function createInvoice($PermissionUser = null)
    {
        return $this->post($PermissionUser);
    }

    /**
     * Verificate / Validate the invoice
     * Can the invoice be posted?
     *
     * @throws QUI\ERP\Accounting\Invoice\Exception
     */
    public function validate()
    {
        $missing = $this->getMissingAttributes();

        foreach ($missing as $field) {
            throw new Exception(Utils\Invoice::getMissingAttributeMessage($field));
        }
    }

    /**
     * Parse the Temporary invoice to an array
     *
     * @return array
     */
    public function toArray()
    {
        $attributes = $this->getAttributes();

        $attributes['id']       = $this->getId();
        $attributes['type']     = $this->getInvoiceType();
        $attributes['articles'] = $this->Articles->toArray();

        return $attributes;
    }

    //region Article Management

    /**
     * Add an article
     *
     * @param QUI\ERP\Accounting\Article $Article
     */
    public function addArticle(QUI\ERP\Accounting\Article $Article)
    {
        $this->Articles->addArticle($Article);
    }

    /**
     * @param int $index
     */
    public function removeArticle($index)
    {
        $this->Articles->removeArticle($index);
    }

    /**
     * Returns the article list
     *
     * @return ArticleList
     */
    public function getArticles()
    {
        return $this->Articles;
    }

    /**
     * Remove all articles from the invoice
     */
    public function clearArticles()
    {
        $this->Articles->clear();
    }

    /**
     * Import an article array
     *
     * @param array $articles - array of articles, eq: [0] => Array
     * (
     *       [productId] => 5
     *       [type] => QUI\ERP\Products\Product\Product
     *       [position] => 1
     *       [quantity] => 1
     *       [unitPrice] => 0
     *       [vat] =>
     *       [title] => USS Enterprise NCC-1701-D
     *       [description] => Raumschiff aus bekannter Serie
     *       [control] => package/quiqqer/products/bin/controls/invoice/Product
     * )
     */
    public function importArticles($articles = array())
    {
        if (!is_array($articles)) {
            $articles = array();
        }

        foreach ($articles as $article) {
            try {
                if (isset($article['class'])
                    && class_exists($article['class'])
                ) {
                    $Article = new $article['class']($article);

                    if ($Article instanceof QUI\ERP\Accounting\Article) {
                        $this->addArticle($Article);
                        continue;
                    }
                }

                $Article = new QUI\ERP\Accounting\Article($article);
                $this->addArticle($Article);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }
    }

    //endregion

    //region Comments

    /**
     * Return the comments list object
     *
     * @return Comments
     */
    public function getComments()
    {
        return $this->Comments;
    }

    /**
     * Add a comment
     *
     * @param string $message
     */
    public function addComment($message)
    {
        $this->Comments->addComment($message);
        $this->save();

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoiceAddComment',
            array($this, $message)
        );
    }

    //endregion

    //region History

    /**
     * Return the history list object
     *
     * @return Comments
     */
    public function getHistory()
    {
        return $this->History;
    }

    /**
     * Add a history entry
     *
     * @param string $message
     */
    public function addHistory($message)
    {
        $this->History->addComment($message);

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoiceAddHistory',
            array($this, $message)
        );
    }

    //endregion

    //region Data

    /**
     * Set a data value
     *
     * @param string $key
     * @param mixed $value
     */
    public function setData($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * Return a data field
     *
     * @param string $key
     * @return bool|mixed
     */
    public function getData($key)
    {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }

        return false;
    }

    //endregion

    //region LOCK

    /**
     * Lock the invoice
     * Invoice can't be edited
     */
    public function lock()
    {
        $Package = QUI::getPackage('quiqqer/invoice');
        $key     = 'temporary-invoice-' . $this->getId();

        QUI\Lock\Locker::lock($Package, $key);
    }

    /**
     * Unlock the invoice
     * Invoice can be edited
     */
    public function unlock()
    {
        $Package = QUI::getPackage('quiqqer/invoice');
        $key     = 'temporary-invoice-' . $this->getId();

        QUI\Lock\Locker::unlock($Package, $key);
    }

    /**
     * Is the invoice locked?
     *
     * @return false|mixed
     */
    public function isLocked()
    {
        $Package = QUI::getPackage('quiqqer/invoice');
        $key     = 'temporary-invoice-' . $this->getId();

        return QUI\Lock\Locker::isLocked($Package, $key);
    }

    /**
     * Check, if the item is locked
     *
     * @throws QUI\Exception
     */
    public function checkLocked()
    {
        $Package = QUI::getPackage('quiqqer/invoice');
        $key     = 'temporary-invoice-' . $this->getId();

        QUI\Lock\Locker::checkLocked($Package, $key);
    }

    //endregion
}
