<?php

namespace QUITests\ERP\Accounting\Invoice;

require_once dirname(__FILE__) . '/Classes/ListHelper.php';

use QUI;
use QUITests\ERP\Accounting\Invoice\Classes\ListHelper;

/**
 * Class BruttoUserTest
 */
class BruttoTest extends \PHPUnit_Framework_TestCase
{
    public function testBrutto()
    {
        writePhpUnitMessage('/*********************************/');
        writePhpUnitMessage('      Brutto Nutzer');
        writePhpUnitMessage('/*********************************/');
        writePhpUnitMessage();

        $NettoUser = new QUI\ERP\User(array(
            'id'        => 0,
            'country'   => 'DE',
            'username'  => 'user',
            'firstname' => 'Markus',
            'lastname'  => 'Baumgartner',
            'lang'      => 'de',
            'isCompany' => 1
        ));

        $NettoUser->setAttribute('quiqqer.erp.isNettoUser', 0);

        $List = new QUI\ERP\Accounting\ArticleList();
        $List->setUser($NettoUser);

        $List->addArticle(
            new QUI\ERP\Accounting\Article(array(
                'id'          => 10,
                'articleNo'   => 'ART001',
                'title'       => 'Artikel 1',
                'description' => 'Artikel Desc',
                'unitPrice'   => 10,
                'quantity'    => 2,
                'vat'         => 19
            ))
        );


        $List->addArticle(
            new QUI\ERP\Accounting\Article(array(
                'id'          => 11,
                'articleNo'   => 'ART002',
                'title'       => 'Artikel 2',
                'description' => 'Artikel Desc',
                'unitPrice'   => 10,
                'quantity'    => 3,
                'vat'         => 7
            ))
        );

        $List->calc();

        ListHelper::outputList($List);
    }
}
