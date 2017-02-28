/**
 * @module package/quiqqer/invoice/bin/backend/controls/InvoiceItems
 *
 * Invoice item list (Produkte Positionen)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Mustache
 * @require Locale
 * @require package/quiqqer/invoice/bin/backend/controls/InvoiceItemsProduct
 * @require text!package/quiqqer/invoice/bin/backend/controls/InvoiceItems.html
 * @require css!package/quiqqer/invoice/bin/backend/controls/InvoiceItems.css
 */
define('package/quiqqer/invoice/bin/backend/controls/InvoiceItems', [

    'qui/QUI',
    'qui/controls/Control',
    'Mustache',
    'Locale',
    'package/quiqqer/invoice/bin/backend/controls/articles/Article',

    'text!package/quiqqer/invoice/bin/backend/controls/InvoiceItems.html',
    'css!package/quiqqer/invoice/bin/backend/controls/InvoiceItems.css'

], function (QUI, QUIControl, Mustache, QUILocale, Article, template) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/InvoiceItems',

        options: {},

        initialize: function (options) {
            this.parent(options);

            this.$articles = [];

            this.$Container = null;
        },

        /**
         * Create the DOMNode element
         *
         * @returns {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();

            this.$Elm.addClass('quiqqer-invoice-backend-invoiceItems');

            this.$Elm.set({
                html: Mustache.render(template, {
                    titleArticleNo  : QUILocale.get(lg, 'invoice.products.articleNo'),
                    titleDescription: QUILocale.get(lg, 'invoice.products.description'),
                    titleQuantity   : QUILocale.get(lg, 'invoice.products.quantity'),
                    titleUnitPrice  : QUILocale.get(lg, 'invoice.products.unitPrice'),
                    titlePrice      : QUILocale.get(lg, 'invoice.products.price'),
                    titleVAT        : QUILocale.get(lg, 'invoice.products.vat'),
                    titleSum        : QUILocale.get(lg, 'invoice.products.sum')
                })
            });

            this.$Container = this.$Elm.getElement('.quiqqer-invoice-backend-invoiceItems-items');

            return this.$Elm;
        },

        /**
         * Serialize the list
         *
         * @returns {Object}
         */
        serialize: function () {
            var articles = this.$articles.map(function (Article) {
                var attr     = Article.getAttributes();
                attr.control = typeOf(Article);

                return attr;
            });

            return {
                articles: articles
            };
        },

        /**
         *
         */
        unserialize: function (list) {
            var data = {};

            if (typeOf(list) == 'string') {
                try {
                    data = JSON.stringify(list);
                } catch (e) {
                }
            } else {
                data = list;
            }

            if (!("articles" in data)) {
                return;
            }

            var needles = data.articles.map(function (entry) {
                return entry.control;
            });

            require(needles, function () {
                var i, no, len, article, control;

                for (i = 0, len = data.articles.length; i < len; i++) {
                    article = data.articles[i];
                    control = article.control;

                    no = needles.indexOf(control);

                    this.addArticle(
                        new arguments[no](article)
                    );
                }
            }.bind(this));
        },

        /**
         * Add a product to the list
         * The product must be an instance of InvoiceItemsProduc
         *
         * @param {Object} Product
         */
        addArticle: function (Product) {
            if (typeof Product !== 'object') {
                return;
            }

            if (!(Product instanceof Article)) {
                return;
            }

            this.$articles.push(Product);

            Product.setPosition(this.$articles.length);
            Product.inject(this.$Container);
        },

        /**
         * Insert a new empty product
         */
        insertNewProduct: function () {
            this.addArticle(new Article());
        },

        /**
         * Return the articles as an array
         *
         * @return {Array}
         */
        save: function () {
            console.log('###');

            return this.$articles.map(function (Article) {
                console.log(typeOf(Article));
                console.log(Article.getAttributes());

                return Article.getAttributes();
            });
        }
    });
});