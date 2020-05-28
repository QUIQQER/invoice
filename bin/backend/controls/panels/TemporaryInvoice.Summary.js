/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/InvoiceSummary
 * @author www.pcsg.de (Henning Leutz)
 *
 * Displays a posted Invoice
 *
 * @todo move to erp package
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.Summary', [

    'qui/QUI',
    'qui/controls/Control',
    'Mustache',
    'Locale',
    'package/quiqqer/invoice/bin/Invoices',

    'text!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.Summary.html',
    'css!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.Summary.css'

], function (QUI, QUIControl, Mustache, QUILocale, Invoices, template) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.Summary',

        options: {
            List    : null,
            styles  : false,
            currency: 'EUR'
        },

        Binds: [
            '$onInject',
            '$refreshArticleSelect',
            'openSummary'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$NettoSum  = null;
            this.$BruttoSum = null;

            this.$Formatter = QUILocale.getNumberFormatter({
                style                : 'currency',
                currency             : this.getAttribute('currency'),
                minimumFractionDigits: 2
            });

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * Create the domnode element
         *
         * @returns {HTMLDivElement}
         */
        create: function () {
            this.$Elm = new Element('div', {
                'class': 'quiqqer-invoice-backend-temporaryInvoice-summary',
                html   : Mustache.render(template)
            });

            this.$Elm.addEvent('click', this.openSummary);

            this.$NettoSum = this.$Elm.getElement(
                '.quiqqer-invoice-backend-temporaryInvoice-summary-total .netto-value'
            );

            this.$BruttoSum = this.$Elm.getElement(
                '.quiqqer-invoice-backend-temporaryInvoice-summary-total .brutto-value'
            );

            this.$VAT = this.$Elm.getElement(
                '.quiqqer-invoice-backend-temporaryInvoice-summary-total-vat .vat-value'
            );

            this.$ArticleNettoSum = this.$Elm.getElement(
                '.quiqqer-invoice-backend-temporaryInvoice-summary-pos .netto-value'
            );

            this.$ArticleBruttoSum = this.$Elm.getElement(
                '.quiqqer-invoice-backend-temporaryInvoice-summary-pos .brutto-value'
            );

            if (this.getAttribute('styles')) {
                this.setStyles(this.getAttribute('styles'));
            }

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var List = this.getAttribute('List');

            if (!List) {
                return;
            }

            var self = this;

            List.addEvent('onCalc', function (List) {
                var data = List.getCalculation();

                self.$NettoSum.set('html', self.$Formatter.format(data.nettoSum));
                self.$BruttoSum.set('html', self.$Formatter.format(data.sum));

                if (typeOf(data.vatArray) === 'array' && !data.vatArray.length) {
                    self.$VAT.set('html', '---');
                    return;
                }

                var key, Entry;
                var vatText = '';

                for (key in data.vatArray) {
                    if (!data.vatArray.hasOwnProperty(key)) {
                        continue;
                    }

                    Entry = data.vatArray[key];

                    if (typeof Entry.sum === 'undefined') {
                        Entry.sum = 0;
                    }

                    if (typeof Entry.text === 'undefined') {
                        Entry.text = '';
                    }

                    if (Entry.text === '') {
                        Entry.text = '';
                    }

                    Entry.sum = parseFloat(Entry.sum);

                    vatText = vatText + Entry.text + ' (' + self.$Formatter.format(Entry.sum) + ')<br />';
                }

                self.$VAT.set('html', vatText);
            });

            List.addEvent('onArticleSelect', this.$refreshArticleSelect);
        },

        /**
         * Open the summary with price factors
         */
        openSummary: function () {
            if (!this.getAttribute('List')) {
                return;
            }

            var self = this;

            require(['qui/controls/windows/Popup'], function (Popup) {
                new Popup({
                    title    : QUILocale.get('quiqqer/erp', 'article.summary.window.title'),
                    buttons  : false,
                    maxHeight: 600,
                    maxWidth : 600,
                    events   : {
                        onCreate: function (Win) {
                            Win.Loader.show();

                            self.$refreshSummaryContent(Win).then(function () {
                                Win.Loader.hide();
                            });
                        }
                    }
                }).open();
            });
        },

        $refreshSummaryContent: function (Win) {
            var self = this;

            return new Promise(function (resolve) {
                var Content      = Win.getContent();
                var List         = self.getAttribute('List');
                var priceFactors = List.getPriceFactors();
                var calculations = List.getCalculation();

                for (var i = 0, len = priceFactors.length; i < len; i++) {
                    priceFactors[i].index = i;
                }

                Content.set('html', '');

                require([
                    'text!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.Summary.Window.html'
                ], function (template) {
                    Content.set('html', Mustache.render(template, {
                        priceFactors: priceFactors,
                        vatArray    : Object.values(calculations.vatArray)
                    }));

                    var Total = Content.getElement('.quiqqer-invoice-backend-temporaryInvoice-summaryWin-total');

                    Total.getElement('.netto-value').set('html', calculations.nettoSum);
                    Total.getElement('.brutto-value').set('html', calculations.sum);

                    Content.getElements(
                        '.quiqqer-invoice-backend-temporaryInvoice-summaryWin-priceFactors'
                    ).addEvent('click', function (event) {
                        var index = event.target.getParent('tr').get('data-index');

                        List.removePriceFactor(index);
                        self.$refreshSummaryContent(Win);
                    });

                    resolve();
                });
            });
        },

        /**
         * event: onArticleSelect
         *
         * @param List
         * @param Article
         */
        $refreshArticleSelect: function (List, Article) {
            var self = this;

            Invoices.getArticleSummary(Article.getAttributes()).then(function (result) {
                self.$ArticleNettoSum.set(
                    'html',
                    self.$Formatter.format(result.calculated.nettoSum)
                );

                self.$ArticleBruttoSum.set(
                    'html',
                    self.$Formatter.format(result.calculated.sum)
                );
            });
        }
    });
});
