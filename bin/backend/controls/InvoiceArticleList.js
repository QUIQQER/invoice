/**
 * @module package/quiqqer/invoice/bin/backend/controls/InvoiceArticleList
 * @author www.pcsg.de (Henning Leutz)
 *
 * Invoice item list (Produkte Positionen)
 *
 * @event onCalc [self, {Object} calculation]
 * @event onArticleSelect [self, {Object} Article]
 * @event onArticleUnSelect [self, {Object} Article]
 * @event onArticleReplaceClick [self, {Object} Article]
 */
define('package/quiqqer/invoice/bin/backend/controls/InvoiceArticleList', [

    'qui/QUI',
    'qui/controls/Control',
    'Mustache',
    'Ajax',
    'Locale',
    'package/quiqqer/invoice/bin/backend/controls/articles/Article',
    'package/quiqqer/invoice/bin/backend/classes/Sortable',

    'text!package/quiqqer/invoice/bin/backend/controls/InvoiceArticleList.html',
    'text!package/quiqqer/invoice/bin/backend/controls/InvoiceArticleList.sortablePlaceholder.html',
    'css!package/quiqqer/invoice/bin/backend/controls/InvoiceArticleList.css'

], function (QUI, QUIControl, Mustache, QUIAjax, QUILocale, Article, Sortables, template, templateSortablePlaceholder) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/InvoiceArticleList',

        Binds: [
            '$onArticleDelete',
            '$onArticleSelect',
            '$onArticleUnSelect',
            '$onArticleReplace',
            '$calc',
            '$onInject'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$articles = [];
            this.$user     = {};
            this.$sorting  = false;

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
            this.$Sortables = null;

            this.addEvents({
                onInject: this.$onInject
            });
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

            if (this.getAttribute('styles')) {
                this.setStyles(this.getAttribute('styles'));
            }

            this.$Container = this.$Elm.getElement('.quiqqer-invoice-backend-invoiceItems-items');

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            (function () {
                if (this.$articles.length) {
                    this.$articles[0].select();
                }
            }).delay(500, this);
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
         *
         * load the serialized list into
         * current articles would be deleted
         *
         * @param {Object|String} list
         * @return {Promise}
         */
        unserialize: function (list) {
            var self = this,
                data = {};

            if (typeOf(list) === 'string') {
                try {
                    data = JSON.stringify(list);
                } catch (e) {
                }
            } else {
                data = list;
            }

            if (!("articles" in data)) {
                return Promise.resolve();
            }

            this.$articles = [];

            var controls = data.articles.map(function (Article) {
                return Article.control;
            }).unique();

            require(controls, function () {
                var i, len, article, index;

                for (i = 0, len = data.articles.length; i < len; i++) {
                    article = data.articles[i];
                    index   = controls.indexOf(article.control);

                    if (index === -1) {
                        self.addArticle(new Article(article));
                        continue;
                    }

                    self.addArticle(new arguments[index](article));
                }
            });
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
         * Return the user details
         *
         * @return {Object|*|{}}
         */
        getUser: function () {
            return this.$user;
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
                onReplace : this.$onArticleReplace,
                onCalc    : this.$calc
            });

            Child.inject(this.$Container);
            Child.getElm().addClass('article');
        },

        /**
         * Replace an article with another
         *
         * @param {Object} NewArticle
         * @param {Number} position
         */
        replaceArticle: function (NewArticle, position) {
            if (typeof NewArticle !== 'object') {
                return;
            }

            if (!(NewArticle instanceof Article)) {
                return;
            }

            var Wanted = this.$articles.find(function (Article) {
                return Article.getAttribute('position') === position;
            });

            this.addArticle(NewArticle);

            if (Wanted) {
                NewArticle.getElm().inject(Wanted.getElm(), 'after');
                Wanted.remove();
            }

            NewArticle.setPosition(position);

            this.$recalculatePositions();

            return this.$calc();
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
                return Object.merge(Article.getAttributes(), {
                    control: Article.getType()
                });
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
         * Calc
         */

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
         * Sorting
         */

        /**
         * Toggles the sorting
         */
        toggleSorting: function () {
            if (this.$sorting) {
                this.disableSorting();
                return;
            }

            this.enableSorting();
        },

        /**
         * Enables the sorting
         * Articles can be sorted by drag and drop
         */
        enableSorting: function () {
            var self = this;

            var Elm      = this.getElm(),
                elements = Elm.getElements('.article');

            elements.each(function (Node) {
                var Article    = QUI.Controls.getById(Node.get('data-quiid'));
                var attributes = Article.getAttributes();

                Article.addEvents({
                    onSetPosition: self.$onArticleSetPosition
                });

                new Element('div', {
                    'class': 'quiqqer-invoice-sortableClone-placeholder',
                    html   : Mustache.render(templateSortablePlaceholder, attributes)
                }).inject(Node);
            });


            this.$Sortables = new Sortables(this.$Container, {
                revert: {
                    duration  : 500,
                    transition: 'elastic:out'
                },

                clone: function (event) {
                    var Target = event.target;

                    if (!Target.hasClass('article')) {
                        Target = Target.getParent('.article');
                    }

                    var size = Target.getSize(),
                        pos  = Target.getPosition(self.$Container);

                    return new Element('div', {
                        styles: {
                            background: 'rgba(0,0,0,0.5)',
                            height    : size.y,
                            position  : 'absolute',
                            top       : pos.y,
                            width     : size.x,
                            zIndex    : 1000
                        }
                    });
                },

                onStart: function (element) {
                    element.addClass('quiqqer-invoice-sortableClone');

                    self.$Container.setStyles({
                        height  : self.$Container.getSize().y,
                        overflow: 'hidden',
                        width   : self.$Container.getSize().x
                    });
                },

                onComplete: function (element) {
                    element.removeClass('quiqqer-invoice-sortableClone');

                    self.$Container.setStyles({
                        height  : null,
                        overflow: null,
                        width   : null
                    });

                    self.$recalculatePositions();
                }
            });

            this.$sorting = true;
        },

        /**
         * Disables the sorting
         * Articles can not be sorted
         */
        disableSorting: function () {
            this.$sorting = false;

            var self     = this,
                Elm      = this.getElm(),
                elements = Elm.getElements('.article');

            Elm.getElements('.quiqqer-invoice-sortableClone-placeholder').destroy();

            elements.each(function (Node) {
                var Article = QUI.Controls.getById(Node.get('data-quiid'));

                Article.removeEvents({
                    onSetPosition: self.$onArticleSetPosition
                });
            });

            this.$Sortables.detach();
            this.$Sortables = null;

            this.$articles.sort(function (A, B) {
                return A.getAttribute('position') - B.getAttribute('position');
            });
        },

        /**
         * Is the sorting enabled?
         *
         * @return {boolean}
         */
        isSortingEnabled: function () {
            return this.$sorting;
        },

        /**
         * event: on set position at article
         *
         * @param Article
         */
        $onArticleSetPosition: function (Article) {
            Article.getElm()
                   .getElement('.quiqqer-invoice-backend-invoiceArticlePlaceholder-pos')
                   .set('html', Article.getAttribute('position'));
        },

        /**
         * Recalculate the Position of all Articles
         */
        $recalculatePositions: function () {
            var i, len, Article;

            var Elm      = this.getElm(),
                elements = Elm.getElements('.article');

            for (i = 0, len = elements.length; i < len; i++) {
                Article = QUI.Controls.getById(elements[i].get('data-quiid'));
                Article.setPosition(i + 1);
            }
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
            if (this.$selectedArticle &&
                this.$selectedArticle !== Article) {
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
         * event : on article replace click
         *
         * @param Article
         */
        $onArticleReplace: function (Article) {
            this.fireEvent('articleReplaceClick', [this, Article]);
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