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

    /**
     * Invoice constructor.
     *
     * @param $id
     * @param Handler $Handler
     */
    public function __construct($id, Handler $Handler)
    {
        $data = $Handler->getTemporaryInvoiceData($id);

        $this->id       = (int)str_replace(self::ID_PREFIX, '', $id);
        $this->Articles = new ArticleList();

        if (isset($data['articles'])) {
            $this->Articles = new ArticleList(
                json_decode($data['articles'], true)
            );

            unset($data['articles']);
        }

        $this->setAttributes($data);
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

        QUI\System\Log::writeRecursive($this->Articles->toJSON());

        // attributes

        $projectName    = '';
        $timeForPayment = '';
        $date           = '';

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

        QUI::getDataBase()->update(
            Handler::getInstance()->temporaryInvoiceTable(),
            array(
                'customer_id'       => (int)$this->getAttribute('customer_id'),
                'address_id'        => (int)$this->getAttribute('address_id'),
                'order_id'          => (int)$this->getAttribute('order_id'),
                'project_name'      => $projectName,
                'payment_method'    => $this->getAttribute('payment_method'),
                'payment_data'      => '',
                'payment_time'      => '',
                'payment_address'   => '',
                'delivery_address'  => '',
                'time_for_payment'  => $timeForPayment,
                'paid_status'       => '',
                'paid_date'         => '',
                'paid_data'         => '',
                'canceled'          => '',
                'date'              => $date,
                'data'              => '',
                'articles'          => $this->Articles->toJSON(),
                'customer_data'     => '', // @todo 'history'           => '',
                'isbrutto'          => '',
                'currency_data'     => json_encode($listCalculations['currencyData']),
                'nettosum'          => $listCalculations['nettoSum'],
                'subsum'            => $listCalculations['subSum'],
                'sum'               => $listCalculations['sum'],
                'vat_data'          => json_encode($listCalculations['vatArray']),
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

        // check all current data

        // user and user-address check
        $customerId = $this->getAttribute('customer_id');
        $addressId  = $this->getAttribute('address_id');

        $Customer = QUI::getUsers()->get($customerId);
        $Address  = $Customer->getAddress($addressId);


        // create invoice
    }

    //region to* Methods

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

    /**
     * Output the invoice as HTML
     *
     * @return string
     */
    public function toHTML()
    {
        return $this->getHTMLHeader() .
               $this->getHTMLBody() .
               $this->getHTMLFooter();
    }

    /**
     * Output the invoice as PDF Document
     *
     * @return QUI\HtmlToPdf\Document
     */
    public function toPDF()
    {
        $localeCode = QUI::getLocale()->getLocalesByLang(
            QUI::getLocale()->getCurrent()
        );

        $Formatter = new \IntlDateFormatter(
            $localeCode[0],
            \IntlDateFormatter::MEDIUM,
            \IntlDateFormatter::SHORT
        );

        $date = $Formatter->format(time());
        $date = preg_replace('/[^0-9]/', '_', $date);

        $fileName = QUI::getLocale()->get('quiqqer/invoice', 'pdf.export.name') . '_';
        $fileName .= $date;
        $fileName .= '.pdf';

        $Document = new QUI\HtmlToPdf\Document(array(
            'marginTop' => 30,
            'filename'  => $fileName
        ));

        try {
            $Document->setHeaderHTML($this->getHTMLHeader());
            $Document->setContentHTML($this->getHTMLHeader());
            $Document->setFooterHTML($this->getHTMLFooter());
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        return $Document;
    }

    //endregion

    //region Template Output Helper

    /**
     * @return QUI\Interfaces\Template\EngineInterface
     */
    protected function getHTMLEngine()
    {
        $Engine = QUI::getTemplateManager()->getEngine();

        $customerId = $this->getAttribute('customer_id');
        $addressId  = $this->getAttribute('address_id');

        $Customer = QUI::getUsers()->get($customerId);
        $Address  = $Customer->getAddress($addressId);

        // list calculation
        $Calc = new QUI\ERP\Accounting\Calc($Customer);

        $this->Articles->calc($Calc);

        $Engine->assign(array(
            'this'        => $this,
            'ArticleList' => $this->Articles,
            'Customer'    => $Customer,
            'Address'     => $Address
        ));

        return $Engine;
    }

    /**
     * Return the html header
     *
     * @return string
     */
    protected function getHTMLHeader()
    {
        $Engine = $this->getHTMLEngine();
        $path   = QUI::getPackage('quiqqer/invoice')->getDir();

        return $Engine->fetch($path . '/template/header.html');
    }

    /**
     * Return the html body
     *
     * @return string
     */
    protected function getHTMLBody()
    {
        $Engine = $this->getHTMLEngine();
        $path   = QUI::getPackage('quiqqer/invoice')->getDir();

        return $Engine->fetch($path . '/template/body.html');
    }

    /**
     * Return the html body
     *
     * @return string
     */
    protected function getHTMLFooter()
    {
        $Engine = $this->getHTMLEngine();
        $path   = QUI::getPackage('quiqqer/invoice')->getDir();

        return $Engine->fetch($path . '/template/footer.html');
    }

    //endregion

    //region Article Management Methods

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
}
