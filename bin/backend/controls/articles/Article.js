/**
 * @module package/quiqqer/invoice/bin/backend/controls/articles/Article
 *
 * Freies Produkt
 * - Dieses Produkt kann vom Benutzer komplett selbst bestimmt werden
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @require Mustache
 * @require Locale
 * @require Ajax
 * @require Editors
 * @require text!package/quiqqer/invoice/bin/backend/controls/articles/Article.html
 * @require css!package/quiqqer/invoice/bin/backend/controls/articles/Article.css
 *
 * @event onCalc [self]
 * @event onSelect [self]
 * @event onUnSelect [self]
 *
 * @event onDelete [self]
 * @event onRemove [self]
 * @event onDrop [self]
 */
define('package/quiqqer/invoice/bin/backend/controls/articles/Article', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/windows/Confirm',
    'Mustache',
    'Locale',
    'Ajax',
    'Editors',

    'text!package/quiqqer/invoice/bin/backend/controls/articles/Article.html',
    'css!package/quiqqer/invoice/bin/backend/controls/articles/Article.css'

], function (QUI, QUIControl, QUIButton, QUIConfirm, Mustache, QUILocale, QUIAjax, Editors, template) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/articles/Article',

        Binds: [
            '$onEditTitle',
            '$onEditDescription',
            '$onEditQuantity',
            '$onEditArticleNo',
            '$onEditUnitPriceQuantity',
            '$onEditVat',
            'openDeleteDialog',
            'remove',
            'select'
        ],

        options: {
            position   : 0,
            articleNo  : '',
            title      : '---',
            description: '---',
            quantity   : 1,
            unitPrice  : 0,
            price      : 0,
            vat        : '',
            params     : false, // mixed value for API Articles
            type       : 'QUI\\ERP\\Accounting\\Invoice\\Articles\\Article'
        },

        initialize: function (options) {
            this.setAttributes(this.__proto__.options); // set the default values
            this.parent(options);

            this.$user         = {};
            this.$calculations = {};

            this.$Position  = null;
            this.$Quantity  = null;
            this.$UnitPrice = null;
            this.$Price     = null;
            this.$VAT       = null;
            this.$Total     = null;

            this.$Text        = null;
            this.$Title       = null;
            this.$Description = null;
            this.$Editor      = null;

            this.$Loader  = null;
            this.$created = false;

            // admin format
            this.$Formatter = QUILocale.getNumberFormatter({
                style   : 'currency',
                currency: 'EUR'
            });
        },

        /**
         * Create the DOMNode element
         *
         * @returns {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();

            this.$Elm.addClass('quiqqer-invoice-backend-invoiceArticle');

            this.$Elm.set({
                html      : Mustache.render(template),
                'tabindex': -1,
                styles    : {
                    outline: 'none'
                },
                events    : {
                    click: this.select
                }
            });

            this.$Position  = this.$Elm.getElement('.quiqqer-invoice-backend-invoiceArticle-pos');
            this.$ArticleNo = this.$Elm.getElement('.quiqqer-invoice-backend-invoiceArticle-articleNo');
            this.$Text      = this.$Elm.getElement('.quiqqer-invoice-backend-invoiceArticle-text');
            this.$Quantity  = this.$Elm.getElement('.quiqqer-invoice-backend-invoiceArticle-quantity');
            this.$UnitPrice = this.$Elm.getElement('.quiqqer-invoice-backend-invoiceArticle-unitPrice');
            this.$Price     = this.$Elm.getElement('.quiqqer-invoice-backend-invoiceArticle-price');
            this.$VAT       = this.$Elm.getElement('.quiqqer-invoice-backend-invoiceArticle-vat');
            this.$Total     = this.$Elm.getElement('.quiqqer-invoice-backend-invoiceArticle-total');
            this.$Buttons   = this.$Elm.getElement('.quiqqer-invoice-backend-invoiceArticle-buttons');

            this.$ArticleNo.addEvent('click', this.$onEditArticleNo);
            this.$Quantity.addEvent('click', this.$onEditQuantity);
            this.$UnitPrice.addEvent('click', this.$onEditUnitPriceQuantity);
            this.$VAT.addEvent('click', this.$onEditVat);

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


            this.$Title = new Element('div', {
                'class': 'quiqqer-invoice-backend-invoiceArticle-text-title cell-editable'
            }).inject(this.$Text);

            this.$Description = new Element('div', {
                'class': 'quiqqer-invoice-backend-invoiceArticle-text-description cell-editable'
            }).inject(this.$Text);

            this.$Title.addEvent('click', this.$onEditTitle);
            this.$Description.addEvent('click', this.$onEditDescription);

            this.setArticleNo(this.getAttribute('articleNo'));
            this.setVat(this.getAttribute('vat'));
            this.setTitle(this.getAttribute('title'));
            this.setDescription(this.getAttribute('description'));

            this.setQuantity(this.getAttribute('quantity'));
            this.setUnitPrice(this.getAttribute('unitPrice'));

            // edit buttons
            new QUIButton({
                title : 'Artikel ersetzen', // #locale
                icon  : 'fa fa-retweet',
                styles: {
                    'float': 'none'
                },
                events: {
                    onClick: this.$openReplaceDialog
                }
            }).inject(this.$Buttons);

            new QUIButton({
                title : 'Artikel löschen', // #locale
                icon  : 'fa fa-trash',
                styles: {
                    'float': 'none'
                },
                events: {
                    onClick: this.openDeleteDialog
                }
            }).inject(this.$Buttons);

            this.$created = true;
            this.calc();

            return this.$Elm;
        },

        /**
         * Deletes the article and destroy the Node
         *
         * @fires onDelete [self]
         * @fires onRemove [self]
         * @fires onDrop [self]
         */
        remove: function () {
            this.fireEvent('delete', [this]);
            this.fireEvent('remove', [this]);
            this.fireEvent('drop', [this]);

            this.destroy();
        },

        /**
         * Set the user data for the article
         *
         * @param {Object} user
         */
        setUser: function (user) {
            this.$user = user;
        },

        /**
         * Calculates the total price of the invoice and refresh the display
         *
         * @return {Promise}
         */
        calc: function () {
            var self = this;

            if (!this.$created) {
                return Promise.resolve();
            }

            this.showLoader();

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_temporary_product_calc', function (product) {
                    var total     = self.$Formatter.format(product.calculated_sum);
                    var unitPrice = self.$Formatter.format(product.calculated_basisPrice);
                    var price     = self.$Formatter.format(product.calculated_price);

                    self.$calculations = product;

                    self.$Total.set({
                        html : total,
                        title: total
                    });

                    self.$UnitPrice.set({
                        html : unitPrice,
                        title: unitPrice
                    });

                    self.$Price.set({
                        html : price,
                        title: price
                    });

                    self.hideLoader();

                    resolve(product);

                    self.fireEvent('calc', [self]);
                }, {
                    'package': 'quiqqer/invoice',
                    onError  : reject,
                    params   : JSON.encode(self.getAttributes()),
                    user     : JSON.encode(self.$user)
                });
            });
        },

        /**
         *
         * @returns {{}|*}
         */
        getCalculations: function () {
            return this.$calculations;
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
         * Set the Article-No
         *
         * @param {String} articleNo
         */
        setArticleNo: function (articleNo) {
            this.setAttribute('articleNo', articleNo);
            this.$ArticleNo.set('html', articleNo);

            if (articleNo === '') {
                this.$ArticleNo.set('html', '&nbsp;');
            }
        },

        /**
         * Set the product position
         *
         * @param {Number} pos
         */
        setPosition: function (pos) {
            this.setAttribute('position', parseInt(pos));

            if (this.$Position) {
                this.$Position.getChildren('span').set('html', this.getAttribute('position'));
            }
        },

        /**
         * Set the product quantity
         *
         * @param {Number} quantity
         * @return {Promise}
         */
        setQuantity: function (quantity) {
            this.setAttribute('quantity', parseInt(quantity));

            if (this.$Quantity) {
                this.$Quantity.set('html', this.getAttribute('quantity'));
            }

            return this.calc();
        },

        /**
         * Set the product unit price
         *
         * @param {Number} price
         * @return {Promise}
         */
        setUnitPrice: function (price) {
            this.setAttribute('unitPrice', parseFloat(price));

            if (this.$UnitPrice) {
                this.$UnitPrice.set('html', this.getAttribute('unitPrice'));
            }

            return this.calc();
        },

        /**
         * Set the product unit price
         *
         * @param {Number|String} vat
         * @return {Promise}
         */
        setVat: function (vat) {
            vat = parseInt(vat);

            if (vat > 100 || vat < 0) {
                return Promise.resolve();
            }

            this.setAttribute('vat', vat);

            if (this.$VAT) {
                vat = this.getAttribute('vat');

                if (!vat) {
                    vat = '-';
                } else {
                    vat = vat + '%';
                }

                this.$VAT.set('html', vat);
            }

            return this.calc();
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
        },

        /**
         * select the article
         */
        select: function () {
            this.$Elm.addClass('quiqqer-invoice-backend-invoiceArticle-select');
            this.fireEvent('select', [this]);
        },

        /**
         * unselect the article
         */
        unselect: function () {
            this.$Elm.removeClass('quiqqer-invoice-backend-invoiceArticle-select');
            this.fireEvent('unSelect', [this]);
        },

        /**
         * Dialogs
         */

        /**
         *
         */
        openDeleteDialog: function () {
            new QUIConfirm({
                icon       : 'fa fa-trash',
                texticon   : 'fa fa-trash',
                title      : QUILocale.get(lg, 'dialog.delete.article.title'),
                information: QUILocale.get(lg, 'dialog.delete.article.information'),
                text       : QUILocale.get(lg, 'dialog.delete.article.text'),
                maxHeight  : 400,
                maxWidth   : 600,
                events     : {
                    onSubmit: this.remove.bind(this)
                }
            }).open();
        },

        /**
         * edit event methods
         */

        /**
         * event : on title edit
         */
        $onEditTitle: function () {
            this.$createEditField(
                this.$Title,
                this.getAttribute('title')
            ).then(function (value) {
                this.setTitle(value);
            }.bind(this));
        },

        /**
         * event : on description edit
         */
        $onEditDescription: function () {
            if (this.$Editor) {
                return;
            }

            var self = this;

            this.showLoader();

            var EditorContainer = new Element('div', {
                    'class': 'quiqqer-invoice-backend-invoiceArticle-text-description-edit'
                }).inject(this.$Description),

                EditorParent    = new Element('div', {
                    styles: {
                        height : 'calc(100% - 50px)',
                        opacity: 0
                    }
                }).inject(EditorContainer),

                EditorSubmit    = new Element('div', {
                    'class': 'quiqqer-invoice-backend-invoiceArticle-text-description-buttons'
                }).inject(EditorContainer);


            var closeEditor = function () {
                moofx(EditorContainer).animate({
                    opacity: 0
                }, {
                    duration: 200,
                    callback: function () {
                        self.$Description.setStyle('height', null);
                        self.$Editor.destroy();
                        EditorContainer.destroy();

                        self.$Editor = null;
                    }
                });
            };

            new QUIButton({
                text     : QUILocale.get('quiqqer/system', 'accept'),
                textimage: 'fa fa-check',
                styles   : {
                    'float': 'none',
                    margin : '10px 5px 0 5px'
                },
                events   : {
                    onClick: function () {
                        this.setDescription(this.$Editor.getContent());
                        closeEditor();
                    }.bind(this)
                }
            }).inject(EditorSubmit);

            new QUIButton({
                text  : QUILocale.get('quiqqer/system', 'cancel'),
                styles: {
                    'float': 'none',
                    margin : '10px 5px 0 5px'
                },
                events: {
                    onClick: closeEditor
                }
            }).inject(EditorSubmit);

            var height = 300;

            if (this.$Description.getSize().y > 300) {
                height = this.$Description.getSize().y;
            }

            moofx(this.$Description).animate({
                height: height
            }, {
                duration: 200,
                callback: function () {

                    Editors.getEditor(null).then(function (Editor) {
                        this.$Editor = Editor;

                        // minimal toolbar
                        this.$Editor.setAttribute('buttons', {
                            lines: [
                                [[
                                    {
                                        type  : "button",
                                        button: "Bold"
                                    },
                                    {
                                        type  : "button",
                                        button: "Italic"
                                    },
                                    {
                                        type  : "button",
                                        button: "Underline"
                                    },
                                    {
                                        type: "separator"
                                    },
                                    {
                                        type  : "button",
                                        button: "RemoveFormat"
                                    },
                                    {
                                        type: "separator"
                                    },
                                    {
                                        type  : "button",
                                        button: "NumberedList"
                                    },
                                    {
                                        type  : "button",
                                        button: "BulletedList"
                                    }
                                ]]
                            ]
                        });

                        this.$Editor.addEvent('onLoaded', function () {
                            this.$Editor.switchToWYSIWYG();
                            this.$Editor.showToolbar();
                            this.$Editor.setContent(this.getAttribute('description'));

                            this.hideLoader();

                            moofx(EditorParent).animate({
                                opacity: 1
                            }, {
                                duration: 200
                            });
                        }.bind(this));

                        this.$Editor.inject(EditorParent);
                        this.$Editor.setHeight(200);

                    }.bind(this));
                }.bind(this)
            });
        },

        /**
         * event: on Article-Number edit
         */
        $onEditArticleNo: function () {
            this.$createEditField(
                this.$ArticleNo,
                this.getAttribute('articleNo')
            ).then(function (value) {
                this.setArticleNo(value);
            }.bind(this));
        },

        /**
         * event : on quantity edit
         */
        $onEditQuantity: function () {
            this.$createEditField(
                this.$Quantity,
                this.getAttribute('quantity'),
                'number'
            ).then(function (value) {
                this.setQuantity(value);
            }.bind(this));
        },

        /**
         * event : on quantity edit
         */
        $onEditUnitPriceQuantity: function () {
            this.$createEditField(
                this.$UnitPrice,
                this.getAttribute('unitPrice'),
                'number'
            ).then(function (value) {
                this.setUnitPrice(value);
            }.bind(this));
        },


        $onEditVat: function () {
            this.$createEditField(
                this.$VAT,
                this.getAttribute('vat'),
                'number'
            ).then(function (value) {
                this.setVat(value);
            }.bind(this));
        },

        /**
         * Creates a input field to edt the product field value
         *
         * @param {HTMLDivElement} Container
         * @param {String} [value] - preselected value
         * @param {String} [type] - edit input type
         * @returns {Promise}
         */
        $createEditField: function (Container, value, type) {
            type = type || 'text';

            return new Promise(function (resolve) {
                var Edit = new Element('input', {
                    type  : type,
                    value : value,
                    styles: {
                        border    : 0,
                        left      : 0,
                        lineHeight: 20,
                        height    : '100%',
                        padding   : 0,
                        position  : 'absolute',
                        top       : 0,
                        width     : '100%'
                    }
                }).inject(Container);

                Edit.focus();

                var onFinish = function () {
                    Edit.destroy();
                    resolve(Edit.value);
                };

                Edit.addEvents({
                    click: function (event) {
                        event.stop();
                    },

                    keydown: function (event) {
                        if (event.key === 'enter') {
                            onFinish();
                        }
                    },

                    blur: onFinish
                });
            });
        }
    });
});