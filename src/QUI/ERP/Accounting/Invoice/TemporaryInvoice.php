<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\TemporaryInvoice
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;
use QUI\Utils\Security\Orthos;
use QUI\ERP\Accounting\ArticleList;

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

        $this->id = (int)str_replace(self::ID_PREFIX, '', $id);

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

        $this->setAttributes($data);
    }

    //region Getter

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
     * Save the current temporary invoice data to the database
     *
     * @param QUI\Interfaces\Users\User|null $User
     * @throws QUI\Permissions\Exception
     */
    public function save($User = null)
    {
        QUI\Permissions\Permission::checkPermission('quiqqer.invoice.edit', $User);
        $this->checkLocked();

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoiceSaveBegin',
            array($this)
        );

        $this->Articles->calc();
        $listCalculations = $this->Articles->getCalculations();

        // attributes
        $projectName    = '';
        $invoiceAddress = '';
        $timeForPayment = '';
        $paymentMethod  = '';
        $date           = '';

        $isBrutto = QUI\ERP\Defaults::getBruttoNettoStatus();

        if ($this->getCustomer()
            && !QUI\ERP\Utils\User::isNettoUser($this->getCustomer())
        ) {
            $isBrutto = 1;
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
            QUI\System\Log::addNotice($Exception->getMessage());
        }

        // payment
        try {
            if ($this->getAttribute('payment_method')) {
                $Payments = QUI\ERP\Accounting\Payments\Handler::getInstance();
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
                'customer_id'  => (int)$this->getAttribute('customer_id'),
                'order_id'     => (int)$this->getAttribute('order_id'),
                'project_name' => $projectName,

                'editor_id'               => $editorId,
                'editor_name'             => $editorName,
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

                // processing status
                'time_for_payment'        => $timeForPayment,
                'paid_status'             => '', // nicht in gui
                'paid_date'               => '', // nicht in gui
                'paid_data'               => '', // nicht in gui
                'processing_status'       => '',
                'customer_data'           => '',  // nicht in gui

                // invoice data
                'date'                    => $date,
                'data'                    => '',
                'articles'                => $this->Articles->toJSON(),
                'history'                 => $this->History->toJSON(),
                'comments'                => $this->Comments->toJSON(),
                'additional_invoice_text' => $this->getAttribute('additional_invoice_text'),

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
     * @param QUI\Interfaces\Users\User|null $User
     * @throws QUI\Permissions\Exception
     */
    public function delete($User = null)
    {
        QUI\Permissions\Permission::checkPermission('quiqqer.invoice.delete', $User);
        $this->checkLocked();

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoiceDelete',
            array($this)
        );

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
        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoiceCopy',
            array($this)
        );

        $Handler = Handler::getInstance();
        $Factory = Factory::getInstance();
        $New     = $Factory->createInvoice($User);

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
     * @param QUI\Interfaces\Users\User|null $User
     * @return Invoice
     * @throws Exception|QUI\Permissions\Exception|QUI\Exception
     */
    public function post($User = null)
    {
        if ($User === null) {
            $User = QUI::getUserBySession();
        }

        QUI\Permissions\Permission::checkPermission('quiqqer.invoice.post', $User);
        $this->checkLocked();

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoicePostBegin',
            array($this)
        );

        $this->save(QUI::getUsers()->getSystemUser());

        // check all current data
        $this->validate();
        $this->Articles->calc();

        // data
        $listCalculations = $this->Articles->getCalculations();

        $date     = date('y-m-d H:i:s');
        $isBrutto = QUI\ERP\Defaults::getBruttoNettoStatus();
        $Customer = $this->getCustomer();
        $Handler  = Handler::getInstance();


        if ($this->getAttribute('date')
            && Orthos::checkMySqlDatetimeSyntax($this->getAttribute('date'))
        ) {
            $date = $this->getAttribute('date');
        }

        if ($Customer
            && !QUI\ERP\Utils\User::isNettoUser($this->getCustomer())
        ) {
            $isBrutto = 1;
        }

        // address // @todo must be variable
        $Address = $Customer->getAddress(
            (int)$this->getAttribute('invoice_address_id')
        );

        $invoiceAddress = $Address->toJSON();

        // customerData
        $customerData = array(
            'erp.isNettoUser' => $Customer->getAttribute('quiqqer.erp.isNettoUser'),
            'erp.euVatId'     => $Customer->getAttribute('quiqqer.erp.euVatId'),
            'erp.taxNumber'   => $Customer->getAttribute('quiqqer.erp.taxNumber'),
        );

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

        QUI::getEvents()->fireEvent(
            'quiqqerInvoiceTemporaryInvoicePost',
            array($this)
        );

        // create invoice
        QUI::getDataBase()->insert(
            $Handler->invoiceTable(),
            array(
                'id_prefix'   => Invoice::ID_PREFIX,
                'customer_id' => $this->getCustomer()->getId(),
                'order_id'    => $this->getAttribute('order_id'),
                'c_user'      => $User->getId(),
                'c_username'  => $User->getUsername(),

                'editor_id'       => $editorId,
                'editor_name'     => $editorName,
                'ordered_by'      => $orderedBy,
                'ordered_by_name' => $orderedByName,

                'hash'         => $this->getAttribute('hash'),
                'project_name' => $this->getAttribute('project_name'),

                'invoice_address'  => $invoiceAddress,
                'delivery_address' => $this->getAttribute('delivery_address'),

                'payment_method'   => $this->getAttribute('payment_method'),
                'payment_data'     => '', // <!-- muss verschlüsselt sein -->
                'payment_time'     => '',
                'time_for_payment' => (int)$this->getAttribute('time_for_payment'),

                'paid_status'   => Invoice::PAYMENT_STATUS_OPEN,
                'paid_date'     => '',
                'paid_data'     => '',
                'customer_data' => json_encode($customerData),

                'date'                    => $date,
                'data'                    => $this->getAttribute('data'),
                'additional_invoice_text' => $this->getAttribute('additional_invoice_text'),
                'articles'                => $this->getArticles()->toJSON(),
                'history'                 => $this->getHistory()->toJSON(),
                'comments'                => $this->getComments()->toJSON(),

                // Calc data
                'isbrutto'                => $isBrutto,
                'currency_data'           => json_encode($listCalculations['currencyData']),
                'nettosum'                => $listCalculations['nettoSum'],
                'nettosubsum'             => $listCalculations['nettoSubSum'],
                'subsum'                  => $listCalculations['subSum'],
                'sum'                     => $listCalculations['sum'],
                'vat_array'               => json_encode($listCalculations['vatArray'])
            )
        );

        $newId = QUI::getDataBase()->getPDO()->lastInsertId();

        $this->delete($User);
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
     * @param null $User
     * @return Invoice
     */
    public function createInvoice($User = null)
    {
        return $this->post($User);
    }

    /**
     * Verificate / Validate the invoice
     * Can the invoice be posted?
     *
     * @throws QUI\Exception|Exception
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
        $attributes       = $this->getAttributes();
        $attributes['id'] = $this->getId();

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
