/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice
 * @author www.pcsg.de (Henning Leutz)
 *
 * Edit a Temporary Invoice and created a posted invoice
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/ButtonMultiple',
    'qui/controls/buttons/Separator',
    'qui/controls/windows/Confirm',
    'qui/utils/Form',
    'controls/users/address/Select',
    'package/quiqqer/invoice/bin/Invoices',
    'package/quiqqer/invoice/bin/backend/utils/Dialogs',
    'package/quiqqer/erp/bin/backend/controls/Comments',
    'package/quiqqer/erp/bin/backend/controls/articles/Text',
    'package/quiqqer/payments/bin/backend/Payments',
    'package/quiqqer/customer/bin/backend/controls/customer/address/Window',
    'package/quiqqer/customer/bin/backend/controls/customer/userFiles/Select',
    'utils/Lock',
    'Locale',
    'Ajax',
    'Mustache',
    'Users',
    'Editors',

    'text!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.Data.html',
    'text!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.Post.html',
    'text!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.Missing.html',

    'css!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.css'

], function(QUI, QUIPanel, QUIButton, QUIButtonMultiple, QUISeparator, QUIConfirm, QUIFormUtils,
    AddressSelect, Invoices, Dialogs, Comments, TextArticle,
    Payments, AddressWindow, CustomerFileSelect, Locker, QUILocale, QUIAjax, Mustache, Users, Editors,
    templateData, templatePost, templateMissing
) {
    'use strict';

    const lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIPanel,
        Type: 'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice',

        Binds: [
            'save',
            'post',
            'openData',
            'openArticles',
            'openComments',
            'openAddCommentDialog',
            'openVerification',
            '$openCategory',
            '$closeCategory',
            '$onCreate',
            '$onInject',
            '$onKeyUp',
            '$onDestroy',
            '$onDeleteInvoice',
            '$onArticleReplaceClick',
            '$clickDelete',
            'toggleSort',
            '$showLockMessage',
            'print',
            'openInvoiceFiles',
            '$openXmlCategory'
        ],

        options: {
            invoiceId: false, // @deprecated
            uuid: false,
            hash: false,
            customer_id: false,
            invoice_address: false,
            invoice_address_id: false,
            project_name: '',
            date: '',
            time_for_payment: '',
            data: {},
            articles: []
        },

        initialize: function(options) {
            this.setAttributes({
                icon: 'fa fa-money'
            });

            this.parent(options);

            if (this.getAttribute('invoiceId') && !this.getAttribute('hash')) {
                this.setAttribute('hash', this.getAttribute('invoiceId'));
            }

            if (this.getAttribute('uuid') && !this.getAttribute('hash')) {
                this.setAttribute('hash', this.getAttribute('uuid'));
            }

            this.$AdditionalText = null;
            this.$ArticleList = null;
            this.$ArticleListSummary = null;
            this.$AddProduct = null;
            this.$ArticleSort = null;
            this.$AddressDelivery = null;

            this.$AddSeparator = null;
            this.$SortSeparator = null;
            this.$locked = false;

            this.$serializedList = {};

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
         * Return the lock key
         *
         * @return {string}
         */
        $getLockKey: function() {
            return 'lock-invoice-temporary-' + this.getAttribute('hash');
        },

        /**
         * Return the lock group
         * @return {string}
         */
        $getLockGroups: function() {
            return 'quiqqer/invoice';
        },

        /**
         * Panel refresh
         */
        refresh: function() {
            if (!this.getAttribute('prefixedNumber')) {
                this.setAttribute('title', '...');
                this.parent();
                return;
            }

            let title = this.getAttribute('prefixedNumber');
            title = title + ' (';

            if (this.getAttribute('isbrutto')) {
                title = title + QUILocale.get(lg, 'brutto.panel.title');
            } else {
                title = title + QUILocale.get(lg, 'netto.panel.title');
            }

            title = title + ')';

            this.setAttribute('title', title);
            this.parent();
        },

        /**
         * Refresh the invoice data
         */
        doRefresh: function() {
            return Invoices.getTemporaryInvoice(this.getAttribute('hash')).then((data) => {
                this.setAttributes(data);

                if (data.articles) {
                    this.$serializedList = data.articles;

                    if (typeof this.$serializedList.articles !== 'undefined') {
                        this.setAttribute('articles', this.$serializedList.articles);
                        this.setAttribute('priceFactors', data.articles.priceFactors);
                    }

                    if (this.$ArticleList) {
                        this.$ArticleList.unserialize(this.$serializedList);
                    }
                }

                if (data.invoice_address) {
                    this.setAttribute('invoice_address', data.invoice_address);
                }

                this.refresh();
            });
        },

        /**
         * Saves the current data
         *
         * @return {Promise}
         */
        save: function() {
            if (this.$locked) {
                return Promise.resolve();
            }

            this.Loader.show();
            this.$unloadCategory(false);

            return Invoices.saveInvoice(
                this.getAttribute('hash'),
                this.getCurrentData()
            ).then(function() {
                this.Loader.hide();
                this.showSavedIconAnimation();
            }.bind(this)).catch(function(err) {
                console.error(err);
                console.error(err.getMessage());
                this.Loader.hide();
            }.bind(this));
        },

        /**
         * Post the temporary invoice
         *
         * @return {Promise}
         */
        post: function() {
            const self = this;

            this.Loader.show();
            this.$unloadCategory(false);

            return Invoices.saveInvoice(
                this.getAttribute('hash'),
                this.getCurrentData()
            ).then(function(Data) {
                return Promise.all([
                    Invoices.postInvoice(self.getAttribute('hash')),
                    Invoices.getSetting('temporaryInvoice', 'openPrintDialogAfterPost'),
                    Data
                ]);
            }).then(function(result) {
                let newInvoiceHash = result[0],
                    openPrintDialogAfterPost = result[1],
                    Data = result[2];

                if (!openPrintDialogAfterPost) {
                    self.destroy();
                    return;
                }

                let entityType;

                switch (parseInt(Data.type)) {
                    case 3:
                        entityType = 'CreditNote';
                        break;

                    case 4:
                        entityType = 'Canceled';
                        break;

                    default:
                        entityType = 'Invoice';
                }

                // open print dialog
                Dialogs.openPrintDialog(newInvoiceHash, entityType).then(function() {
                    self.destroy();
                });
            }).catch(function(err) {
                console.error(err);
                console.error(err.getMessage());
                this.Loader.hide();
            }.bind(this));
        },

        /**
         * @returns {{customer_id, invoice_address_id, project_name, articles, date, time_for_payment}}
         */
        getCurrentData: function() {
            let deliveryAddress = this.getAttribute('addressDelivery');

            if (!deliveryAddress) {
                deliveryAddress = this.getAttribute('delivery_address');
            }

            return {
                customer_id: this.getAttribute('customer_id'),
                invoice_address_id: this.getAttribute('invoice_address_id'),
                project_name: this.getAttribute('project_name'),
                articles: this.getAttribute('articles'),
                priceFactors: this.getAttribute('priceFactors'),
                date: this.getAttribute('date'),
                editor_id: this.getAttribute('editor_id'),
                ordered_by: this.getAttribute('ordered_by'),
                contact_person: this.getAttribute('contact_person'),
                contactEmail: this.getAttribute('contactEmail'),
                time_for_payment: this.getAttribute('time_for_payment'),
                payment_method: this.getAttribute('payment_method'),
                additional_invoice_text: this.getAttribute('additional_invoice_text'),
                currency: this.getAttribute('currency'),
                currencyRate: this.getAttribute('currencyRate'),
                addressDelivery: deliveryAddress,
                processing_status: this.getAttribute('processing_status'),
                attached_customer_files: this.getAttribute('attached_customer_files')
            };
        },

        /**
         * Return the current user data
         */
        getUserData: function() {
            return {
                uid: this.getAttribute('customer_id'),
                aid: this.getAttribute('invoice_address_id')
            };
        },

        /**
         * Categories
         */

        /**
         * Open the data category
         *
         * @returns {Promise}
         */
        openData: function() {
            const self = this;

            this.renderDataDone = false;
            this.Loader.show();

            return this.$closeCategory().then(function() {
                const Container = self.getContent().getElement('.container');

                Container.setStyle('height', null);

                Container.set({
                    html: Mustache.render(templateData, {
                        textInvoiceData: QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textInvoiceData'),
                        textInvoiceDate: QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textInvoiceDate'),
                        textTermOfPayment: QUILocale.get(
                            lg,
                            'erp.panel.temporary.invoice.category.data.textTermOfPayment'
                        ),
                        textProjectName: QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textProjectName'),
                        textOrderedBy: QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textOrderedBy'),
                        textEditor: QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textEditor'),
                        textInvoicePayment: QUILocale.get(
                            lg,
                            'erp.panel.temporary.invoice.category.data.textInvoicePayment'
                        ),
                        textPaymentMethod: QUILocale.get(
                            lg,
                            'erp.panel.temporary.invoice.category.data.textPaymentMethod'
                        ),
                        textInvoiceText: QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textInvoiceText'),
                        textStatus: QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textStatus'),
                        textContactPerson: QUILocale.get(
                            lg,
                            'erp.panel.temporary.invoice.category.data.textContactPerson'
                        ),

                        textCurrency: QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textCurrency'),
                        textCurrencyRate: QUILocale.get(
                            lg,
                            'erp.panel.temporary.invoice.category.data.textCurrencyRate'
                        ),

                        textInvoiceDeliveryAddress: QUILocale.get(lg, 'deliveryAddress')
                    })
                });

                const Form = Container.getElement('form');

                QUIFormUtils.setDataToForm(self.getAttribute('data'), Form);

                // set invoice date to today
                // quiqqer/invoice#46
                let local = new Date();
                local.setMinutes(local.getMinutes() - local.getTimezoneOffset());
                let dateDate = local.toJSON().slice(0, 10);

                QUIFormUtils.setDataToForm({
                    date: dateDate,
                    time_for_payment: self.getAttribute('time_for_payment'),
                    project_name: self.getAttribute('project_name'),
                    editor_id: self.getAttribute('editor_id'),
                    processing_status: self.getAttribute('processing_status'),
                    contact_person: self.getAttribute('contact_person'),
                    currency: self.getAttribute('currency'),
                    currencyRate: self.getAttribute('currencyRate')
                }, Form);

                Form.elements.date.set('disabled', true);
                Form.elements.date.set('title', QUILocale.get(lg, 'permissions.set.invoice.date'));

                require(['Permissions'], function(Permissions) {
                    Permissions.hasPermission('quiqqer.invoice.changeDate').then(function(has) {
                        if (has) {
                            Form.elements.date.set('disabled', false);
                            Form.elements.date.set('title', '');
                        }
                    });
                });

                return QUI.parse(Container);
            }).then(function() {
                return new Promise(function(resolve, reject) {
                    const Form = self.getContent().getElement('form');

                    require([
                        'Packages',
                        'utils/Controls'
                    ], function(Packages, ControlUtils) {
                        ControlUtils.parse(Form).then(function() {
                            return Packages.getConfig('quiqqer/currency');
                        }).then(resolve);
                    }, reject);
                });
            }).then(function(config) {
                const Content = self.getContent();

                const quiId = Content.getElement(
                    '[data-qui="package/quiqqer/erp/bin/backend/controls/userData/UserData"]'
                ).get('data-quiid');

                let editorIdQUIId = Content.getElement('[name="editorId"]').get('data-quiid');
                let orderedByIdQUIId = Content.getElement('[name="orderedBy"]').get('data-quiid');
                let currencyIdQUIId = Content.getElement('[name="currency"]').get('data-quiid');
                let CurrencyRate = Content.getElement('[name="currencyRate"]');

                let Data = QUI.Controls.getById(quiId);
                let EditorId = QUI.Controls.getById(editorIdQUIId);
                let OrderedBy = QUI.Controls.getById(orderedByIdQUIId);
                let Currency = QUI.Controls.getById(currencyIdQUIId);

                if (parseInt(config.currency.differentAccountingCurrencies) === 0) {
                    Content.getElements('table.invoice-currency').setStyle('display', 'none');
                }

                OrderedBy.setAttribute('showAddressName', false);

                Data.addEvent('onChange', function() {
                    if (self.renderDataDone === false) {
                        return;
                    }

                    const Customer = Data.getValue();
                    let userId = Customer.userId;

                    self.setAttribute('customer_id', userId);
                    self.setAttribute('invoice_address_id', Customer.addressId);
                    self.setAttribute('contact_person', Customer.contactPerson);
                    self.setAttribute('contactEmail', Customer.contactEmail);

                    // reset deliver address
                    if (self.$AddressDelivery) {
                        self.$AddressDelivery.setAttribute('userId', userId);
                    }

                    Promise.all([
                        Invoices.getPaymentTime(userId),
                        Invoices.isNetto(userId)
                    ]).then(function(result) {
                        let paymentTime = result[0];
                        let isNetto = result[1];

                        Content.getElement('[name="time_for_payment"]').value = paymentTime;

                        self.setAttribute('isbrutto', !isNetto);
                        self.setAttribute('time_for_payment', paymentTime);
                        self.refresh();
                    });
                });

                // currency
                Currency.addEvent('change', function(Instance, value) {
                    if (self.renderDataDone === false) {
                        return;
                    }

                    self.Loader.show();
                    self.setAttribute('currency', value);

                    require(['package/quiqqer/currency/bin/Currency'], function(Currencies) {
                        Currencies.getCurrency(value).then(function(data) {
                            if ('rate' in data) {
                                self.setAttribute('currencyRate', data.rate);
                                CurrencyRate.value = data.rate;
                            }

                            self.Loader.hide();
                        }).catch(function(err) {
                            console.error(err);
                            self.Loader.hide();
                        });
                    });
                });

                // editor
                EditorId.addEvent('onChange', function() {
                    if (self.renderDataDone === false) {
                        return;
                    }

                    self.setAttribute('editor_id', EditorId.getValue());
                });

                if (typeof window.QUIQQER_EMPLOYEE_GROUP !== 'undefined') {
                    EditorId.setAttribute('search', true);
                    EditorId.setAttribute('searchSettings', {
                        filter: {
                            filter_group: window.QUIQQER_EMPLOYEE_GROUP
                        }
                    });
                }

                if (self.getAttribute('editor_id')) {
                    EditorId.addItem(self.getAttribute('editor_id'));
                } else {
                    EditorId.addItem(USER.id);
                }


                // ordered by
                OrderedBy.addEvent('onChange', function() {
                    self.setAttribute('ordered_by', OrderedBy.getValue());
                });

                if (typeof window.QUIQQER_CUSTOMER_GROUP !== 'undefined') {
                    OrderedBy.setAttribute('search', true);
                    OrderedBy.setAttribute('searchSettings', {
                        filter: {
                            filter_group: window.QUIQQER_CUSTOMER_GROUP
                        }
                    });
                }

                if (self.getAttribute('ordered_by')) {
                    OrderedBy.addItem(self.getAttribute('ordered_by'));
                }

                // invoice address
                let address = self.getAttribute('invoice_address');

                if (!address) {
                    address = {};
                }

                address.userId = self.getAttribute('customer_id');
                address.addressId = self.getAttribute('invoice_address_id');
                address.contactPerson = self.getAttribute('contact_person') ? self.getAttribute('contact_person') : '';
                address.contactEmail = self.getAttribute('contactEmail') ? self.getAttribute('contactEmail') : '';

                if (self.getAttribute('contactEmail')) {
                    address.contactEmail = self.getAttribute('contactEmail');
                }

                return Data.setValue(address);
            }).then(function() {
                // delivery address
                self.$AddressDelivery = QUI.Controls.getById(
                    self.getContent().getElement(
                        '[data-qui="package/quiqqer/erp/bin/backend/controls/DeliveryAddress"]'
                    ).get('data-quiid')
                );

                let deliveryAddress = self.getAttribute('addressDelivery');

                if (!deliveryAddress) {
                    deliveryAddress = self.getAttribute('delivery_address');

                    if (deliveryAddress) {
                        deliveryAddress = JSON.decode(deliveryAddress);
                    }
                }

                if (deliveryAddress) {
                    self.$AddressDelivery.setAttribute('userId', self.getAttribute('customer_id'));
                    self.$AddressDelivery.setValue(deliveryAddress);
                }
            }).then(function() {
                let Container = self.getContent().getElement('.container');

                new QUIButton({
                    text: QUILocale.get(lg, 'erp.panel.temporary.invoice.button.nextToArticles') +
                        ' <span class="fa fa-angle-right"></span>',
                    styles: {
                        display: 'block',
                        'float': 'right',
                        margin: '0 0 20px'
                    },
                    events: {
                        onClick: function() {
                            self.openArticles().catch(function(e) {
                                console.error(e);
                            });
                        }
                    }
                }).inject(Container);
            }).then(function() {
                return Payments.getPayments();
            }).then(function(payments) {
                // load payments
                let Payments = self.getContent().getElement('[name="payment_method"]');

                new Element('option', {
                    html: '',
                    value: ''
                }).inject(Payments);

                let i, len, title;
                let current = QUILocale.getCurrent();

                for (i = 0, len = payments.length; i < len; i++) {
                    title = payments[i].title;

                    if (typeOf(title) === 'object' && typeof title[current] !== 'undefined') {
                        title = title[current];
                    }

                    new Element('option', {
                        html: title,
                        value: payments[i].id
                    }).inject(Payments);
                }

                Payments.value = self.getAttribute('payment_method');
            }).then(function() {
                // additional-invoice-text -> wysiwyg
                return self.$loadAdditionalInvoiceText();
            }).then(function() {
                self.getCategory('data').setActive();

                return self.Loader.hide();
            }).then(function() {
                return self.$openCategory();
            }).then(function() {
                self.renderDataDone = true;
            });
        },

        /**
         * Open the product category
         *
         * @returns {Promise}
         */
        openArticles: function() {
            const self = this;

            this.Loader.show();

            return self.$closeCategory().then(function(Container) {
                return new Promise(function(resolve) {
                    require([
                        'package/quiqqer/erp/bin/backend/controls/articles/ArticleList',
                        'package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary'
                    ], function(List, Summary) {
                        self.$ArticleList = new List({
                            nettoinput: !self.getAttribute('isbrutto'),
                            currency: self.getAttribute('currency'),
                            events: {
                                onArticleReplaceClick: self.$onArticleReplaceClick
                            },
                            styles: {
                                height: 'calc(100% - 110px)'
                            }
                        }).inject(Container);

                        Container.setStyle('height', '100%');

                        self.$ArticleListSummary = new Summary({
                            currency: self.getAttribute('currency'),
                            List: self.$ArticleList,
                            styles: {
                                bottom: -20,
                                left: 0,
                                opacity: 0,
                                position: 'absolute'
                            }
                        }).inject(Container.getParent());

                        moofx(self.$ArticleListSummary.getElm()).animate({
                            bottom: 0,
                            opacity: 1
                        });

                        self.$ArticleList.setUser(self.getUserData());

                        if (self.$serializedList) {
                            self.$ArticleList.unserialize(self.$serializedList);
                        }

                        self.$AddProduct.show();
                        self.$AddSeparator.show();
                        self.$SortSeparator.show();
                        self.$ArticleSort.show();

                        self.getCategory('articles').setActive();

                        new QUIButton({
                            text: '<span class="fa fa-angle-left"></span> ' +
                                QUILocale.get(lg, 'erp.panel.temporary.invoice.button.data'),
                            styles: {
                                'float': 'left',
                                margin: '20px 0 0'
                            },
                            events: {
                                onClick: self.openData
                            }
                        }).inject(Container);

                        new QUIButton({
                            text: QUILocale.get(lg, 'erp.panel.temporary.invoice.category.review.btnGoto') +
                                ' <span class="fa fa-angle-right"></span>',
                            styles: {
                                'float': 'right',
                                margin: '20px 0 0'
                            },
                            events: {
                                onClick: self.openVerification
                            }
                        }).inject(Container);

                        self.Loader.hide().then(resolve);
                    });
                });
            }).then(function() {
                return self.$openCategory();
            });
        },

        /**
         * open the comments
         *
         * @return {Promise<Promise>}
         */
        openComments: function() {
            const self = this;

            this.Loader.show();
            this.getCategory('comments').setActive();

            return this.$closeCategory().then(function() {
                self.refreshComments();
            }).then(function() {
                return self.$openCategory();
            }).then(function() {
                self.Loader.hide();
            });
        },

        /**
         * Refresh the comment display
         */
        refreshComments: function() {
            const Container = this.getContent().getElement('.container');

            Container.set('html', '');

            new QUIButton({
                textimage: 'fa fa-comments',
                text: QUILocale.get(lg, 'invoice.panel.comment.add'),
                styles: {
                    'float': 'right',
                    marginBottom: 10
                },
                events: {
                    onClick: this.openAddCommentDialog
                }
            }).inject(Container);

            new Comments({
                comments: this.getAttribute('comments')
            }).inject(Container);
        },

        /**
         * Open invoice file management
         *
         * @returns {Promise}
         */
        openInvoiceFiles: function() {
            this.Loader.show();

            this.getCategory('invoiceFiles').setActive();

            return this.$closeCategory().then((Container) => {
                Container.setStyle('overflow', 'hidden');
                Container.setStyle('padding', 20);
                Container.setStyle('height', '100%');

                const customerId = this.getAttribute('customer_id');

                if (!customerId) {
                    new Element('p', {
                        html: QUILocale.get(lg, 'controls.panels.TemporaryInvoice.invoice_files_no_customer')
                    }).inject(Container);

                    return;
                }

                return new Promise((resolve) => {
                    require(['package/quiqqer/erp/bin/backend/controls/customerFiles/Grid'], (FileGrid) => {
                        new FileGrid({
                            hash: this.getAttribute('hash')
                        }).inject(Container);

                        resolve();
                    });
                });
            }).then(() => {
                return this.$openCategory();
            }).catch((err) => {
                console.error('ERROR');
                console.error(err);

                return this.$openCategory();
            });
        },

        /**
         * Open the verification category
         *
         * @returns {Promise}
         */
        openVerification: function() {
            const self = this;
            let ParentContainer = null,
                FrameContainer = null;

            this.Loader.show();

            return this.$closeCategory().then((Container) => {
                FrameContainer = new Element('div', {
                    'class': 'quiqqer-invoice-backend-temporaryInvoice-previewContainer'
                }).inject(Container);

                Container.setStyle('overflow', 'hidden');
                Container.setStyle('padding', 0);
                Container.setStyle('height', '100%');

                ParentContainer = Container;

                return Invoices.getTemporaryInvoicePreview(
                    self.getAttribute('hash'),
                    self.getCurrentData()
                ).then(function(html) {

                    return new Promise(function(resolve) {
                        require(['qui/controls/elements/Sandbox'], function(Sandbox) {
                            new Sandbox({
                                content: html,
                                styles: {
                                    height: '100%',
                                    padding: 20,
                                    width: '95%'
                                },
                                events: {
                                    onLoad: function(Box) {
                                        Box.getElm().addClass('quiqqer-invoice-backend-temporaryInvoice-preview');
                                    }
                                }
                            }).inject(FrameContainer);

                            resolve();
                        });
                    });
                });
            }).then(function() {
                // check invoice date
                const Now = new Date();
                Now.setHours(0, 0, 0, 0);

                let InvoiceDate = new Date(self.getAttribute('date'));

                if (InvoiceDate < Now) {
                    new QUIConfirm({
                        title: QUILocale.get(lg, 'window.invoice.date.past.title'),
                        text: QUILocale.get(lg, 'window.invoice.date.past.title'),
                        information: QUILocale.get(lg, 'window.invoice.date.past.content'),
                        icon: 'fa fa-clock-o',
                        texticon: 'fa fa-clock-o',
                        maxHeight: 400,
                        maxWidth: 600,
                        autoclose: false,
                        cancel_button: {
                            text: QUILocale.get(lg, 'window.invoice.date.past.cancel.text'),
                            textimage: 'fa fa-close'
                        },
                        ok_button: {
                            text: QUILocale.get(lg, 'window.invoice.date.past.ok.text'),
                            textimage: 'fa fa-check'
                        },
                        events: {
                            onSubmit: function(Win) {
                                Win.Loader.show();

                                let Today = new Date();
                                let today = Today.toISOString().split('T')[0];

                                self.setAttribute('date', today + ' 00:00:00');

                                self.save().then(function() {
                                    self.openVerification();
                                    Win.close();
                                });
                            }
                        }
                    }).open();
                }
            }).then(function() {
                return Invoices.getMissingAttributes(self.getAttribute('hash'));
            }).then(function(missing) {
                const Missing = new Element('div', {
                    'class': 'quiqqer-invoice-backend-temporaryInvoice-missing',
                    styles: {
                        opacity: 0,
                        bottom: -20
                    }
                }).inject(ParentContainer);

                if (Object.getLength(missing)) {
                    Missing.set('html', Mustache.render(templateMissing, {
                        message: QUILocale.get(lg, 'message.invoice.missing')
                    }));

                    const Info = new Element('info', {
                        'class': 'quiqqer-invoice-backend-temporaryInvoice-missing-miss-message',
                        styles: {
                            display: 'none',
                            opacity: 0
                        }
                    }).inject(ParentContainer);

                    Missing.getElement(
                        '.quiqqer-invoice-backend-temporaryInvoice-missing-miss-button'
                    ).addEvent('click', function() {
                        let isShow = parseInt(Info.getStyle('opacity'));

                        if (isShow) {
                            moofx(Info).animate({
                                bottom: 60,
                                opacity: 0
                            }, {
                                callback: function() {
                                    Info.setStyle('display', 'none');
                                }
                            });
                        } else {
                            Info.setStyle('display', null);

                            moofx(Info).animate({
                                bottom: 80,
                                opacity: 1
                            });
                        }
                    });

                    for (let missed in missing) {
                        if (!missing.hasOwnProperty(missed)) {
                            continue;
                        }

                        new Element('div', {
                            'class': 'messages-message message-error',
                            html: missing[missed]
                        }).inject(Info);
                    }

                    Missing.getElement(
                        '.quiqqer-invoice-backend-temporaryInvoice-missing-miss-button'
                    ).click();
                } else {
                    // post available
                    Missing.set('html', Mustache.render(templatePost, {
                        message: QUILocale.get(lg, 'message.invoice.ok')
                    }));

                    new QUIButton({
                        text: QUILocale.get(lg, 'journal.btn.post'),
                        'class': 'btn-green',
                        events: {
                            onClick: self.post
                        },
                        disabled: self.$locked
                    }).inject(
                        Missing.getElement('.quiqqer-invoice-backend-temporaryInvoice-missing-button')
                    );
                }

                self.getCategory('verification').setActive();

                self.Loader.hide().then(function() {
                    return new Promise(function(resolve) {
                        moofx(Missing).animate({
                            opacity: 1,
                            bottom: 0
                        }, {
                            callback: function() {
                                self.Loader.hide().then(resolve);
                            }
                        });
                    });
                });
            }).then(function() {
                return self.$openCategory();
            }).catch(function(err) {
                console.error('ERROR');
                console.error(err);

                return self.$openCategory();
            });
        },

        /**
         * Opens the product search
         *
         * @todo only if products are installed
         */
        openProductSearch: function() {
            const self = this;

            this.$AddProduct.setAttribute('textimage', 'fa fa-spinner fa-spin');

            return new Promise(function(resolve) {
                require([
                    'package/quiqqer/erp/bin/backend/controls/articles/product/AddProductWindow',
                    'package/quiqqer/invoice/bin/backend/controls/articles/Article'
                ], function(Win, Article) {
                    let productDescriptionSource = false;

                    new Win({
                        user: self.getUserData(),
                        fields: false,
                        events: {
                            onLoad: function(Instance, ProductSearch) {
                                ProductSearch.Loader.show();

                                require(['package/quiqqer/invoice/bin/Invoices'], function(Invoices) {
                                    Invoices.getSetting('invoice', 'productDescriptionSource').then(function(src) {
                                        if (parseInt(src)) {
                                            productDescriptionSource = parseInt(src);
                                            Instance.setAttribute('fields', [productDescriptionSource]);
                                        }

                                        ProductSearch.Loader.hide();
                                    });
                                });
                            },

                            onSubmit: function(Win, article) {
                                const Instance = new Article(article);

                                if ('calculated_vatArray' in article) {
                                    Instance.setVat(article.calculated_vatArray.vat);
                                }

                                if (productDescriptionSource &&
                                    typeof article.fields !== 'undefined' &&
                                    typeof article.fields[productDescriptionSource] !== 'undefined') {
                                    let field = article.fields[productDescriptionSource];
                                    let current = QUILocale.getCurrent();

                                    if (field && typeof field[current] !== 'undefined') {
                                        Instance.setAttribute('description', field[current]);
                                    } else {
                                        if (field) {
                                            Instance.setAttribute('description', field);
                                        }
                                    }
                                }

                                self.$ArticleList.addArticle(Instance);
                                resolve(Instance);
                            }
                        }
                    }).open();

                    self.$AddProduct.setAttribute('textimage', 'fa fa-plus');
                });
            });
        },

        /**
         *
         * @return {Promise}
         */
        $loadAdditionalInvoiceText: function() {
            const self = this;

            return new Promise(function(resolve) {
                const EditorParent = new Element('div').inject(
                    self.getContent().getElement('.additional-invoice-text')
                );

                Editors.getEditor(null).then(function(Editor) {
                    self.$AdditionalText = Editor;

                    // minimal toolbar
                    self.$AdditionalText.setAttribute('buttons', {
                        lines: [
                            [
                                [
                                    {
                                        type: 'button',
                                        button: 'Bold'
                                    },
                                    {
                                        type: 'button',
                                        button: 'Italic'
                                    },
                                    {
                                        type: 'button',
                                        button: 'Underline'
                                    },
                                    {
                                        type: 'separator'
                                    },
                                    {
                                        type: 'button',
                                        button: 'RemoveFormat'
                                    },
                                    {
                                        type: 'separator'
                                    },
                                    {
                                        type: 'button',
                                        button: 'NumberedList'
                                    },
                                    {
                                        type: 'button',
                                        button: 'BulletedList'
                                    }
                                ]
                            ]
                        ]
                    });

                    self.$AdditionalText.addEvent('onLoaded', function() {
                        self.$AdditionalText.switchToWYSIWYG();
                        self.$AdditionalText.showToolbar();
                        self.$AdditionalText.setContent(self.getAttribute('additional_invoice_text'));

                        resolve();
                    });

                    self.$AdditionalText.inject(EditorParent);
                    self.$AdditionalText.setHeight(200);
                });
            });
        },

        /**
         * Close the current category and save it
         *
         * @returns {Promise}
         */
        $closeCategory: function() {
            const self = this;

            if (self.$AddProduct) {
                self.$AddProduct.hide();
                self.$AddSeparator.hide();
                self.$SortSeparator.hide();
                self.$ArticleSort.hide();
            }

            if (self.$ArticleListSummary) {
                moofx(self.$ArticleListSummary.getElm()).animate({
                    bottom: -20,
                    opacity: 0
                }, {
                    duration: 250,
                    callback: function() {
                        self.$ArticleListSummary.destroy();
                        self.$ArticleListSummary = null;
                    }
                });
            }

            self.getContent().setStyle('padding', 0);

            return new Promise(function(resolve) {
                let Container = self.getContent().getElement('.container');

                if (!Container) {
                    Container = new Element('div', {
                        'class': 'container',
                        styles: {
                            opacity: 0,
                            position: 'relative',
                            top: -50
                        }
                    }).inject(self.getContent());
                }

                moofx(Container).animate({
                    opacity: 0,
                    top: -50
                }, {
                    duration: 200,
                    callback: function() {
                        self.$unloadCategory();

                        if (self.$AddressDelivery) {
                            self.$AddressDelivery.destroy();
                            self.$AddressDelivery = null;
                        }

                        Container.set('html', '');
                        Container.setStyle('padding', 20);

                        self.save().then(function() {
                            resolve(Container);
                        }).catch(function() {
                            resolve(Container);
                        });
                    }
                });
            });
        },

        /**
         * Open the current category
         *
         * @returns {Promise}
         */
        $openCategory: function() {
            const self = this;

            return new Promise(function(resolve) {
                const Container = self.getContent().getElement('.container');

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
         * Unload the category and reserve the data
         *
         * @param {Boolean} [destroyList] - destroy the article list, default = true
         */
        $unloadCategory: function(destroyList) {
            const Container = this.getContent().getElement('.container');

            destroyList = typeof destroyList === 'undefined' ? true : destroyList;

            if (this.$ArticleList) {
                this.setAttribute('articles', this.$ArticleList.save());
                this.setAttribute('priceFactors', this.$ArticleList.getPriceFactors());
                this.$serializedList = this.$ArticleList.serialize();

                if (destroyList) {
                    this.$ArticleList.destroy();
                    this.$ArticleList = null;
                }
            }

            if (this.$AddressDelivery) {
                this.setAttribute('addressDelivery', this.$AddressDelivery.getValue());
                this.setAttribute('delivery_address', this.$AddressDelivery.getValue());
            }

            if (this.$AdditionalText) {
                this.setAttribute(
                    'additional_invoice_text',
                    this.$AdditionalText.getContent()
                );
            }

            const Form = Container.getElement('form');

            if (!Form) {
                return;
            }

            let formData = QUIFormUtils.getFormData(Form);
            let data = this.getAttribute('data') || {};

            // timefields
            if ('date' in formData) {
                this.setAttribute('date', formData.date + ' 00:00:00');
            }

            [
                'processing_status',
                'time_for_payment',
                'project_name',
                'payment_method',
                'editor_id',
                'ordered_by',
                'currencyRate',
                'currency'
            ].each(function(entry) {
                if (!formData.hasOwnProperty(entry)) {
                    return;
                }

                if (entry === 'time_for_payment') {
                    formData[entry] = parseInt(formData[entry]);
                }

                this.setAttribute(entry, formData[entry]);
                delete formData[entry];
            }.bind(this));

            this.setAttribute('data', Object.merge(data, formData));
        },

        /**
         * Event Handling
         */

        /**
         * event: on create
         */
        $onCreate: function() {
            const self = this;

            this.$AddProduct = new QUIButtonMultiple({
                textimage: 'fa fa-plus',
                text: QUILocale.get(lg, 'erp.panel.temporary.invoice.buttonAdd'),
                events: {
                    onClick: function() {
                        if (self.$ArticleList) {
                            self.openProductSearch();
                        }
                    }
                }
            });

            this.$AddProduct.hide();

            this.$AddProduct.appendChild({
                text: QUILocale.get(lg, 'erp.panel.temporary.invoice.buttonAdd.custom'),
                events: {
                    onClick: function() {
                        if (self.$ArticleList) {
                            self.$ArticleList.insertNewProduct();
                        }
                    }
                }
            });

            this.$AddProduct.appendChild({
                text: QUILocale.get(lg, 'erp.panel.temporary.invoice.buttonAdd.text'),
                events: {
                    onClick: function() {
                        if (self.$ArticleList) {
                            self.$ArticleList.addArticle(new TextArticle());
                        }
                    }
                }
            });

            this.$AddSeparator = new QUISeparator();
            this.$SortSeparator = new QUISeparator();

            // buttons
            this.addButton({
                name: 'save',
                text: QUILocale.get('quiqqer/system', 'save'),
                textimage: 'fa fa-save',
                events: {
                    onClick: function() {
                        //quiqqer/invoice', 'message.invoice.save.successfully'
                        self.save().then(function() {
                            QUI.getMessageHandler().then(function(MH) {
                                MH.addSuccess(
                                    QUILocale.get('quiqqer/invoice', 'message.invoice.save.successfully')
                                );
                            });
                        });
                    }
                }
            });

            this.$ArticleSort = new QUIButton({
                name: 'sort',
                textimage: 'fa fa-sort',
                text: QUILocale.get(lg, 'erp.panel.temporary.invoice.button.article.sort.text'),
                events: {
                    onClick: this.toggleSort
                }
            });

            this.$ArticleSort.hide();


            this.addButton(this.$AddSeparator);
            this.addButton(this.$AddProduct);
            this.addButton(this.$SortSeparator);
            this.addButton(this.$ArticleSort);

            this.addButton({
                name: 'lock',
                icon: 'fa fa-warning',
                styles: {
                    background: '#fcf3cf',
                    color: '#7d6608',
                    'float': 'right'
                },
                events: {
                    onClick: this.$showLockMessage
                }
            });

            this.getButtons('lock').hide();

            this.addButton({
                name: 'delete',
                icon: 'fa fa-trash',
                title: QUILocale.get(lg, 'erp.panel.temporary.invoice.deleteButton.title'),
                styles: {
                    'float': 'right'
                },
                events: {
                    onClick: this.$clickDelete
                }
            });

            this.addButton({
                name: 'output',
                textimage: 'fa fa-print',
                text: QUILocale.get(lg, 'journal.btn.pdf'),
                styles: {
                    'float': 'right'
                },
                events: {
                    onClick: this.print
                }
            });

            // categories
            this.addCategory({
                name: 'data',
                icon: 'fa fa-info',
                text: QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data'),
                events: {
                    onClick: this.openData
                }
            });

            this.addCategory({
                name: 'articles',
                icon: 'fa fa-list',
                text: QUILocale.get(lg, 'erp.panel.temporary.invoice.category.pos'),
                events: {
                    onClick: this.openArticles
                }
            });

            this.addCategory({
                name: 'comments',
                icon: 'fa fa-comments',
                text: QUILocale.get(lg, 'erp.panel.temporary.invoice.category.comments'),
                events: {
                    onClick: this.openComments
                }
            });

            this.addCategory({
                name: 'invoiceFiles',
                icon: 'fa fa-file-text-o',
                text: QUILocale.get(lg, 'erp.panel.temporary.invoice.category.invoiceFiles'),
                events: {
                    onClick: this.openInvoiceFiles
                }
            });

            this.addCategory({
                name: 'verification',
                icon: 'fa fa-check',
                text: QUILocale.get(lg, 'erp.panel.temporary.invoice.category.review'),
                events: {
                    onClick: this.openVerification
                }
            });


            // invoice.xml panel api
            QUIAjax.get('package_quiqqer_invoice_ajax_invoices_panel_getCategories', (categories) => {
                let cat, title;

                if (typeOf(categories) === 'array' && !categories.length) {
                    return;
                }

                for (let category in categories) {
                    if (!categories.hasOwnProperty(category)) {
                        continue;
                    }

                    cat = categories[category];
                    title = cat.title;

                    this.addCategory({
                        icon: cat.icon,
                        name: cat.name,
                        title: QUILocale.get(title[0], title[1]),
                        text: QUILocale.get(title[0], title[1]),
                        events: {
                            onClick: this.$openXmlCategory
                        }
                    });
                }
            }, {
                'package': 'quiqqer/invoice'
            });
        },

        /**
         * event: on inject
         */
        $onInject: function() {
            this.Loader.show();

            if (!this.getAttribute('hash')) {
                this.destroy();
                return;
            }

            document.addEvent('keyup', this.$onKeyUp);

            const self = this;
            let invoiceHash = this.getAttribute('hash');

            Locker.isLocked(
                this.$getLockKey(),
                this.$getLockGroups()
            ).then(function(isLocked) {
                if (isLocked) {
                    self.$locked = isLocked;
                    self.lockPanel();
                    return;
                }

                return Locker.lock(
                    self.$getLockKey(),
                    self.$getLockGroups()
                );
            }).then(function() {
                return self.doRefresh();
            }).then(function() {
                return Invoices.getMissingAttributes(invoiceHash);
            }).then(function(missing) {
                if (Object.getLength(missing)) {
                    self.getCategoryBar().firstChild().click();
                    return;
                }

                self.getCategoryBar().getChildren('verification').click();
            }).catch(function(Exception) {
                QUI.getMessageHandler().then(function(MH) {
                    console.error(Exception);

                    if (typeof Exception.getMessage === 'function') {
                        MH.addError(Exception.getMessage());
                    }
                });

                self.destroy();
            });
        },

        /**
         * event: on panel destroy
         */
        $onDestroy: function() {
            Invoices.removeEvents({
                onDeleteInvoice: this.$onDeleteInvoice
            });

            document.removeEvent('keyup', this.$onKeyUp);

            Locker.unlock(
                this.$getLockKey(),
                this.$getLockGroups()
            );
        },

        /**
         * lock the complete panel
         */
        lockPanel: function() {
            this.getButtons('save').disable();
            this.getButtons('delete').disable();
            this.getButtons('lock').show();
        },

        /**
         * unlock the lock
         *
         * @return {Promise<T>}
         */
        unlockPanel: function() {
            const self = this;

            this.Loader.show();

            return Locker.unlock(
                this.$getLockKey(),
                this.$getLockGroups()
            ).then(function() {
                return Locker.isLocked(
                    self.$getLockKey(),
                    self.$getLockGroups()
                );
            }).then(function(isLocked) {
                if (isLocked) {
                    return;
                }

                self.$locked = isLocked;
                self.getButtons('lock').hide();

                return self.refresh();
            }).then(function() {
                return self.openData();
            });
        },

        /**
         * show the lock message window
         */
        $showLockMessage: function() {
            const self = this;
            let btnText = QUILocale.get('quiqqer/core', 'submit');

            if (window.USER.isSU) {
                btnText = QUILocale.get(lg, 'button.unlock.invoice.is.locked');
            }

            new QUIConfirm({
                title: QUILocale.get(lg, 'window.unlock.invoice.title'),
                icon: 'fa fa-warning',
                texticon: 'fa fa-warning',
                text: QUILocale.get(lg, 'window.unlock.invoice.text', this.$locked),
                information: QUILocale.get(lg, 'message.invoice.is.locked', this.$locked),
                autoclose: false,
                maxHeight: 400,
                maxWidth: 600,
                ok_button: {
                    text: btnText
                },

                events: {
                    onSubmit: function(Win) {
                        if (!window.USER.isSU) {
                            Win.close();
                            return;
                        }

                        Win.Loader.show();

                        self.unlockPanel().then(function() {
                            Win.close();
                        });
                    }
                }
            }).open();
        },

        /**
         * event: on key up
         *
         * @param event
         */
        $onKeyUp: function(event) {
            if (this.$ArticleList && (event.event.code === 'NumpadAdd' || event.code === 107)) {
                this.$AddProduct.click();
            }
        },

        /**
         * opens the delete dialog
         */
        $clickDelete: function() {
            const self = this;

            new QUIConfirm({
                title: QUILocale.get(lg, 'dialog.ti.delete.title'),
                text: QUILocale.get(lg, 'dialog.ti.delete.text'),
                information: QUILocale.get(lg, 'dialog.ti.delete.information', {
                    invoices: '<ul><li>' + this.getAttribute('hash') + '</li></ul>'
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
                    onSubmit: function(Win) {
                        Win.Loader.show();

                        Invoices.deleteInvoice(self.getAttribute('hash')).then(function() {
                            Win.close();
                        }).then(function() {
                            Win.Loader.show();
                        });
                    }
                }
            }).open();
        },

        /**
         * event : on invoice deletion
         */
        $onDeleteInvoice: function(Invoices, hash) {
            if (hash === this.getAttribute('hash')) {
                this.destroy();
            }
        },

        /**
         *
         * @param List
         * @param Article
         */
        $onArticleReplaceClick: function(List, Article) {
            const self = this;

            const replaceArticle = function(NewArticle) {
                List.replaceArticle(
                    NewArticle,
                    Article.getAttribute('position')
                );

                NewArticle.select();
            };

            new QUIConfirm({
                title: QUILocale.get(lg, 'erp.panel.temporary.invoice.replace.article.title'),
                maxHeight: 400,
                maxWidth: 600,
                icon: 'fa fa-retweet',
                events: {
                    onOpen: function(Win) {
                        Win.getContent().setStyles({
                            textAlign: 'center'
                        });

                        Win.getContent().set(
                            'html',
                            QUILocale.get(lg, 'erp.panel.temporary.invoice.replace.article.text')
                        );

                        const Select = new Element('select', {
                            styles: {
                                margin: '20px auto 0'
                            }
                        }).inject(Win.getContent());

                        new Element('option', {
                            html: QUILocale.get(lg, 'erp.panel.temporary.invoice.replace.article.withProduct'),
                            value: 'product'
                        }).inject(Select);

                        new Element('option', {
                            html: QUILocale.get(lg, 'erp.panel.temporary.invoice.buttonAdd.custom'),
                            value: 'custom'
                        }).inject(Select);

                        new Element('option', {
                            html: QUILocale.get(lg, 'erp.panel.temporary.invoice.buttonAdd.text'),
                            value: 'text'
                        }).inject(Select);
                    },

                    onSubmit: function(Win) {
                        const Select = Win.getContent().getElement('select');

                        if (Select.value === 'product') {
                            self.openProductSearch().then(replaceArticle);
                            return;
                        }

                        if (Select.value === 'text') {
                            replaceArticle(new TextArticle());
                            return;
                        }

                        require([
                            'package/quiqqer/invoice/bin/backend/controls/articles/Article'
                        ], function(Article) {
                            replaceArticle(new Article());
                        });
                    }
                }
            }).open();
        },

        /**
         * Toggle the article sorting
         */
        toggleSort: function() {
            this.$ArticleList.toggleSorting();

            if (this.$ArticleList.isSortingEnabled()) {
                this.$ArticleSort.setActive();
                return;
            }

            this.$ArticleSort.setNormal();
        },

        //region comments

        /**
         * Open the add dialog window
         */
        openAddCommentDialog: function() {
            const self = this;

            new QUIConfirm({
                title: QUILocale.get(lg, 'dialog.add.comment.title'),
                icon: 'fa fa-edit',
                maxHeight: 600,
                maxWidth: 800,
                events: {
                    onOpen: function(Win) {
                        Win.getContent().set('html', '');
                        Win.Loader.show();

                        require([
                            'Editors'
                        ], function(Editors) {
                            Editors.getEditor(null).then(function(Editor) {
                                Win.$Editor = Editor;

                                Win.$Editor.addEvent('onLoaded', function() {
                                    Win.$Editor.switchToWYSIWYG();
                                    Win.$Editor.showToolbar();
                                    Win.$Editor.setContent(self.getAttribute('content'));
                                    Win.Loader.hide();
                                });

                                Win.$Editor.inject(Win.getContent());
                                Win.$Editor.setHeight(200);
                            });
                        });
                    },

                    onSubmit: function(Win) {
                        Win.Loader.show();

                        self.addComment(Win.$Editor.getContent()).then(function() {
                            return self.doRefresh();
                        }).then(function() {
                            Win.$Editor.destroy();
                            Win.close();

                            self.refreshComments();
                        });
                    }
                }
            }).open();
        },

        /**
         * add a comment to the order
         *
         * @param {String} message
         */
        addComment: function(message) {
            return Invoices.addComment(this.getAttribute('hash'), message);
        },

        //endregion

        /**
         * set the contact person by an address data object to the contact person input field
         *
         * @param address
         */
        $setContactPersonByAddress: function(address) {
            const Content = this.getContent(),
                PersonInput = Content.getElement('[name="contact_person"]');

            if (!PersonInput) {
                return;
            }

            let value = (address.salutation + ' ' + address.firstname + ' ' + address.lastname).trim();
            PersonInput.set('value', value);
        },

        /**
         * Opens the print dialog
         *
         * @return {Promise}
         */
        print: function() {
            const self = this;

            let type = self.getAttribute('type'),
                entityType;

            switch (parseInt(type)) {
                case 3:
                    entityType = 'CreditNote';
                    break;

                case 4:
                    entityType = 'Canceled';
                    break;

                default:
                    entityType = 'Invoice';
            }

            return new Promise(function(resolve) {
                require([
                    'package/quiqqer/invoice/bin/backend/utils/Dialogs'
                ], function(Dialogs) {
                    Dialogs.openPrintDialog(self.getAttribute('hash'), entityType).then(resolve);
                });
            });
        },

        //region category stuff

        $openXmlCategory: function(Category) {
            this.Loader.show();

            QUIAjax.get('package_quiqqer_invoice_ajax_invoices_panel_getCategory', (html) => {
                this.$closeCategory().then((Container) => {
                    Container.set('html', html);

                    return QUI.parse(Container);
                }).then(() => {
                    return this.$openCategory();
                }).then(() => {
                    this.Loader.hide();
                });
            }, {
                'package': 'quiqqer/invoice',
                category: Category.getAttribute('name')
            });
        }

        //endregion
    });
});
