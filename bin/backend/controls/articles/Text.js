/**
 * @module package/quiqqer/invoice/bin/backend/controls/articles/Text
 *
 * Text Produkt
 * - Dieses "Produkt" benhaltet nur text und hat keine Summe oder Preise
 * - Dieses Produkt wird verwendet für Hinweise auf der Rechnung
 *
 * @require qui/QUI
 * @require qui/controls/Control
 */
define('package/quiqqer/invoice/bin/backend/controls/articles/Text', [

    'package/quiqqer/invoice/bin/backend/controls/articles/Article',
    'qui/controls/buttons/Button',
    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/invoice/bin/backend/controls/articles/Text.html',
    'css!package/quiqqer/invoice/bin/backend/controls/articles/Text.css'

], function (InvoiceArticle, QUIButton, QUILocale, QUIAjax, Mustache, template) {
    "use strict";

    var lg = 'quiqqer/invoice';
    
    return new Class({

        Extends: InvoiceArticle,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/articles/Text',

        Binds: [
            '$onEditTitle',
            '$onEditDescription'
        ],

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                'class': 'QUI\\ERP\\Accounting\\Invoice\\Articles\\Text'
            });
        },

        /**
         * Create the DOMNode element
         *
         * @returns {HTMLDivElement}
         */
        create: function () {
            this.$Elm = new Element('div');

            this.$Elm.addClass('quiqqer-invoice-backend-invoiceArticleText');

            this.$Elm.set({
                html  : Mustache.render(template),
                events: {
                    click: this.select
                }
            });

            this.$Position = this.$Elm.getElement('.quiqqer-invoice-backend-invoiceArticleText-pos');
            this.$Text     = this.$Elm.getElement('.quiqqer-invoice-backend-invoiceArticleText-text');
            this.$Buttons  = this.$Elm.getElement('.quiqqer-invoice-backend-invoiceArticleText-buttons');

            this.$Loader = new Element('div', {
                html  : '<span class="fa fa-spinner fa-spin"></span>',
                styles: {
                    background: '#fff',
                    display   : 'none',
                    left      : 0,
                    padding   : 10,
                    position  : 'absolute',
                    top       : 0,
                    width     : '100%'
                }
            }).inject(this.$Position);

            new Element('span').inject(this.$Position);

            if (this.getAttribute('position')) {
                this.setPosition(this.getAttribute('position'));
            }

            // text nodes
            this.$Title = new Element('div', {
                'class': 'quiqqer-invoice-backend-invoiceArticleText-text-title cell-editable'
            }).inject(this.$Text);

            this.$Description = new Element('div', {
                'class': 'quiqqer-invoice-backend-invoiceArticleText-text-description cell-editable'
            }).inject(this.$Text);

            this.$Title.addEvent('click', this.$onEditTitle);
            this.$Description.addEvent('click', this.$onEditDescription);

            this.setTitle(this.getAttribute('title'));
            this.setDescription(this.getAttribute('description'));

            // edit buttons
            new QUIButton({
                title : QUILocale.get(lg, 'invoice.articleList.article.button.replace'),
                icon  : 'fa fa-retweet',
                styles: {
                    'float': 'none'
                },
                events: {
                    onClick: this.$onReplaceClick
                }
            }).inject(this.$Buttons);

            new QUIButton({
                title : QUILocale.get(lg, 'invoice.articleList.article.button.delete'),
                icon  : 'fa fa-trash',
                styles: {
                    'float': 'none'
                },
                events: {
                    onClick: this.openDeleteDialog
                }
            }).inject(this.$Buttons);

            this.$created = true;

            return this.$Elm;
        },

        /**
         * Calculates nothing
         * Text Article has no prices
         *
         * @return {Promise}
         */
        calc: function () {
            return Promise.resolve();
        },

        /**
         * Set the product title
         *
         * @param {String} title
         */
        setTitle: function (title) {
            this.setAttribute('title', title);
            this.$Title.set('html', title);

            if (title === '') {
                this.$Title.set('html', '&nbsp;');
            }
        },

        /**
         * Set the product description
         *
         * @param {String} description
         */
        setDescription: function (description) {
            this.setAttribute('description', description);
            this.$Description.set('html', description);

            if (description === '') {
                this.$Description.set('html', '&nbsp;');
            }
        },

        /**
         * Set the product quantity
         *
         * @return {Promise}
         */
        setQuantity: function () {
            return Promise.resolve();
        },

        /**
         * Set the product unit price
         *
         */
        setUnitPrice: function () {
            return Promise.resolve();
        },

        /**
         * Set the product unit price
         **/
        setVat: function () {
            return Promise.resolve();
        },

        /**
         * Show the loader
         */
        showLoader: function () {
            this.$Loader.setStyle('display', null);
        },

        /**
         * Hide the loader
         */
        hideLoader: function () {
            this.$Loader.setStyle('display', 'none');
        }
    });
});
