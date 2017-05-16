/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/InvoiceSummary
 *
 * Displays a posted Invoice
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Mustache
 * @require text!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.Summary.html
 * @require css!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.Summary.css
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
            List  : null,
            styles: false
        },

        Binds: [
            '$onInject',
            '$refreshArticleSelect'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$NettoSum  = null;
            this.$BruttoSum = null;

            this.$Formatter = QUILocale.getNumberFormatter({
                style                : 'currency',
                currency             : 'EUR',
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

                self.$Formatter = QUILocale.getNumberFormatter({
                    style                : 'currency',
                    currency             : data.currencyData.code,
                    minimumFractionDigits: 2
                });

                self.$NettoSum.set('html', self.$Formatter.format(data.nettoSum));
                self.$BruttoSum.set('html', self.$Formatter.format(data.sum));


                var key, Entry;
                var vatText = '';

                for (key in data.vatArray) {
                    Entry = data.vatArray[key];

                    if (!Entry.sum) {
                        continue;
                    }

                    vatText = vatText + Entry.text + ' (' + self.$Formatter.format(Entry.sum) + ')<br />';
                }

                self.$VAT.set('html', vatText);
            });

            List.addEvent('onArticleSelect', this.$refreshArticleSelect);
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
                    self.$Formatter.format(result.calculated_nettoSum)
                );

                self.$ArticleBruttoSum.set(
                    'html',
                    self.$Formatter.format(result.calculated_sum)
                );
            });
        }
    });
});
