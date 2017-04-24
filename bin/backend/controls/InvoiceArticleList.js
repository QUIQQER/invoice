/**
 * @module package/quiqqer/invoice/bin/backend/controls/InvoiceArticleList
 *
 * Invoice item list (Produkte Positionen)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Mustache
 * @require Locale
 * @require package/quiqqer/invoice/bin/backend/controls/article/Article
 * @require text!package/quiqqer/invoice/bin/backend/controls/InvoiceArticleList.html
 * @require css!package/quiqqer/invoice/bin/backend/controls/InvoiceArticleList.css
 */
define('package/quiqqer/invoice/bin/backend/controls/InvoiceArticleList', [

    'qui/QUI',
    'qui/controls/Control',
    'Mustache',
    'Locale',
    'package/quiqqer/invoice/bin/backend/controls/articles/Article',

    'text!package/quiqqer/invoice/bin/backend/controls/InvoiceArticleList.html',
    'css!package/quiqqer/invoice/bin/backend/controls/InvoiceArticleList.css'

], function (QUI, QUIControl, Mustache, QUILocale, Article, template) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/InvoiceArticleList',

        Binds: [
            '$onArticleDelete'
        ],

        options: {},

        initialize: function (options) {
            this.parent(options);

            this.$articles = [];
            this.$user     = {};

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
         * Unserialize the list
         * load the serialized list into list
         *
         * @param {Object|String} list
         */
        unserialize: function (list) {
            var data = {};

            if (typeOf(list) === 'string') {
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

            var article;

            for (var i = 0, len = data.articles.length; i < len; i++) {
                article = data.articles[i];

                this.addArticle(new Article(article));
            }
        },

        /**
         * Set user details to the list
         *
         * @param {Object} user
         */
        setUser: function (user) {
            this.$user = user;
        },

        /**
         * Add a product to the list
         * The product must be an instance of Article
         *
         * @param {Object} Child
         */
        addArticle: function (Child) {
            if (typeof Child !== 'object') {
                return;
            }

            if (!(Child instanceof Article)) {
                return;
            }

            this.$articles.push(Child);

            Child.setUser(this.$user);
            Child.setPosition(this.$articles.length);

            Child.addEvents({
                onDelete: this.$onArticleDelete
            });

            Child.inject(this.$Container);
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
            // console.log('###');

            return this.$articles.map(function (Article) {
                // console.log(typeOf(Article));
                // console.log(Article.getAttributes());

                return Article.getAttributes();
            });
        },

        /**
         * Events
         */


        /**
         * event : on article delete
         */
        $onArticleDelete: function (Article) {
            var i, len, Current;

            var articles = [],
                position = 1;

            for (i = 0, len = this.$articles.length; i < len; i++) {
                if (this.$articles[i].getAttribute('position') === Article.getAttribute('position')) {
                    continue;
                }

                Current = this.$articles[i];
                Current.setPosition(position);
                articles.push(Current);

                position++;
            }

            this.$articles = articles;
        }
    });
});