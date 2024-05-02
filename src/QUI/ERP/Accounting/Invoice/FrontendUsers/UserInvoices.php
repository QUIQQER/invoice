<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\FrontendUsers\UserInvoices
 */

namespace QUI\ERP\Accounting\Invoice\FrontendUsers;

use QUI;
use QUI\Control;
use QUI\FrontendUsers\Controls\Profile\ControlInterface;

use function dirname;

/**
 * Class UserInvoices
 * - Invoice list of
 *
 * @package QUI\ERP\Order\FrontendUsers\Controls
 */
class UserInvoices extends Control implements ControlInterface
{
    /**
     * UserOrders constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->addCSSClass('quiqqer-invoice-profile-invoices');
        $this->addCSSFile(dirname(__FILE__) . '/UserInvoices.css');

        $this->setAttributes([
            'page' => 1,
            'limit' => 5
        ]);

        parent::__construct($attributes);
    }

    /**
     * @return string
     *
     * @throws QUI\Exception
     */
    public function getBody(): string
    {
        $Engine = QUI::getTemplateManager()->getEngine();
        $User = QUI::getUserBySession();
        $Search = QUI\ERP\Accounting\Invoice\Search\InvoiceSearch::getInstance();
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();

        if ($this->getAttribute('User')) {
            $User = $this->getAttribute('User');
        }

        if (!$this->getAttribute('limit')) {
            $this->setAttribute('limit', 5);
        }

        $limit = (int)$this->getAttribute('limit');
        $sheetCurrent = (int)$this->getAttribute('page');
        $start = ($sheetCurrent - 1) * $limit;

        $Search->limit($start, $start + $limit);
        $Search->setFilter('customer_id', $User->getUUID());

        $result = $Search->search();
        $invoices = [];

        foreach ($result as $entry) {
            try {
                $invoices[] = $Invoices->get($entry['id'])->getView();
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        $Engine->assign([
            'invoices' => $invoices
        ]);

        return $Engine->fetch(dirname(__FILE__) . '/UserInvoices.html');
    }

    /**
     * @return QUI\Interfaces\Projects\Site
     * @throws QUI\Exception
     */
    public function getSite(): QUI\Interfaces\Projects\Site
    {
        if ($this->getAttribute('Site')) {
            return $this->getAttribute('Site');
        }

        $Site = QUI::getRewrite()->getSite();
        $this->setAttribute('Site', $Site);

        return $Site;
    }

    /**
     * event: on save
     */
    public function onSave(): void
    {
    }

    public function validate(): void
    {
    }
}
