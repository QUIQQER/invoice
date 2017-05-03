<?php

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


    protected $Comments;

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

        //region list of attributes - helper
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
        //endregion

        $this->Articles->calc();
        $listCalculations = $this->Articles->getCalculations();

        //QUI\System\Log::writeRecursive($this->Articles->toJSON());

        // attributes
        $projectName    = '';
        $invoiceAddress = '';
        $timeForPayment = '';
        $date           = '';
        $isBrutto       = QUI\ERP\Defaults::getBruttoNettoStatus();

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

        QUI::getDataBase()->update(
            Handler::getInstance()->temporaryInvoiceTable(),
            array(
                'customer_id'  => (int)$this->getAttribute('customer_id'),
                'order_id'     => (int)$this->getAttribute('order_id'),
                'project_name' => $projectName,

                'payment_method' => $this->getAttribute('payment_method'),
                'payment_data'   => '',
                'payment_time'   => '',

                'invoice_address_id'  => (int)$this->getAttribute('invoice_address_id'),
                'invoice_address'     => $invoiceAddress,
                'delivery_address_id' => '',
                'delivery_address'    => '',

                'time_for_payment'  => $timeForPayment,
                'paid_status'       => '', // nicht in gui
                'paid_date'         => '', // nicht in gui
                'paid_data'         => '', // nicht in gui
                'processing_status' => '',
                'customer_data'     => '',  // nicht in gui

                'date'     => $date,
                'data'     => '',
                'articles' => $this->Articles->toJSON(),
                'history'  => $this->History->toJSON(),
                'comments' => $this->Comments->toJSON(),

                'isbrutto'      => $isBrutto,
                'currency_data' => json_encode($listCalculations['currencyData']),
                'nettosum'      => $listCalculations['nettoSum'],
                'subsum'        => $listCalculations['subSum'],
                'sum'           => $listCalculations['sum'],
                'vat_data'      => json_encode($listCalculations['vatArray'])

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

        $this->save(QUI::getUsers()->getSystemUser());

        // check all current data
        $this->validate();

        // create invoice
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
        $this->Articles->calc();

        // user and user-address check
        $customerId = $this->getAttribute('customer_id');
        $addressId  = $this->getAttribute('invoice_address_id');

        $Customer = QUI::getUsers()->get($customerId);
        $Address  = $Customer->getAddress($addressId);

        // check address fields
        $Address->getCountry();

        $this->verificateField($Address->getAttribute('firstname'), array(
            'quiqqer/invoice',
            'exception.invoice.verification.firstname'
        ));

        $this->verificateField($Address->getAttribute('lastname'), array(
            'quiqqer/invoice',
            'exception.invoice.verification.lastname'
        ));

        $this->verificateField($Address->getAttribute('street_no'), array(
            'quiqqer/invoice',
            'exception.invoice.verification.street_no'
        ));

        $this->verificateField($Address->getAttribute('zip'), array(
            'quiqqer/invoice',
            'exception.invoice.verification.zip'
        ));

        $this->verificateField($Address->getAttribute('city'), array(
            'quiqqer/invoice',
            'exception.invoice.verification.city'
        ));

        $this->verificateField($Address->getAttribute('country'), array(
            'quiqqer/invoice',
            'exception.invoice.verification.country'
        ));

        if (!$this->Articles->count()) {
            throw new Exception(array(
                'quiqqer/invoice',
                'exception.invoice.verification.empty.articles'
            ));
        }

        // @todo payment prüfung
    }

    /**
     * Verification of a field, value can not be empty
     *
     * @param $value
     * @param array|string $eMessage
     * @param int $eCode - optional
     * @param array $eContext - optional
     *
     * @throws Exception
     */
    protected function verificateField($value, $eMessage, $eCode = 0, $eContext = array())
    {
        if (empty($value)) {
            throw new Exception($eMessage, $eCode, $eContext);
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
    }

    //endregion
}
