/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/product/AddProductWindow
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/product/AddProductWindow', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'qui/utils/Form',
    'package/quiqqer/products/bin/controls/products/search/Window',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/invoice/bin/backend/controls/panels/product/AddProductWindow.ProductSettings.html',
    'css!package/quiqqer/invoice/bin/backend/controls/panels/product/AddProductWindow.css'

], function (QUI, QUIControl, QUIConfirm, QUIFormUtils, ProductSearch, QUIAjax, Mustache,
             templateProductSettings) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/panels/product/AddProductWindow',

        options: {
            user: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$user = {};

            if (typeof options.user !== 'undefined') {
                this.$user = options.user;
            }
        },

        /**
         * open and start the product selection
         */
        open: function () {
            var self = this;

            new ProductSearch({
                autoclose: false,
                events   : {
                    onSubmit: function (Win, products) {
                        var productId = products[0];

                        Win.Loader.show();

                        self.$hasProductCustomFields(productId).then(function (hasCustomFields) {
                            return Win.close().then(function () {
                                if (!hasCustomFields) {
                                    return Promise.resolve(false);
                                }
                                return self.openProductSettings(productId);
                            });
                        }).then(function (productSettings) {
                            return self.$parseProductToArticle(productId, productSettings);
                        }).then(function (article) {
                            self.fireEvent('submit', [self, article]);
                        }).catch(function (err) {
                            console.error(err);
                        });
                    }
                }
            }).open();
        },

        /**
         * Opens the product settings for a product
         *
         * @param productId
         * @returns {Promise}
         */
        openProductSettings: function (productId) {
            var self = this;

            return new Promise(function (resolve) {
                new QUIConfirm({
                    title    : 'Produkt Eigenschaften', // #locale
                    icon     : 'fa fa-shopping-bag',
                    maxHeight: 500,
                    maxWidth : 500,
                    events   : {
                        onOpen: function (Win) {
                            Win.Loader.show();

                            var Content = Win.getContent();
                            Content.set('html', Mustache.render(templateProductSettings));
                            Content.addClass('quiqqer-invoice-addProductWin');

                            var Form  = Content.getElement('form');
                            var Table = Form.getElement('table tbody');

                            var Row = new Element('tr', {
                                html: '<td><label class="field-container"></label></td>'
                            });

                            self.$getProductEdit(productId).then(function (result) {
                                var Ghost = new Element('div', {
                                    html: result
                                });

                                var Header = Ghost.getElement('header');

                                if (Header) {
                                    Header.getElements('.quiqqer-products-productEdit-header-image').destroy();
                                    Header.inject(Form, 'top');
                                }

                                Ghost.getElements('.quiqqer-product-field').each(function (Field) {
                                    var RowClone = Row.clone();
                                    var Label    = RowClone.getElement('label');

                                    Label.set('html', Field.get('html'));

                                    Label.getElement('.quiqqer-product-field-title')
                                         .addClass('field-container-item');

                                    var Value = Label.getElement('.quiqqer-product-field-value');
                                    var Input = Value.getElement('input,select');

                                    if (Input) {
                                        Input.replaces(Value);
                                        Input.addClass('field-container-field');
                                    } else {
                                        Label.getElement('.quiqqer-product-field-value')
                                             .addClass('field-container-field');
                                    }

                                    RowClone.inject(Table);
                                });

                                QUI.parse(Form).then(function () {
                                    return Win.Loader.hide();
                                });
                            }).catch(function (err) {
                                console.error(err);
                            });
                        },

                        onSubmit: function (Win) {
                            var Form = Win.getContent().getElement('form');
                            var data = QUIFormUtils.getFormData(Form);

                            resolve(data);
                        },

                        onCancel: function () {
                            resolve(false);
                        }
                    }
                }).open();
            });
        },

        /**
         * Return the user data
         *
         * @returns {{}|*}
         */
        getUserData: function () {
            return this.$user;
        },

        /**
         * Return the data of a product for an ERP article
         *
         * @param {String|Number} productId
         * @param {Object} [attributes] - fields, quantity and so on
         * @returns {Promise}
         */
        $parseProductToArticle: function (productId, attributes) {
            var self = this;

            attributes = attributes || {};

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_temporary_product_parseProductToArticle', resolve, {
                    'package' : 'quiqqer/invoice',
                    productId : productId,
                    attributes: JSON.encode(attributes),
                    user      : JSON.encode(self.getUserData()),
                    onError   : reject
                });
            });
        },

        /**
         * Return the product edit
         *
         * @param {String|Number} productId
         * @returns {Promise}
         */
        $getProductEdit: function (productId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_temporary_product_getProductEdit', resolve, {
                    'package': 'quiqqer/invoice',
                    productId: productId,
                    user     : JSON.encode(this.getUserData()),
                    onError  : reject
                });
            }.bind(this));
        },

        /**
         * Has the product custom fields
         *
         * @param productId
         * @returns {Promise}
         */
        $hasProductCustomFields: function (productId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_temporary_product_hasProductCustomFields', resolve, {
                    'package': 'quiqqer/invoice',
                    productId: productId,
                    onError  : reject
                });
            }.bind(this));
        }
    });
});