/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice
 *
 * Edit a Temporary Invoice and created a posted invoice
 *
 * @require qui/QUI
 * @require qui/controls/desktop/Panel
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/ButtonMultiple',
    'qui/controls/buttons/Seperator',
    'qui/controls/windows/Confirm',
    'package/quiqqer/invoice/bin/Invoices',
    'Locale'

], function (QUI, QUIPanel, QUIButton, QUIButtonMultiple, QUISeparator, QUIConfirm, Invoices, QUILocale) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIPanel,
        Type: 'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice',

        Binds: [
            'openData',
            'openProducts',
            'openVerification',
            '$openCategory',
            '$closeCategory',
            '$onCreate',
            '$onInject',
            '$onDestroy',
            '$onDeleteInvoice',
            '$clickDelete'
        ],

        options: {
            invoiceId: false
        },

        initialize: function (options) {
            this.setAttributes({
                icon: 'fa fa-money'
            });

            this.parent(options);

            this.$ProductList = null;
            this.$AddProduct = null;
            this.$AddSeparator = null;

            this.addEvents({
                onCreate: this.$onCreate,
                onInject: this.$onInject,
                onDestroy: this.$onDestroy
            });

            Invoices.addEvents({
                onDeleteInvoice: this.$onDeleteInvoice
            });
        },

        /**
         * Open the data category
         *
         * @returns {Promise}
         */
        openData: function () {
            var self = this;

            this.Loader.show();

            return this.$closeCategory().then(function () {


                self.Loader.hide();
            }).then(function () {
                return self.$openCategory();
            });
        },

        /**
         * Open the product category
         *
         * @returns {Promise}
         */
        openProducts: function () {
            this.Loader.show();

            return this.$closeCategory().then(function (Container) {
                return new Promise(function (resolve) {
                    require([
                        'package/quiqqer/invoice/bin/backend/controls/InvoiceItems'
                    ], function (InvoiceItems) {
                        this.$ProductList = new InvoiceItems({
                            events: {}
                        }).inject(Container);

                        this.$AddProduct.show();
                        this.$AddSeparator.show();
                        this.Loader.hide();
                        resolve();
                    }.bind(this));
                }.bind(this));
            }.bind(this)).then(function () {
                return this.$openCategory();
            }.bind(this));
        },

        /**
         * Open the verification category
         *
         * @returns {Promise}
         */
        openVerification: function () {
            var self = this;

            this.Loader.show();

            return this.$closeCategory().then(function () {

                this.Loader.hide();
            }.bind(this)).then(function () {
                return self.$openCategory();
            });
        },

        /**
         * Opens the product search
         */
        openProductSearch: function () {
            this.$AddProduct.setAttribute('textimage', 'fa fa-spinner fa-spin');

            require([
                'package/quiqqer/products/bin/controls/products/search/Window',
                'package/quiqqer/products/bin/controls/invoice/Product'
            ], function (ProductSearch, Product) {
                new ProductSearch({
                    events: {
                        onSubmit: function (Win, products) {
                            for (var i = 0, len = products.length; i < len; i++) {
                                this.$ProductList.addProduct(
                                    new Product({
                                        productId: products[i]
                                    })
                                );
                            }
                        }.bind(this)
                    }
                }).open();

                this.$AddProduct.setAttribute('textimage', 'fa fa-plus');
            }.bind(this));
        },

        /**
         * Close the current category
         *
         * @returns {Promise}
         */
        $closeCategory: function () {
            if (this.$AddProduct) {
                this.$AddProduct.hide();
                this.$AddSeparator.hide();
            }

            return new Promise(function (resolve) {
                var Container = this.getContent().getElement('.container');

                if (!Container) {
                    Container = new Element('div', {
                        'class': 'container',
                        styles: {
                            opacity: 0,
                            position: 'relative',
                            top: -50
                        }
                    }).inject(this.getContent());
                }

                moofx(Container).animate({
                    opacity: 0,
                    top: -50
                }, {
                    duration: 200,
                    callback: function () {
                        if (this.$ProductList) {
                            this.$ProductList.destroy();
                            this.$ProductList = null;
                        }

                        Container.set('html', '');

                        resolve(Container);
                    }.bind(this)
                });
            }.bind(this));
        },

        /**
         * Open the current category
         *
         * @returns {Promise}
         */
        $openCategory: function () {
            var self = this;

            return new Promise(function (resolve) {
                var Container = self.getContent().getElement('.container');

                if (!Container) {
                    resolve();
                    return;
                }

                moofx(Container).animate({
                    opacity: 1,
                    top: 0
                }, {
                    duration: 200,
                    callback: resolve
                });
            });
        },

        /**
         * Event Handling
         */

        /**
         * event: on create
         */
        $onCreate: function () {
            var self = this;

            this.$AddProduct = new QUIButtonMultiple({
                textimage: 'fa fa-plus',
                text: 'Produkt hinzufügen',
                events: {
                    onClick: function () {
                        if (self.$ProductList) {
                            self.openProductSearch();
                        }
                    }
                }
            });

            this.$AddProduct.hide();

            this.$AddProduct.appendChild({
                text: 'Freies Produkt',
                events: {
                    onClick: function () {
                        if (self.$ProductList) {
                            self.$ProductList.insertNewProduct();
                        }
                    }
                }
            });

            this.$AddProduct.appendChild({
                text: 'Text',
                events: {
                    onClick: function () {
                        if (self.$ProductList) {

                        }
                    }
                }
            });

            this.$AddSeparator = new QUISeparator();

            // buttons
            this.addButton({
                text: QUILocale.get('quiqqer/system', 'save'),
                textimage: 'fa fa-save',
                events: {
                    onClick: function () {
                    }
                }
            });

            this.addButton(this.$AddSeparator);
            this.addButton(this.$AddProduct);

            this.addButton({
                icon: 'fa fa-trash',
                styles: {
                    'float': 'right'
                },
                events: {
                    onClick: this.$clickDelete
                }
            });

            // categories
            this.addCategory({
                icon: 'fa fa-info',
                text: 'Rechnungsdaten',
                events: {
                    onClick: this.openData
                }
            });

            this.addCategory({
                icon: 'fa fa-list',
                text: 'Positionen (Produkte)',
                events: {
                    onClick: this.openProducts
                }
            });

            this.addCategory({
                icon: 'fa fa-check',
                text: 'Überprüfung',
                events: {
                    onClick: this.openVerification
                }
            });
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            this.Loader.show();

            if (!this.getAttribute('invoiceId')) {
                this.destroy();
                return;
            }

            Invoices.getTemporaryInvoice(this.getAttribute('invoiceId')).then(function (data) {
                this.setAttribute('title', data.id);

                this.refresh();
                this.getCategoryBar().firstChild().click();
                this.Loader.hide();

            }.bind(this)).catch(function (Exception) {
                QUI.getMessageHandler().then(function (MH) {
                    console.error(Exception);
                    MH.addError(Exception.getMessage());
                });

                this.destroy();
            }.bind(this));
        },

        /**
         * event: on panel destroy
         */
        $onDestroy: function () {
            Invoices.removeEvents({
                onDeleteInvoice: this.$onDeleteInvoice
            });
        },

        /**
         * opens the delete dialog
         */
        $clickDelete: function () {
            var self = this;

            new QUIConfirm({
                title: QUILocale.get(lg, 'dialog.ti.delete.title'),
                text: QUILocale.get(lg, 'dialog.ti.delete.text'),
                information: QUILocale.get(lg, 'dialog.ti.delete.information', {
                    id: this.getAttribute('invoiceId')
                }),
                icon: 'fa fa-trash',
                texticon: 'fa fa-trash',
                maxHeight: 400,
                maxWidth: 600,
                autoclose: false,
                ok_button: {
                    text: QUILocale.get('quiqqer/system', 'delete'),
                    textimage: 'fa fa-trash'
                },
                events: {
                    onSubmit: function (Win) {
                        Win.Loader.show();

                        Invoices.deleteInvoice(self.getAttribute('invoiceId')).then(function () {
                            Win.close();
                        }).then(function () {
                            Win.Loader.show();
                        });
                    }
                }
            }).open();
        },

        /**
         * event : on invoice deletion
         */
        $onDeleteInvoice: function () {
            this.destroy();
        }
    });
});