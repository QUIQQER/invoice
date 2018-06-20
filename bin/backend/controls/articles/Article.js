/**
 * @module package/quiqqer/invoice/bin/backend/controls/articles/Article
 * @author www.pcsg.de (Henning Leutz)
 *
 * Freies Produkt
 * - Dieses Produkt kann vom Benutzer komplett selbst bestimmt werden
 *
 * @event onCalc [self]
 * @event onSelect [self]
 * @event onUnSelect [self]
 *
 * @event onDelete [self]
 * @event onRemove [self]
 * @event onDrop [self]
 *
 * @event onSetTitle [self]
 * @event onSetDescription [self]
 * @event onSetPosition [self]
 * @event onSetQuantity [self]
 * @event onSetUnitPrice [self]
 * @event onSetVat [self]
 * @event onEditKeyDown [self, event]
 */
define('package/quiqqer/invoice/bin/backend/controls/articles/Article', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/windows/Confirm',
    'qui/utils/Elements',
    'package/quiqqer/erp/bin/backend/utils/Discount',
    'package/quiqqer/erp/bin/backend/utils/Money',
    'Mustache',
    'Locale',
    'Ajax',
    'Editors',

    'text!package/quiqqer/invoice/bin/backend/controls/articles/Article.html',
    'css!package/quiqqer/invoice/bin/backend/controls/articles/Article.css'

], function (QUI, QUIControl, QUIButton, QUIConfirm, QUIElements, DiscountUtils, MoneyUtils, Mustache, QUILocale, QUIAjax, Editors, template) {
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
            '$onEditDiscount',
            'openDeleteDialog',
            '$onReplaceClick',
            '$editNext',
            'remove',
            'select'
        ],

        options: {
            articleNo  : '',
            description: '---',
            discount   : '-',
            position   : 0,
            price      : 0,
            quantity   : 1,
            title      : '---',
            unitPrice  : 0,
            vat        : '',
            'class'    : 'QUI\\ERP\\Accounting\\Invoice\\Articles\\Article',
            params     : false // mixed value for API Articles
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

            // discount
            if (options && "discount" in options) {
                this.setAttribute(
                    'discount',
                    DiscountUtils.parseToString(
                        DiscountUtils.unserialize(options.discount)
                    )
                );
            }

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
            this.$Discount  = this.$Elm.getElement('.quiqqer-invoice-backend-invoiceArticle-discount');
            this.$Total     = this.$Elm.getElement('.quiqqer-invoice-backend-invoiceArticle-total');
            this.$Buttons   = this.$Elm.getElement('.quiqqer-invoice-backend-invoiceArticle-buttons');

            this.$ArticleNo.addEvent('click', this.$onEditArticleNo);
            this.$Quantity.addEvent('click', this.$onEditQuantity);
            this.$UnitPrice.addEvent('click', this.$onEditUnitPriceQuantity);
            this.$VAT.addEvent('click', this.$onEditVat);
            this.$Discount.addEvent('click', this.$onEditDiscount);

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
            this.setDiscount(this.getAttribute('discount'));

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
            this.calc();

            this.addEvent('onEditKeyDown', function (me, event) {
                if (event.key === 'tab') {
                    this.$editNext(event);
                }
            }.bind(this));

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
         * Trigger the replace event
         */
        $onReplaceClick: function () {
            this.fireEvent('replace', [this]);
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
                    var unitPrice = self.$Formatter.format(product.unitPrice);
                    var price     = self.$Formatter.format(product.calculated.nettoSubSum);
                    var total     = self.$Formatter.format(product.calculated.nettoSum);

                    self.$calculations = product;

                    var setElement = function (Node, text) {
                        var isInEditMode = Node.getElement('input');

                        if (isInEditMode) {
                            Node.set({
                                title: text
                            });
                            return;
                        }

                        Node.set({
                            html : text,
                            title: text
                        });
                    };

                    setElement(self.$Total, total);
                    setElement(self.$UnitPrice, unitPrice);
                    setElement(self.$Price, price);
                    setElement(self.$VAT, product.vat + '%');

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
         * Return the current calculations
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

            this.fireEvent('setTitle', [this]);
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

            this.fireEvent('setDescription', [this]);
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

            this.fireEvent('setArticleNo', [this]);
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

            this.fireEvent('setPosition', [this]);
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

            this.fireEvent('setQuantity', [this]);

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

            this.fireEvent('setUnitPrice', [this]);

            return this.calc();
        },

        /**
         * Set the product unit price
         *
         * @param {Number|String} vat
         * @return {Promise}
         */
        setVat: function (vat) {
            if (vat === '-' || vat === '') {
                this.setAttribute('vat', '');
                this.$VAT.set('html', '-');

                return this.calc();
            }

            vat = parseInt(vat);

            if (vat > 100 || vat < 0) {
                return Promise.resolve();
            }

            this.setAttribute('vat', vat);

            if (this.$VAT) {
                vat = this.getAttribute('vat');
                vat = vat + '%';

                this.$VAT.set('html', vat);
            }

            this.fireEvent('setVat', [this]);

            return this.calc();
        },

        /**
         * Set the discount
         *
         * @param {String|Number} discount - 100 = 100€, 100€ = 100€ or 10% =  calculation
         */
        setDiscount: function (discount) {
            var self  = this,
                value = '',
                type  = '';

            if (discount === '' || !discount) {
                discount = '-';
            }

            if (typeOf(discount) === 'string' && discount.indexOf('%') !== -1) {
                type = '%';
            }

            var Prom;

            if (discount && type === '%') {
                Prom = Promise.resolve(discount);
            } else if (discount) {
                Prom = MoneyUtils.validatePrice(discount);
            } else {
                Prom = Promise.resolve('-');
            }

            return Prom.then(function (discount) {
                if (discount && type === '%') {
                    discount = (discount).toString().replace(/\%/g, '') + type;
                    value    = discount;
                } else if (discount) {
                    value = self.$Formatter.format(discount) + type;
                } else {
                    value = '-';
                }

                self.setAttribute('discount', discount);
                self.$Discount.set('html', value);
            }).then(this.calc.bind(this));
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
            if (!this.$Elm) {
                return;
            }

            if (this.$Elm.hasClass('quiqqer-invoice-backend-invoiceArticle-select')) {
                return;
            }

            this.$Elm.addClass('quiqqer-invoice-backend-invoiceArticle-select');
            this.fireEvent('select', [this]);
        },

        /**
         * unselect the article
         */
        unselect: function () {
            if (!this.$Elm) {
                return;
            }

            this.$Elm.removeClass('quiqqer-invoice-backend-invoiceArticle-select');
            this.fireEvent('unSelect', [this]);
        },

        /**
         * Dialogs
         */

        /**
         * Opens the delete dialog
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

            new QUIConfirm({
                title    : QUILocale.get(lg, 'dialog.edit.description.title', {
                    articleNo   : this.getAttribute('articleNo'),
                    articleTitle: this.getAttribute('title')
                }),
                icon     : 'fa fa-edit',
                maxHeight: 600,
                maxWidth : 800,
                events   : {
                    onOpen  : function (Win) {
                        Win.Loader.show();
                        Win.getContent().set('html', '');

                        Editors.getEditor(null).then(function (Editor) {
                            self.$Editor = Editor;

                            // minimal toolbar
                            self.$Editor.setAttribute('buttons', {
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

                            self.$Editor.addEvent('onLoaded', function () {
                                self.$Editor.switchToWYSIWYG();
                                self.$Editor.showToolbar();
                                self.$Editor.setContent(self.getAttribute('description'));

                                Win.Loader.hide();
                            });

                            self.$Editor.inject(Win.getContent());
                            self.$Editor.setHeight(200);
                        });
                    },
                    onSubmit: function () {
                        self.setDescription(self.$Editor.getContent());
                    },
                    onClose : function () {
                        self.$Editor.destroy();
                        self.$Editor = null;
                    }
                }
            }).open();
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

        /**
         * event: on edit VAT
         */
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
         * event: on edit discount
         */
        $onEditDiscount: function () {
            var discount = this.getAttribute('discount');

            if (discount === '-' || discount === false || !discount) {
                discount = '';
            } else if (!discount.toString().match('%')) {
                discount = parseFloat(discount);
            }

            this.$createEditField(
                this.$Discount,
                discount
            ).then(function (value) {
                this.setDiscount(value);
            }.bind(this));
        },

        /**
         * Creates a input field to edt the product field value
         *
         * @param {HTMLDivElement} Container
         * @param {String} [value] - preselected value
         * @param {String} [type] - edit input type
         * @param {Object} [inputAttributes] - input attributes
         * @returns {Promise}
         */
        $createEditField: function (Container, value, type, inputAttributes) {
            var self = this;

            type = type || 'text';

            return new Promise(function (resolve) {
                var Edit = new Element('input', {
                    type  : type,
                    value : value,
                    styles: {
                        border    : 0,
                        left      : 0,
                        lineHeight: 20,
                        //height    : '100%',
                        textAlign : 'right',
                        padding   : 10,
                        position  : 'absolute',
                        top       : 0,
                        width     : '100%'
                    }
                }).inject(Container);

                if (typeof inputAttributes !== 'undefined') {
                    Edit.set(inputAttributes);
                }

                Edit.focus();
                Edit.select();

                var onFinish = function () {
                    Edit.destroy();
                    resolve(Edit.value);
                };

                Edit.addEvents({
                    click: function (event) {
                        event.stop();
                    },

                    keydown: function (event) {
                        self.fireEvent('editKeyDown', [self, event]);

                        if (event.key === 'enter') {
                            self.$editNext(event);
                            return;
                        }

                        if (event.key === 'esc') {
                            onFinish();
                        }
                    },

                    blur: onFinish
                });
            });
        },

        /**
         * Opens the next edit field
         *
         * @param event
         */
        $editNext: function (event) {
            var Target = event.target,
                Cell   = Target.getParent('.cell');

            if (!Cell) {
                return;
            }

            var Next;

            if (event.shift) {
                Next = Cell.getPrevious('.cell-editable');
            } else {
                Next = Cell.getNext('.cell-editable');
            }

            if (Next) {
                event.stop();

                QUIElements.simulateEvent(Next, 'click');
            }
        }
    });
});