/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/Invoice
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/Invoice', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'package/quiqqer/invoice/bin/Invoices',
    'package/quiqqer/invoice/bin/backend/controls/Comments',
    'Locale',
    'Mustache',

    'css!package/quiqqer/invoice/bin/backend/controls/panels/Invoice.css'

], function (QUI, QUIPanel, QUIButton, Invoices, Comments, QUILocale, Mustache) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({
        Extends: QUIPanel,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/panels/Invoice',

        Binds: [
            'openInfo',
            'openArticles',
            'openPayments',
            'openHistory',
            'openComments',
            '$onCreate',
            '$onInject',
            '$onDestroy'
        ],

        options: {
            invoiceId: false
        },

        initialize: function (options) {
            this.setAttributes({
                icon: 'fa fa-money'
            });

            this.parent(options);

            this.addEvents({
                onCreate : this.$onCreate,
                onInject : this.$onInject,
                onDestroy: this.$onDestroy
            });
        },

        /**
         * Refresh the invoice data
         */
        doRefresh: function () {
            var self = this;

            return Invoices.get(this.getAttribute('invoiceId')).then(function (data) {
                self.setAttribute('title', QUILocale.get(lg, 'erp.panel.invoice.title', {
                    id: data.id
                }));


                self.setAttribute('data', data);
                self.refresh();
            });
        },

        /**
         * event: on create
         */
        $onCreate: function () {
            this.addCategory({
                icon  : 'fa fa-info',
                name  : 'info',
                title : QUILocale.get(lg, 'erp.panel.invoice.data'),
                text  : QUILocale.get(lg, 'erp.panel.invoice.data'),
                events: {
                    onClick: this.openInfo
                }
            });

            this.addCategory({
                icon  : 'fa fa-list',
                name  : 'articles',
                title : QUILocale.get(lg, 'erp.panel.invoice.articles'),
                text  : QUILocale.get(lg, 'erp.panel.invoice.articles'),
                events: {
                    onClick: this.openArticles
                }
            });

            this.addCategory({
                icon  : 'fa fa-money',
                name  : 'payments',
                title : QUILocale.get(lg, 'erp.panel.invoice.payments'),
                text  : QUILocale.get(lg, 'erp.panel.invoice.payments'),
                events: {
                    onClick: this.openPayments
                }
            });

            this.addCategory({
                icon  : 'fa fa-history',
                name  : 'history',
                title : QUILocale.get(lg, 'erp.panel.invoice.history'),
                text  : QUILocale.get(lg, 'erp.panel.invoice.history'),
                events: {
                    onClick: this.openHistory
                }
            });

            this.addCategory({
                icon  : 'fa fa-comments',
                name  : 'comments',
                title : QUILocale.get(lg, 'erp.panel.invoice.comments'),
                text  : QUILocale.get(lg, 'erp.panel.invoice.comments'),
                events: {
                    onClick: this.openComments
                }
            });

            this.getContent().addClass('quiqqer-invoice-invoice');
            this.openInfo();
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self = this;

            this.Loader.show();
            this.doRefresh().then(function () {
                self.Loader.hide();
            });
        },

        /**
         * event: on destroy
         */
        $onDestroy: function () {

        },

        //region Categories

        /**
         * Open the information
         */
        openInfo: function () {
            var self = this;

            this.Loader.show();
            this.getCategory('info').setActive();

            return this.$closeCategory().then(function (Container) {
                return new Promise(function (resolve) {
                    require([
                        'text!package/quiqqer/invoice/bin/backend/controls/panels/Invoice.Data.html'
                    ], function (template) {
                        console.log(self.getAttribute('data'));

                        var data = self.getAttribute('data');

                        data.textInvoiceRecipient = QUILocale.get(lg, 'cutomerData');
                        data.textCustomer         = QUILocale.get(lg, 'customer');
                        data.textCompany          = QUILocale.get(lg, 'company');
                        data.textStreet           = QUILocale.get(lg, 'street');
                        data.textZip              = QUILocale.get(lg, 'zip');
                        data.textCity             = QUILocale.get(lg, 'city');

                        data.textInvoiceData = QUILocale.get(lg, 'erp.panel.invoice.data.title');
                        data.textInvoiceDate = QUILocale.get(lg, 'erp.panel.invoice.data.date');
                        data.textProjectName = QUILocale.get(lg, 'erp.panel.invoice.data.projectName');
                        data.textOrderedBy   = QUILocale.get(lg, 'erp.panel.invoice.data.orderedBy');
                        data.textEditor      = QUILocale.get(lg, 'erp.panel.invoice.data.editor');

                        data.textInvoicePayment       = QUILocale.get(lg, 'erp.panel.invoice.data.payment');
                        data.textInvoicePaymentMethod = QUILocale.get(lg, 'erp.panel.invoice.data.paymentMethod');
                        data.textTermOfPayment        = QUILocale.get(lg, 'erp.panel.invoice.data.termOfPayment');

                        data.textInvoiceText = QUILocale.get(lg, 'erp.panel.invoice.data.invoiceText');

                        Container.set({
                            html: Mustache.render(template, data)
                        });

                        try {
                            var Form    = Container.getElement('form'),
                                address = JSON.decode(data.invoice_address);

                            Form.elements.customer.value  = address.salutation + ' ' + address.firstname + ' ' + address.lastname;
                            Form.elements.company.value   = address.company;
                            Form.elements.street_no.value = address.street_no;
                            Form.elements.zip.value       = address.zip;
                            Form.elements.city.value      = address.city;

                            console.warn(address);

                        } catch (e) {
                            console.error(e);
                        }

                        resolve();
                    });
                });
            }).then(function () {
                return self.$openCategory();
            }).then(function () {
                self.Loader.hide();
            });
        },

        /**
         * open articles
         */
        openArticles: function () {
            var self = this;

            this.Loader.show();
            this.getCategory('articles').setActive();

            console.warn(self.getAttribute('data').id);

            return Promise.all([
                this.$closeCategory(),
                Invoices.getArticlesHtml(self.getAttribute('data').id)
            ]).then(function (result) {
                var Container = result[0];

                return new Promise(function (resolve) {
                    require(['qui/controls/elements/Sandbox'], function (Sandbox) {
                        Container.set('html', '');

                        new Sandbox({
                            content: result[1],
                            styles : {
                                border: 0,
                                height: '100%',
                                width : '100%'
                            },
                            events : {
                                onLoad: resolve
                            }
                        }).inject(Container);
                    });
                });

            }).then(function () {
                return self.$openCategory();
            }).then(function () {
                self.Loader.hide();
            });
        },

        /**
         * Open payments list
         */
        openPayments: function () {
            var self = this;

            this.Loader.show();
            this.getCategory('payments').setActive();

            return this.$closeCategory().then(function (Container) {
                return new Promise(function (resolve) {
                    require([
                        'package/quiqqer/invoice/bin/backend/controls/panels/Journal.Payments'
                    ], function (Payments) {
                        new Payments({
                            invoiceId: self.getAttribute('data').id,
                            events   : {
                                onLoad: resolve
                            }
                        }).inject(Container);
                    });
                });
            }).then(function () {
                return self.$openCategory();
            }).then(function () {
                self.Loader.hide();
            });
        },

        /**
         * open History
         */
        openHistory: function () {
            var self = this;

            this.Loader.show();
            this.getCategory('history').setActive();

            return this.$closeCategory().then(function (Container) {
                new Comments({
                    comments: self.getAttribute('data').history
                }).inject(Container);
            }).then(function () {
                return self.$openCategory();
            }).then(function () {
                self.Loader.hide();
            });
        },

        /**
         * open comments
         */
        openComments: function () {
            var self = this;

            this.Loader.show();
            this.getCategory('comments').setActive();

            return this.$closeCategory().then(function (Container) {
                new Comments({
                    comments: self.getAttribute('data').comments
                }).inject(Container);
            }).then(function () {
                return self.$openCategory();
            }).then(function () {
                self.Loader.hide();
            });
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
                    top    : 0
                }, {
                    duration: 200,
                    callback: resolve
                });
            });
        },

        /**
         * Close the current category
         *
         * @returns {Promise}
         */
        $closeCategory: function () {
            this.getContent().setStyle('padding', 0);

            return new Promise(function (resolve) {
                var Container = this.getContent().getElement('.container');

                if (!Container) {
                    Container = new Element('div', {
                        'class': 'container',
                        styles : {
                            height  : '100%',
                            opacity : 0,
                            position: 'relative',
                            top     : -50
                        }
                    }).inject(this.getContent());
                }

                moofx(Container).animate({
                    opacity: 0,
                    top    : -50
                }, {
                    duration: 200,
                    callback: function () {
                        Container.set('html', '');
                        Container.setStyle('padding', 20);

                        resolve(Container);
                    }.bind(this)
                });
            }.bind(this));
        }

        //endregion
    });
});