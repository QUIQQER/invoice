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
    'utils/Lock',
    'Locale',
    'Mustache',
    'Users',
    'Editors',

    'text!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.Data.html',
    'text!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.Post.html',
    'text!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.Missing.html',

    'css!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.css'

], function (QUI, QUIPanel, QUIButton, QUIButtonMultiple, QUISeparator, QUIConfirm, QUIFormUtils,
             AddressSelect, Invoices, Dialogs, Comments, TextArticle,
             Payments, AddressWindow, Locker, QUILocale, Mustache, Users, Editors,
             templateData, templatePost, templateMissing) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice',

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
            'print'
        ],

        options: {
            invoiceId         : false,
            customer_id       : false,
            invoice_address   : false,
            invoice_address_id: false,
            project_name      : '',
            date              : '',
            time_for_payment  : '',
            data              : {},
            articles          : []
        },

        initialize: function (options) {
            this.setAttributes({
                icon: 'fa fa-money'
            });

            this.parent(options);

            this.$AdditionalText     = null;
            this.$ArticleList        = null;
            this.$ArticleListSummary = null;
            this.$AddProduct         = null;
            this.$ArticleSort        = null;
            this.$AddressDelivery    = null;

            this.$AddSeparator  = null;
            this.$SortSeparator = null;
            this.$locked        = false;

            this.$serializedList = {};

            this.addEvents({
                onCreate : this.$onCreate,
                onInject : this.$onInject,
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
        $getLockKey: function () {
            return 'lock-invoice-temporary-' + this.getAttribute('invoiceId');
        },

        /**
         * Return the lock group
         * @return {string}
         */
        $getLockGroups: function () {
            return 'quiqqer/invoice';
        },

        /**
         * Panel refresh
         */
        refresh: function () {
            var title = this.getAttribute('invoiceId');

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
        doRefresh: function () {
            var self      = this,
                invoiceId = this.getAttribute('invoiceId');

            return Invoices.getTemporaryInvoice(invoiceId).then(function (data) {
                self.setAttributes(data);

                if (data.articles.articles && data.articles.articles.length) {
                    self.$serializedList = {
                        articles: data.articles.articles
                    };

                    self.setAttribute('articles', data.articles.articles);
                }

                if (data.invoice_address) {
                    self.setAttribute('invoice_address', data.invoice_address);
                }

                self.refresh();
            });
        },

        /**
         * Saves the current data
         *
         * @return {Promise}
         */
        save: function () {
            if (this.$locked) {
                return Promise.resolve();
            }

            this.Loader.show();
            this.$unloadCategory(false);

            return Invoices.saveInvoice(
                this.getAttribute('invoiceId'),
                this.getCurrentData()
            ).then(function () {
                this.Loader.hide();
                this.showSavedIconAnimation();
            }.bind(this)).catch(function (err) {
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
        post: function () {
            var self = this;

            this.Loader.show();
            this.$unloadCategory(false);

            return Invoices.saveInvoice(
                this.getAttribute('invoiceId'),
                this.getCurrentData()
            ).then(function (Data) {
                return Promise.all([
                    Invoices.postInvoice(self.getAttribute('invoiceId')),
                    Invoices.getSetting('temporaryInvoice', 'openPrintDialogAfterPost'),
                    Data
                ]);
            }).then(function (result) {
                var newInvoiceHash           = result[0],
                    openPrintDialogAfterPost = result[1],
                    Data                     = result[2];

                if (!openPrintDialogAfterPost) {
                    self.destroy();
                    return;
                }

                var entityType;

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
                Dialogs.openPrintDialog(newInvoiceHash, entityType).then(function () {
                    self.destroy();
                });
            }).catch(function (err) {
                console.error(err);
                console.error(err.getMessage());
                this.Loader.hide();
            }.bind(this));
        },

        /**
         * @returns {{customer_id, invoice_address_id, project_name, articles, date, time_for_payment}}
         */
        getCurrentData: function () {
            var deliveryAddress = this.getAttribute('addressDelivery');

            if (!deliveryAddress) {
                deliveryAddress = this.getAttribute('delivery_address');
            }

            return {
                customer_id            : this.getAttribute('customer_id'),
                invoice_address_id     : this.getAttribute('invoice_address_id'),
                project_name           : this.getAttribute('project_name'),
                articles               : this.getAttribute('articles'),
                date                   : this.getAttribute('date'),
                editor_id              : this.getAttribute('editor_id'),
                ordered_by             : this.getAttribute('ordered_by'),
                contact_person         : this.getAttribute('contact_person'),
                time_for_payment       : this.getAttribute('time_for_payment'),
                payment_method         : this.getAttribute('payment_method'),
                additional_invoice_text: this.getAttribute('additional_invoice_text'),
                currency               : this.getAttribute('currency'),
                addressDelivery        : deliveryAddress,
                processing_status      : this.getAttribute('processing_status')
            };
        },

        /**
         * Return the current user data
         */
        getUserData: function () {
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
        openData: function () {
            var self = this;

            this.renderDataDone = false;
            this.Loader.show();

            return this.$closeCategory().then(function () {
                var Container = self.getContent().getElement('.container');

                Container.setStyle('height', null);

                Container.set({
                    html: Mustache.render(templateData, {
                        textInvoiceData   : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textInvoiceData'),
                        textInvoiceDate   : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textInvoiceDate'),
                        textTermOfPayment : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textTermOfPayment'),
                        textProjectName   : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textProjectName'),
                        textOrderedBy     : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textOrderedBy'),
                        textEditor        : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textEditor'),
                        textInvoicePayment: QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textInvoicePayment'),
                        textPaymentMethod : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textPaymentMethod'),
                        textInvoiceText   : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textInvoiceText'),
                        textStatus        : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textStatus'),
                        textContactPerson : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textContactPerson'),

                        textCurrency    : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textCurrency'),
                        textCurrencyRate: QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textCurrencyRate'),

                        textInvoiceDeliveryAddress: QUILocale.get(lg, 'deliveryAddress'),
                    })
                });

                var Form = Container.getElement('form');

                QUIFormUtils.setDataToForm(self.getAttribute('data'), Form);

                // set invoice date to today
                // quiqqer/invoice#46
                var local = new Date();
                local.setMinutes(local.getMinutes() - local.getTimezoneOffset());
                var dateDate = local.toJSON().slice(0, 10);

                QUIFormUtils.setDataToForm({
                    date             : dateDate,
                    time_for_payment : self.getAttribute('time_for_payment'),
                    project_name     : self.getAttribute('project_name'),
                    editor_id        : self.getAttribute('editor_id'),
                    processing_status: self.getAttribute('processing_status'),
                    contact_person   : self.getAttribute('contact_person'),
                    currency         : self.getAttribute('currency')
                }, Form);

                Form.elements.date.set('disabled', true);
                Form.elements.date.set('title', QUILocale.get(lg, 'permissions.set.invoice.date'));

                require(['Permissions'], function (Permissions) {
                    Permissions.hasPermission('quiqqer.invoice.changeDate').then(function (has) {
                        if (has) {
                            Form.elements.date.set('disabled', false);
                            Form.elements.date.set('title', '');
                        }
                    });
                });

                Container.getElements('[name="select-contact-id-address"]').addEvent('click', function () {
                    new AddressWindow({
                        autoclose: false,
                        userId   : self.getAttribute('customer_id'),
                        events   : {
                            onSubmit: function (Win, addressId, address) {
                                Win.close();
                                self.$setContactPersonByAddress(address);
                            }
                        }
                    }).open();
                });

                if (self.getAttribute('customer_id')) {
                    Container.getElements('[name="select-contact-id-address"]').set('disabled', false);
                }

                return QUI.parse(Container);
            }).then(function () {
                return new Promise(function (resolve, reject) {
                    var Form = self.getContent().getElement('form');

                    require(['utils/Controls'], function (ControlUtils) {
                        ControlUtils.parse(Form).then(resolve);
                    }, reject);
                });
            }).then(function () {
                var Content = self.getContent();

                var quiId = Content.getElement(
                    '[data-qui="package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.UserData"]'
                ).get('data-quiid');

                var editorIdQUIId    = Content.getElement('[name="editorId"]').get('data-quiid');
                var orderedByIdQUIId = Content.getElement('[name="orderedBy"]').get('data-quiid');
                var currencyIdQUIId  = Content.getElement('[name="currency"]').get('data-quiid');

                var Data      = QUI.Controls.getById(quiId);
                var EditorId  = QUI.Controls.getById(editorIdQUIId);
                var OrderedBy = QUI.Controls.getById(orderedByIdQUIId);
                var Currency  = QUI.Controls.getById(currencyIdQUIId);

                OrderedBy.setAttribute('showAddressName', false);

                Data.addEvent('onChange', function () {
                    if (self.renderDataDone === false) {
                        return;
                    }

                    var userId = Data.getValue().userId;

                    self.setAttribute('customer_id', parseInt(userId));
                    self.setAttribute('invoice_address_id', Data.getValue().addressId);

                    if (!userId) {
                        Content.getElements('[name="select-contact-id-address"]').set('disabled', true);
                        Content.getElements('[name="contact_person"]').set('value', '');
                    } else {
                        Content.getElements('[name="select-contact-id-address"]').set('disabled', false);

                        Users.get(userId).loadIfNotLoaded().then(function (User) {
                            var addressId = User.getAttribute('quiqqer.erp.customer.contact.person');

                            if (User.getAttribute('quiqqer.erp.standard.payment')) {
                                self.getContent()
                                    .getElement('[name="payment_method"]')
                                    .value = User.getAttribute('quiqqer.erp.standard.payment');
                            }

                            if (!addressId) {
                                return;
                            }

                            addressId = parseInt(addressId);

                            User.getAddressList().then(function (addressList) {
                                for (var i = 0, len = addressList.length; i < len; i++) {
                                    if (addressList[i].id === addressId) {
                                        self.$setContactPersonByAddress(addressList[i]);
                                    }
                                }
                            });
                        });
                    }

                    // reset deliver address
                    if (self.$AddressDelivery) {
                        self.$AddressDelivery.reset();
                        self.$AddressDelivery.setAttribute('userId', userId);
                    }

                    Promise.all([
                        Invoices.getPaymentTime(userId),
                        Invoices.isNetto(userId)
                    ]).then(function (result) {
                        var paymentTime = result[0];
                        var isNetto     = result[1];

                        Content.getElement('[name="time_for_payment"]').value = paymentTime;

                        self.setAttribute('isbrutto', !isNetto);
                        self.setAttribute('time_for_payment', paymentTime);
                        self.refresh();
                    });
                });

                // currency
                Currency.addEvent('change', function (Instance, value) {
                    self.setAttribute('currency', value);
                });

                // editor
                EditorId.addEvent('onChange', function () {
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

                if (parseInt(self.getAttribute('editor_id'))) {
                    EditorId.addItem(self.getAttribute('editor_id'));
                } else {
                    EditorId.addItem(USER.id);
                }


                // ordered by
                OrderedBy.addEvent('onChange', function () {
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

                if (parseInt(self.getAttribute('ordered_by'))) {
                    OrderedBy.addItem(parseInt(self.getAttribute('ordered_by')));
                }

                // invoice address
                var address = self.getAttribute('invoice_address');

                if (!address) {
                    address = {};
                }

                address.userId    = self.getAttribute('customer_id');
                address.addressId = self.getAttribute('invoice_address_id');

                return Data.setValue(address);
            }).then(function () {
                // delivery address
                self.$AddressDelivery = QUI.Controls.getById(
                    self.getContent().getElement(
                        '[data-qui="package/quiqqer/erp/bin/backend/controls/DeliveryAddress"]'
                    ).get('data-quiid')
                );

                var deliveryAddress = self.getAttribute('addressDelivery');

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
            }).then(function () {
                var Container = self.getContent().getElement('.container');

                new QUIButton({
                    textimage: 'fa fa-list',
                    text     : QUILocale.get(lg, 'erp.panel.temporary.invoice.button.nextToArticles'),
                    styles   : {
                        display: 'block',
                        'float': 'right',
                        margin : '0 0 20px'
                    },
                    events   : {
                        onClick: function () {
                            self.openArticles().catch(function (e) {
                                console.error(e);
                            });
                        }
                    }
                }).inject(Container);

            }).then(function () {
                return Payments.getPayments();
            }).then(function (payments) {
                // load payments
                var Payments = self.getContent().getElement('[name="payment_method"]');

                new Element('option', {
                    html : '',
                    value: ''
                }).inject(Payments);

                var i, len, title;
                var current = QUILocale.getCurrent();

                for (i = 0, len = payments.length; i < len; i++) {
                    title = payments[i].title;

                    if (typeOf(title) === 'object' && typeof title[current] !== 'undefined') {
                        title = title[current];
                    }

                    new Element('option', {
                        html : title,
                        value: payments[i].id
                    }).inject(Payments);
                }

                Payments.value = self.getAttribute('payment_method');
            }).then(function () {
                // additional-invoice-text -> wysiwyg
                return self.$loadAdditionalInvoiceText();
            }).then(function () {
                self.getCategory('data').setActive();

                return self.Loader.hide();
            }).then(function () {
                return self.$openCategory();
            }).then(function () {
                self.renderDataDone = true;
            });
        },

        /**
         * Open the product category
         *
         * @returns {Promise}
         */
        openArticles: function () {
            var self = this;

            this.Loader.show();

            return self.$closeCategory().then(function (Container) {
                return new Promise(function (resolve) {
                    require([
                        'package/quiqqer/erp/bin/backend/controls/articles/ArticleList',
                        'package/quiqqer/erp/bin/backend/controls/articles/ArticleSummary'
                    ], function (List, Summary) {
                        self.$ArticleList = new List({
                            nettoinput: !self.getAttribute('isbrutto'),
                            currency  : self.getAttribute('currency'),
                            events    : {
                                onArticleReplaceClick: self.$onArticleReplaceClick
                            },
                            styles    : {
                                height: 'calc(100% - 120px)'
                            }
                        }).inject(Container);

                        Container.setStyle('height', '100%');

                        self.$ArticleListSummary = new Summary({
                            currency: self.getAttribute('currency'),
                            List    : self.$ArticleList,
                            styles  : {
                                bottom  : -20,
                                left    : 0,
                                opacity : 0,
                                position: 'absolute'
                            }
                        }).inject(Container.getParent());

                        moofx(self.$ArticleListSummary.getElm()).animate({
                            bottom : 0,
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
                            textimage: 'fa fa-info',
                            text     : QUILocale.get(lg, 'erp.panel.temporary.invoice.button.data'),
                            styles   : {
                                'float': 'left',
                                margin : '20px 0 0'
                            },
                            events   : {
                                onClick: self.openData
                            }
                        }).inject(Container);

                        new QUIButton({
                            textimage: 'fa fa-check',
                            text     : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.review.btnGoto'),
                            styles   : {
                                'float': 'right',
                                margin : '20px 0 0'
                            },
                            events   : {
                                onClick: self.openVerification
                            }
                        }).inject(Container);

                        self.Loader.hide().then(resolve);
                    });
                });
            }).then(function () {
                return self.$openCategory();
            });
        },

        /**
         * open the comments
         *
         * @return {Promise<Promise>}
         */
        openComments: function () {
            var self = this;

            this.Loader.show();
            this.getCategory('comments').setActive();

            return this.$closeCategory().then(function () {
                self.refreshComments();
            }).then(function () {
                return self.$openCategory();
            }).then(function () {
                self.Loader.hide();
            });
        },

        /**
         * Refresh the comment display
         */
        refreshComments: function () {
            var Container = this.getContent().getElement('.container');

            Container.set('html', '');

            new QUIButton({
                textimage: 'fa fa-comments',
                text     : QUILocale.get(lg, 'invoice.panel.comment.add'),
                styles   : {
                    'float'     : 'right',
                    marginBottom: 10
                },
                events   : {
                    onClick: this.openAddCommentDialog
                }
            }).inject(Container);

            new Comments({
                comments: this.getAttribute('comments')
            }).inject(Container);
        },

        /**
         * Open the verification category
         *
         * @returns {Promise}
         */
        openVerification: function () {
            var self            = this,
                ParentContainer = null,
                FrameContainer  = null;

            this.Loader.show();

            return this.$closeCategory().then(function (Container) {
                FrameContainer = new Element('div', {
                    'class': 'quiqqer-invoice-backend-temporaryInvoice-previewContainer'
                }).inject(Container);

                Container.setStyle('overflow', 'hidden');
                Container.setStyle('padding', 0);
                Container.setStyle('height', '100%');

                ParentContainer = Container;

                return Invoices.getTemporaryInvoicePreview(
                    self.getAttribute('invoiceId'),
                    self.getCurrentData()
                ).then(function (html) {

                    return new Promise(function (resolve) {
                        require(['qui/controls/elements/Sandbox'], function (Sandbox) {
                            new Sandbox({
                                content: html,
                                styles : {
                                    height : '100%',
                                    padding: 20,
                                    width  : '95%'
                                },
                                events : {
                                    onLoad: function (Box) {
                                        Box.getElm().addClass('quiqqer-invoice-backend-temporaryInvoice-preview');
                                    }
                                }
                            }).inject(FrameContainer);

                            resolve();
                        });
                    });
                });
            }).then(function () {
                // check invoice date
                var Now = new Date();
                Now.setHours(0, 0, 0, 0);

                var InvoiceDate = new Date(self.getAttribute('date'));

                if (InvoiceDate < Now) {
                    new QUIConfirm({
                        title        : QUILocale.get(lg, 'window.invoice.date.past.title'),
                        text         : QUILocale.get(lg, 'window.invoice.date.past.title'),
                        information  : QUILocale.get(lg, 'window.invoice.date.past.content'),
                        icon         : 'fa fa-clock-o',
                        texticon     : 'fa fa-clock-o',
                        maxHeight    : 400,
                        maxWidth     : 600,
                        autoclose    : false,
                        cancel_button: {
                            text     : QUILocale.get(lg, 'window.invoice.date.past.cancel.text'),
                            textimage: 'fa fa-close'
                        },
                        ok_button    : {
                            text     : QUILocale.get(lg, 'window.invoice.date.past.ok.text'),
                            textimage: 'fa fa-check'
                        },
                        events       : {
                            onSubmit: function (Win) {
                                Win.Loader.show();

                                var Today = new Date()
                                var today = Today.toISOString().split('T')[0];

                                self.setAttribute('date', today + ' 00:00:00');

                                self.save().then(function () {
                                    self.openVerification();
                                    Win.close();
                                });
                            }
                        }
                    }).open();
                }
            }).then(function () {
                return Invoices.getMissingAttributes(self.getAttribute('invoiceId'));
            }).then(function (missing) {
                var Missing = new Element('div', {
                    'class': 'quiqqer-invoice-backend-temporaryInvoice-missing',
                    styles : {
                        opacity: 0,
                        bottom : -20
                    }
                }).inject(ParentContainer);

                if (Object.getLength(missing)) {
                    Missing.set('html', Mustache.render(templateMissing, {
                        message: QUILocale.get(lg, 'message.invoice.missing')
                    }));

                    var Info = new Element('info', {
                        'class': 'quiqqer-invoice-backend-temporaryInvoice-missing-miss-message',
                        styles : {
                            display: 'none',
                            opacity: 0
                        }
                    }).inject(ParentContainer);

                    Missing.getElement(
                        '.quiqqer-invoice-backend-temporaryInvoice-missing-miss-button'
                    ).addEvent('click', function () {
                        var isShow = parseInt(Info.getStyle('opacity'));

                        if (isShow) {
                            moofx(Info).animate({
                                bottom : 60,
                                opacity: 0
                            }, {
                                callback: function () {
                                    Info.setStyle('display', 'none');
                                }
                            });
                        } else {
                            Info.setStyle('display', null);

                            moofx(Info).animate({
                                bottom : 80,
                                opacity: 1
                            });
                        }
                    });

                    for (var missed in missing) {
                        if (!missing.hasOwnProperty(missed)) {
                            continue;
                        }

                        new Element('div', {
                            'class': 'messages-message message-error',
                            html   : missing[missed]
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
                        text    : QUILocale.get(lg, 'journal.btn.post'),
                        'class' : 'btn-green',
                        events  : {
                            onClick: self.post
                        },
                        disabled: self.$locked
                    }).inject(
                        Missing.getElement('.quiqqer-invoice-backend-temporaryInvoice-missing-button')
                    );
                }

                self.getCategory('verification').setActive();

                self.Loader.hide().then(function () {
                    return new Promise(function (resolve) {
                        moofx(Missing).animate({
                            opacity: 1,
                            bottom : 0
                        }, {
                            callback: function () {
                                self.Loader.hide().then(resolve);
                            }
                        });
                    });
                });
            }).then(function () {
                return self.$openCategory();
            }).catch(function (err) {
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
        openProductSearch: function () {
            var self = this;

            this.$AddProduct.setAttribute('textimage', 'fa fa-spinner fa-spin');

            return new Promise(function (resolve) {
                require([
                    'package/quiqqer/erp/bin/backend/controls/articles/product/AddProductWindow',
                    'package/quiqqer/invoice/bin/backend/controls/articles/Article'
                ], function (Win, Article) {
                    var productDescriptionSource = false;

                    new Win({
                        user  : self.getUserData(),
                        fields: false,
                        events: {
                            onLoad: function (Instance, ProductSearch) {
                                ProductSearch.Loader.show();

                                require(['package/quiqqer/invoice/bin/Invoices'], function (Invoices) {
                                    Invoices.getSetting('invoice', 'productDescriptionSource').then(function (src) {
                                        if (parseInt(src)) {
                                            productDescriptionSource = parseInt(src);
                                            Instance.setAttribute('fields', [productDescriptionSource]);
                                        }

                                        ProductSearch.Loader.hide();
                                    });
                                });
                            },

                            onSubmit: function (Win, article) {
                                var Instance = new Article(article);

                                if ("calculated_vatArray" in article) {
                                    Instance.setVat(article.calculated_vatArray.vat);
                                }

                                if (productDescriptionSource &&
                                    typeof article.fields !== 'undefined' &&
                                    typeof article.fields[productDescriptionSource] !== 'undefined') {
                                    var field   = article.fields[productDescriptionSource];
                                    var current = QUILocale.getCurrent();

                                    if (field && typeof field[current] !== 'undefined') {
                                        Instance.setAttribute('description', field[current]);
                                    } else if (field) {
                                        Instance.setAttribute('description', field);
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
        $loadAdditionalInvoiceText: function () {
            var self = this;

            return new Promise(function (resolve) {
                var EditorParent = new Element('div').inject(
                    self.getContent().getElement('.additional-invoice-text')
                );

                Editors.getEditor(null).then(function (Editor) {
                    self.$AdditionalText = Editor;

                    // minimal toolbar
                    self.$AdditionalText.setAttribute('buttons', {
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

                    self.$AdditionalText.addEvent('onLoaded', function () {
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
        $closeCategory: function () {
            var self = this;

            if (self.$AddProduct) {
                self.$AddProduct.hide();
                self.$AddSeparator.hide();
                self.$SortSeparator.hide();
                self.$ArticleSort.hide();
            }

            if (self.$ArticleListSummary) {
                moofx(self.$ArticleListSummary.getElm()).animate({
                    bottom : -20,
                    opacity: 0
                }, {
                    duration: 250,
                    callback: function () {
                        self.$ArticleListSummary.destroy();
                        self.$ArticleListSummary = null;
                    }
                });
            }

            self.getContent().setStyle('padding', 0);

            return new Promise(function (resolve) {
                var Container = self.getContent().getElement('.container');

                if (!Container) {
                    Container = new Element('div', {
                        'class': 'container',
                        styles : {
                            opacity : 0,
                            position: 'relative',
                            top     : -50
                        }
                    }).inject(self.getContent());
                }

                moofx(Container).animate({
                    opacity: 0,
                    top    : -50
                }, {
                    duration: 200,
                    callback: function () {
                        self.$unloadCategory();

                        if (self.$AddressDelivery) {
                            self.$AddressDelivery.destroy();
                            self.$AddressDelivery = null;
                        }

                        Container.set('html', '');
                        Container.setStyle('padding', 20);

                        self.save().then(function () {
                            resolve(Container);
                        }).catch(function () {
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
         * Unload the category and reserve the data
         *
         * @param {Boolean} [destroyList] - destroy the article list, default = true
         */
        $unloadCategory: function (destroyList) {
            var Container = this.getContent().getElement('.container');

            destroyList = typeof destroyList === 'undefined' ? true : destroyList;

            if (this.$ArticleList) {
                this.setAttribute('articles', this.$ArticleList.save());
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

            var Form = Container.getElement('form');

            if (!Form) {
                return;
            }

            var formData = QUIFormUtils.getFormData(Form);
            var data     = this.getAttribute('data') || {};

            // timefields
            if ("date" in formData) {
                this.setAttribute('date', formData.date + ' 00:00:00');
            }

            if (typeof formData.contact_person !== 'undefined') {
                this.setAttribute('contact_person', formData.contact_person);
            }

            [
                'processing_status',
                'time_for_payment',
                'project_name',
                'payment_method',
                'editor_id',
                'ordered_by'
            ].each(function (entry) {
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
        $onCreate: function () {
            var self = this;

            this.$AddProduct = new QUIButtonMultiple({
                textimage: 'fa fa-plus',
                text     : QUILocale.get(lg, 'erp.panel.temporary.invoice.buttonAdd'),
                events   : {
                    onClick: function () {
                        if (self.$ArticleList) {
                            self.openProductSearch();
                        }
                    }
                }
            });

            this.$AddProduct.hide();

            this.$AddProduct.appendChild({
                text  : QUILocale.get(lg, 'erp.panel.temporary.invoice.buttonAdd.custom'),
                events: {
                    onClick: function () {
                        if (self.$ArticleList) {
                            self.$ArticleList.insertNewProduct();
                        }
                    }
                }
            });

            this.$AddProduct.appendChild({
                text  : QUILocale.get(lg, 'erp.panel.temporary.invoice.buttonAdd.text'),
                events: {
                    onClick: function () {
                        if (self.$ArticleList) {
                            self.$ArticleList.addArticle(new TextArticle());
                        }
                    }
                }
            });

            this.$AddSeparator  = new QUISeparator();
            this.$SortSeparator = new QUISeparator();

            // buttons
            this.addButton({
                name     : 'save',
                text     : QUILocale.get('quiqqer/system', 'save'),
                textimage: 'fa fa-save',
                events   : {
                    onClick: function () {
                        //quiqqer/invoice', 'message.invoice.save.successfully'
                        self.save().then(function () {
                            QUI.getMessageHandler().then(function (MH) {
                                MH.addSuccess(
                                    QUILocale.get('quiqqer/invoice', 'message.invoice.save.successfully')
                                );
                            });
                        });
                    }
                }
            });

            this.$ArticleSort = new QUIButton({
                name     : 'sort',
                textimage: 'fa fa-sort',
                text     : QUILocale.get(lg, 'erp.panel.temporary.invoice.button.article.sort.text'),
                events   : {
                    onClick: this.toggleSort
                }
            });

            this.$ArticleSort.hide();


            this.addButton(this.$AddSeparator);
            this.addButton(this.$AddProduct);
            this.addButton(this.$SortSeparator);
            this.addButton(this.$ArticleSort);

            this.addButton({
                name  : 'lock',
                icon  : 'fa fa-warning',
                styles: {
                    background: '#fcf3cf',
                    color     : '#7d6608',
                    'float'   : 'right'
                },
                events: {
                    onClick: this.$showLockMessage
                }
            });

            this.getButtons('lock').hide();

            this.addButton({
                name  : 'delete',
                icon  : 'fa fa-trash',
                title : QUILocale.get(lg, 'erp.panel.temporary.invoice.deleteButton.title'),
                styles: {
                    'float': 'right'
                },
                events: {
                    onClick: this.$clickDelete
                }
            });

            this.addButton({
                name     : 'output',
                textimage: 'fa fa-print',
                text     : QUILocale.get(lg, 'journal.btn.pdf'),
                styles   : {
                    'float': 'right'
                },
                events   : {
                    onClick: this.print
                }
            });

            // categories
            this.addCategory({
                name  : 'data',
                icon  : 'fa fa-info',
                text  : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data'),
                events: {
                    onClick: this.openData
                }
            });

            this.addCategory({
                name  : 'articles',
                icon  : 'fa fa-list',
                text  : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.pos'),
                events: {
                    onClick: this.openArticles
                }
            });

            this.addCategory({
                name  : 'comments',
                icon  : 'fa fa-comments',
                text  : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.comments'),
                events: {
                    onClick: this.openComments
                }
            });

            this.addCategory({
                name  : 'verification',
                icon  : 'fa fa-check',
                text  : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.review'),
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

            document.addEvent('keyup', this.$onKeyUp);

            var self      = this,
                invoiceId = this.getAttribute('invoiceId');

            Locker.isLocked(
                this.$getLockKey(),
                this.$getLockGroups()
            ).then(function (isLocked) {
                if (isLocked) {
                    self.$locked = isLocked;
                    self.lockPanel();
                    return;
                }

                return Locker.lock(
                    self.$getLockKey(),
                    self.$getLockGroups()
                );
            }).then(function () {
                return self.doRefresh();
            }).then(function () {
                return Invoices.getMissingAttributes(invoiceId);
            }).then(function (missing) {
                if (Object.getLength(missing)) {
                    self.getCategoryBar().firstChild().click();
                    return;
                }

                self.getCategoryBar().getChildren('verification').click();
            }).catch(function (Exception) {
                QUI.getMessageHandler().then(function (MH) {
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
        $onDestroy: function () {
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
        lockPanel: function () {
            this.getButtons('save').disable();
            this.getButtons('delete').disable();
            this.getButtons('lock').show();
        },

        /**
         * unlock the lock
         *
         * @return {Promise<T>}
         */
        unlockPanel: function () {
            var self = this;

            this.Loader.show();

            return Locker.unlock(
                this.$getLockKey(),
                this.$getLockGroups()
            ).then(function () {
                return Locker.isLocked(
                    self.$getLockKey(),
                    self.$getLockGroups()
                );
            }).then(function (isLocked) {
                if (isLocked) {
                    return;
                }

                self.$locked = isLocked;
                self.getButtons('lock').hide();

                return self.refresh();
            }).then(function () {
                return self.openData();
            });
        },

        /**
         * show the lock message window
         */
        $showLockMessage: function () {
            var self    = this,
                btnText = QUILocale.get('quiqqer/quiqqer', 'submit');

            if (window.USER.isSU) {
                btnText = QUILocale.get(lg, 'button.unlock.invoice.is.locked');
            }

            new QUIConfirm({
                title      : QUILocale.get(lg, 'window.unlock.invoice.title'),
                icon       : 'fa fa-warning',
                texticon   : 'fa fa-warning',
                text       : QUILocale.get(lg, 'window.unlock.invoice.text', this.$locked),
                information: QUILocale.get(lg, 'message.invoice.is.locked', this.$locked),
                autoclose  : false,
                maxHeight  : 400,
                maxWidth   : 600,
                ok_button  : {
                    text: btnText
                },

                events: {
                    onSubmit: function (Win) {
                        if (!window.USER.isSU) {
                            Win.close();
                            return;
                        }

                        Win.Loader.show();

                        self.unlockPanel().then(function () {
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
        $onKeyUp: function (event) {
            if (this.$ArticleList && (event.event.code === 'NumpadAdd' || event.code === 107)) {
                this.$AddProduct.click();
            }
        },

        /**
         * opens the delete dialog
         */
        $clickDelete: function () {
            var self = this;

            new QUIConfirm({
                title      : QUILocale.get(lg, 'dialog.ti.delete.title'),
                text       : QUILocale.get(lg, 'dialog.ti.delete.text'),
                information: QUILocale.get(lg, 'dialog.ti.delete.information', {
                    id: this.getAttribute('invoiceId')
                }),
                icon       : 'fa fa-trash',
                texticon   : 'fa fa-trash',
                maxHeight  : 400,
                maxWidth   : 600,
                autoclose  : false,
                ok_button  : {
                    text     : QUILocale.get('quiqqer/system', 'delete'),
                    textimage: 'fa fa-trash'
                },
                events     : {
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
        },

        /**
         *
         * @param List
         * @param Article
         */
        $onArticleReplaceClick: function (List, Article) {
            var self = this;

            var replaceArticle = function (NewArticle) {
                List.replaceArticle(
                    NewArticle,
                    Article.getAttribute('position')
                );

                NewArticle.select();
            };

            new QUIConfirm({
                title    : QUILocale.get(lg, 'erp.panel.temporary.invoice.replace.article.title'),
                maxHeight: 400,
                maxWidth : 600,
                icon     : 'fa fa-retweet',
                events   : {
                    onOpen: function (Win) {
                        Win.getContent().setStyles({
                            textAlign: 'center'
                        });

                        Win.getContent().set(
                            'html',
                            QUILocale.get(lg, 'erp.panel.temporary.invoice.replace.article.text')
                        );

                        var Select = new Element('select', {
                            styles: {
                                margin: '20px auto 0'
                            }
                        }).inject(Win.getContent());

                        new Element('option', {
                            html : QUILocale.get(lg, 'erp.panel.temporary.invoice.replace.article.withProduct'),
                            value: 'product'
                        }).inject(Select);

                        new Element('option', {
                            html : QUILocale.get(lg, 'erp.panel.temporary.invoice.buttonAdd.custom'),
                            value: 'custom'
                        }).inject(Select);

                        new Element('option', {
                            html : QUILocale.get(lg, 'erp.panel.temporary.invoice.buttonAdd.text'),
                            value: 'text'
                        }).inject(Select);
                    },

                    onSubmit: function (Win) {
                        var Select = Win.getContent().getElement('select');

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
                        ], function (Article) {
                            replaceArticle(new Article());
                        });
                    }
                }
            }).open();
        },

        /**
         * Toggle the article sorting
         */
        toggleSort: function () {
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
        openAddCommentDialog: function () {
            var self = this;

            new QUIConfirm({
                title    : QUILocale.get(lg, 'dialog.add.comment.title'),
                icon     : 'fa fa-edit',
                maxHeight: 600,
                maxWidth : 800,
                events   : {
                    onOpen: function (Win) {
                        Win.getContent().set('html', '');
                        Win.Loader.show();

                        require([
                            'Editors'
                        ], function (Editors) {
                            Editors.getEditor(null).then(function (Editor) {
                                Win.$Editor = Editor;

                                Win.$Editor.addEvent('onLoaded', function () {
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

                    onSubmit: function (Win) {
                        Win.Loader.show();

                        self.addComment(Win.$Editor.getContent()).then(function () {
                            return self.doRefresh();
                        }).then(function () {
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
        addComment: function (message) {
            return Invoices.addComment(this.getAttribute('invoiceId'), message);
        },

        //endregion

        /**
         * set the contact person by an address data object to the contact person input field
         *
         * @param address
         */
        $setContactPersonByAddress: function (address) {
            var Content     = this.getContent(),
                PersonInput = Content.getElement('[name="contact_person"]');

            if (!PersonInput) {
                return;
            }

            var value = (address.salutation + ' ' + address.firstname + ' ' + address.lastname).trim();
            PersonInput.set('value', value);
        },

        /**
         * Opens the print dialog
         *
         * @return {Promise}
         */
        print: function () {
            var self = this,
                type = self.getAttribute('type'),
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

            return new Promise(function (resolve) {
                require([
                    'package/quiqqer/invoice/bin/backend/utils/Dialogs'
                ], function (Dialogs) {
                    Dialogs.openPrintDialog(self.getAttribute('id'), entityType).then(resolve);
                });
            });
        },
    });
});
