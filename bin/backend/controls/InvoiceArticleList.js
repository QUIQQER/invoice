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
 *
 * @event onCalc [self, {Object} calculation]
 * @event onArticleSelect [self, {Object} Article]
 * @event onArticleUnSelect [self, {Object} Article]
 */
define('package/quiqqer/invoice/bin/backend/controls/InvoiceArticleList', [

    'qui/QUI',
    'qui/controls/Control',
    'Mustache',
    'Ajax',
    'Locale',
    'package/quiqqer/invoice/bin/backend/controls/articles/Article',

    'text!package/quiqqer/invoice/bin/backend/controls/InvoiceArticleList.html',
    'css!package/quiqqer/invoice/bin/backend/controls/InvoiceArticleList.css'

], function (QUI, QUIControl, Mustache, QUIAjax, QUILocale, Article, template) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/InvoiceArticleList',

        Binds: [
            '$onArticleDelete',
            '$onArticleSelect',
            '$onArticleUnSelect',
            '$calc'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$articles = [];
            this.$user     = {};

            this.$calculationTimer = null;

            this.$calculations = {
                currencyData: {},
                isEuVat     : 0,
                isNetto     : true,
                nettoSubSum : 0,
                nettoSum    : 0,
                subSum      : 0,
                sum         : 0,
                vatArray    : [],
                vatText     : []
            };

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
                    titleVAT        : QUILocale.get(lg, 'invoice.products.table.vat'),
                    titleDiscount   : QUILocale.get(lg, 'invoice.products.discount'),
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

            this.$articles.each(function (Article) {
                Article.setUser(this.$user);
            }.bind(this));
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
                onDelete  : this.$onArticleDelete,
                onSelect  : this.$onArticleSelect,
                onUnSelect: this.$onArticleUnSelect,
                onCalc    : this.$calc
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
            return this.$articles.map(function (Article) {
                return Article.getAttributes();
            });
        },

        /**
         * Calculate the list
         */
        $calc: function () {
            if (this.$calculationTimer) {
                clearTimeout(this.$calculationTimer);
                this.$calculationTimer = null;
            }

            var self = this;

            this.$calculationTimer = (function () {
                self.$executeCalculation();
            }).delay(500);
        },

        /**
         * Execute a new calculation
         *
         * @returns {Promise}
         */
        $executeCalculation: function () {
            var self = this;

            return new Promise(function (resolve) {
                var articles = self.$articles.map(function (Article) {
                    return Article.getAttributes();
                });

                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_temporary_calc', function (result) {
                    self.$calculations = result;
                    self.fireEvent('calc', [self, result]);
                    resolve(result);
                }, {
                    'package': 'quiqqer/invoice',
                    articles : JSON.encode(articles),
                    user     : JSON.encode(self.$user)
                });
            });
        },

        /**
         * Return the current calculations
         *
         * @returns {{currencyData: {}, isEuVat: number, isNetto: boolean, nettoSubSum: number, nettoSum: number, subSum: number, sum: number, vatArray: Array, vatText: Array}|*}
         */
        getCalculation: function () {
            return this.$calculations;
        },

        /**
         * Events
         */


        /**
         * event : on article delete
         *
         * @param {Object} Article
         */
        $onArticleDelete: function (Article) {
            if (this.$selectedArticle) {
                this.$selectedArticle.unselect();
            }

            var i, len, Current;

            var self     = this,
                articles = [],
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
            this.$executeCalculation().then(function () {
                if (self.$articles.length) {
                    self.$articles[0].select();
                }
            });
        },

        /**
         * event : on article delete
         *
         * @param {Object} Article
         */
        $onArticleSelect: function (Article) {
            if (this.$selectedArticle) {
                this.$selectedArticle.unselect();
            }

            this.$selectedArticle = Article;
            this.fireEvent('articleSelect', [this, this.$selectedArticle]);
        },

        /**
         * event : on article delete
         *
         * @param Article
         */
        $onArticleUnSelect: function (Article) {
            if (this.$selectedArticle === Article) {
                this.$selectedArticle = null;
                this.fireEvent('articleUnSelect', [this, this.$selectedArticle]);
            }
        },

        /**
         * Return the current selected Article
         *
         * @returns {null|Object}
         */
        getSelectedArticle: function () {
            return this.$selectedArticle;
        }
    });
});