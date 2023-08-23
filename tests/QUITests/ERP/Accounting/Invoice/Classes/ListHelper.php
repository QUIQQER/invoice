<?php

/**
 * This file contains QUITests\ERP\Accounting\Invoice\Classes\ListHelper
 */

namespace QUITests\ERP\Accounting\Invoice\Classes;

use QUI;

/**
 * Class ProductListHelper
 * @package QUITests\ERP\Products\CaseStudies\Classes
 */
class ListHelper
{
    /**
     * Ausgabe einer produkt liste für phpunit
     *
     * @param QUI\ERP\Accounting\ArticleList $List
     */
    public static function outputList(QUI\ERP\Accounting\ArticleList $List)
    {
        $data = $List->toArray();
        $User = $List->getUser();
        $Locale = $User->getLocale();
        $Currency = QUI\ERP\Currency\Handler::getDefaultCurrency();

        $calculations = $data['calculations'];

        writePhpUnitMessage('Preis Liste');
        writePhpUnitMessage('== ' . ($calculations['isNetto'] ? 'netto' : 'brutto') . ' =========================');
        writePhpUnitMessage();

        writePhpUnitMessage('Produkte');
        writePhpUnitMessage('------');
        writePhpUnitMessage();

        foreach ($data['articles'] as $article) {
            writePhpUnitMessage($article['quantity'] . 'x ' . $article['title']);
            writePhpUnitMessage('-------');

            writePhpUnitMessage('Grundpreis: ' . $Currency->format($article['unitPrice'], $Locale));
            writePhpUnitMessage();

            writePhpUnitMessage(
                '    Calc Netto Sum: ' . $Currency->format($article['calculated']['nettoSum'], $Locale)
            );
            writePhpUnitMessage('    Calc Price: ' . $Currency->format($article['calculated']['price'], $Locale));
            writePhpUnitMessage('    Calc Sum: ' . $Currency->format($article['calculated']['sum'], $Locale));

            writePhpUnitMessage(
                '    -> Vat ' . $article['calculated']['vatArray']['vat'] . '% : ' .
                $article['calculated']['vatArray']['text'] . ' ' .
                $Currency->format($article['calculated']['vatArray']['sum'], $Locale)
            );

            writePhpUnitMessage();
        }

        writePhpUnitMessage();

        writePhpUnitMessage('NettoSubSum: ' . $Currency->format($calculations['nettoSubSum'], $Locale));
        writePhpUnitMessage('SubSum: ' . $Currency->format($calculations['subSum'], $Locale));
        writePhpUnitMessage();
        writePhpUnitMessage();

        // rabatte
        writePhpUnitMessage('Rabatte');
        writePhpUnitMessage('------');
        writePhpUnitMessage();
        writePhpUnitMessage('TODO');

//        $priceFactore = $List->getPriceFactors()->sort();
//
//        /* @var QUI\ERP\Products\Utils\PriceFactor $PriceFactor */
//        foreach ($priceFactore as $PriceFactor) {
//            writePhpUnitMessage('    ' . $PriceFactor->getTitle() . ': ' . $PriceFactor->getValueFormated());
//        }

        writePhpUnitMessage();
        writePhpUnitMessage();

        writePhpUnitMessage('Berechnung');
        writePhpUnitMessage('------');
        writePhpUnitMessage();

        writePhpUnitMessage('nettoSum: ' . $Currency->format($calculations['nettoSum'], $Locale));
        writePhpUnitMessage();
        writePhpUnitMessage('    MwSt.:');

        foreach ($calculations['vatArray'] as $vatEntry) {
            writePhpUnitMessage('    - ' . $vatEntry['text'] . ': ' . $Currency->format($vatEntry['sum'], $Locale));
        }

        writePhpUnitMessage();
        writePhpUnitMessage('Sum: ' . $Currency->format($calculations['sum'], $Locale));
        writePhpUnitMessage();
        writePhpUnitMessage();
    }
}
