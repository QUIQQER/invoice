/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice
 *
 * Edit a Temporary Invoice and created a posted invoice
 *
 * @require qui/QUI
 * @require qui/controls/desktop/Panel
 * @require qui/controls/buttons/Button
 * @require qui/controls/buttons/ButtonMultiple
 * @require qui/controls/buttons/Separator
 * @require qui/controls/windows/Confirm
 * @require controls/users/address/Select
 * @require package/quiqqer/invoice/bin/Invoices
 * @require Locale
 * @require Mustache
 * @require Users
 * @require text!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.Data.html',
 * @require css!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.css'
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
    'package/quiqqer/invoice/bin/backend/controls/articles/Text',
    'package/quiqqer/payments/bin/backend/Payments',
    'Locale',
    'Mustache',
    'Users',

    'text!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.Data.html',
    'text!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.Post.html',
    'text!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.Missing.html',

    'css!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.css'

], function (QUI, QUIPanel, QUIButton, QUIButtonMultiple, QUISeparator, QUIConfirm, QUIFormUtils,
             AddressSelect, Invoices, TextArticle,
             Payments, QUILocale, Mustache, Users,
             templateData, templatePost, templateMissing) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice',

        Binds: [
            'save',
            'openData',
            'openArticles',
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
            invoiceId         : false,
            customer_id       : false,
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

            this.$ArticleList        = null;
            this.$ArticleListSummary = null;
            this.$AddProduct         = null;
            this.$AddSeparator       = null;

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
         * Panel refresh
         */
        refresh: function () {
            var title = this.getAttribute('invoiceId');

            title = title + ' (';

            if (this.getAttribute('isbrutto')) {
                title = title + QUILocale.get(lg, 'brutto');
            } else {
                title = title + QUILocale.get(lg, 'netto');
            }

            title = title + ')';

            this.setAttribute('title', title);
            this.parent();
        },

        /**
         * Saves the current data
         *
         * @return {Promise}
         */
        save: function () {
            this.Loader.show();
            this.$unloadCategory(false);

            return Invoices.saveInvoice(
                this.getAttribute('invoiceId'),
                this.getCurrentData()
            ).then(function () {
                this.Loader.hide();
            }.bind(this)).catch(function (err) {
                console.error(err);
                console.error(err.getMessage());
                this.Loader.hide();
            }.bind(this));
        },

        /**
         *
         * @returns {{customer_id, invoice_address_id, project_name, articles, date, time_for_payment}}
         */
        getCurrentData: function () {
            return {
                customer_id       : this.getAttribute('customer_id'),
                invoice_address_id: this.getAttribute('invoice_address_id'),
                project_name      : this.getAttribute('project_name'),
                articles          : this.getAttribute('articles'),
                date              : this.getAttribute('date'),
                time_for_payment  : this.getAttribute('time_for_payment'),
                payment_method    : this.getAttribute('payment_method')
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

            this.Loader.show();

            return this.$closeCategory().then(function () {
                var Container = self.getContent().getElement('.container');

                Container.set({
                    html: Mustache.render(templateData, {
                        textInvoiceData   : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textInvoiceData'),
                        textInvoiceDate   : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textInvoiceDate'),
                        textTermOfPayment : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textTermOfPayment'),
                        textProjectName   : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textProjectName'),
                        textOrderedBy     : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textOrderedBy'),
                        textInvoicePayment: QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textInvoicePayment'),
                        textPaymentMethod : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.data.textPaymentMethod')
                    })
                });

                var Form = Container.getElement('form');

                QUIFormUtils.setDataToForm(self.getAttribute('data'), Form);

                // time fields
                var time;

                var dateDate = '',
                    dateTime = '';

                if (self.getAttribute('date')) {
                    time = self.getAttribute('date').split(' ');

                    dateDate = time[0];
                    dateTime = time[1];
                }

                if (!dateTime || dateTime === '') {
                    dateTime = '00:00:00';
                }

                QUIFormUtils.setDataToForm({
                    date_date       : dateDate,
                    date_time       : dateTime,
                    time_for_payment: self.getAttribute('time_for_payment'),
                    project_name    : self.getAttribute('project_name')
                }, Form);

                return QUI.parse(Container);
            }).then(function () {
                var quiId = self.getContent().getElement(
                    '[data-qui="package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.UserData"]'
                ).get('data-quiid');

                var Data = QUI.Controls.getById(quiId);

                Data.addEvent('onChange', function () {
                    self.setAttribute('customer_id', Data.getValue().userId);
                    self.setAttribute('invoice_address_id', Data.getValue().addressId);
                });

                return Data.setValue(
                    self.getAttribute('customer_id'),
                    self.getAttribute('invoice_address_id')
                );
            }).then(function () {
                var Container = self.getContent().getElement('.container');

                new QUIButton({
                    textimage: 'fa fa-list',
                    text     : 'Artikel verwalten', // #locale
                    styles   : {
                        display: 'block',
                        'float': 'none',
                        margin : '0 auto'
                    },
                    events   : {
                        onClick: function () {
                            self.openArticles();
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

                for (var payment in payments) {
                    if (!payments.hasOwnProperty(payment)) {
                        continue;
                    }

                    new Element('option', {
                        html : payments[payment].title,
                        value: payment
                    }).inject(Payments);
                }

                Payments.value = self.getAttribute('payment_method');
                self.getCategory('data').setActive();

                return self.Loader.hide();
            }).then(function () {
                return self.$openCategory();
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
                        'package/quiqqer/invoice/bin/backend/controls/InvoiceArticleList',
                        'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.Summary'
                    ], function (List, Summary) {
                        self.$ArticleList = new List({
                            events: {},
                            styles: {
                                height: 'calc(100% - 120px)'
                            }
                        }).inject(Container);

                        Container.setStyle('height', '100%');

                        self.$ArticleListSummary = new Summary({
                            List  : self.$ArticleList,
                            styles: {
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

                        self.getCategory('articles').setActive();

                        new QUIButton({
                            textimage: 'fa fa-check',
                            text     : QUILocale.get(lg, 'erp.panel.temporary.invoice.category.review.btnGoto'),
                            styles   : {
                                display: 'block',
                                'float': 'none',
                                margin : '20px auto 0'
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

                ParentContainer = Container;

                return Invoices.getInvoicePreviewHtml(
                    self.getAttribute('invoiceId'),
                    self.getCurrentData()
                ).then(function (html) {
                    return new Promise(function (resolve) {
                        require(['qui/controls/elements/Sandbox'], function (Sandbox) {
                            new Sandbox({
                                content: html,
                                styles : {
                                    height : 1240,
                                    padding: 20,
                                    width  : 874
                                },
                                events : {
                                    onLoad: function (Box) {
                                        //Box.getBody().style.padding = '20px';
                                        Box.getElm().addClass('quiqqer-invoice-backend-temporaryInvoice-preview');
                                    }
                                }
                            }).inject(FrameContainer);

                            resolve();
                        });
                    });
                });
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
                    Missing.set('html', Mustache.render(templateMissing));

                    var Info = new Element('info', {
                        'class': 'quiqqer-invoice-backend-temporaryInvoice-missing-miss-message',
                        styles : {
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
                            });
                        } else {
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
                } else {
                    // post available
                    Missing.set('html', Mustache.render(templatePost));

                    new QUIButton({
                        text  : 'Rechnung buchen',
                        class : 'btn-green',
                        events: {
                            onClick: function () {
                            }
                        }
                    }).inject(
                        Missing.getElement('.quiqqer-invoice-backend-temporaryInvoice-missing-button')
                    );
                }


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

            require([
                'package/quiqqer/invoice/bin/backend/controls/panels/product/AddProductWindow',
                'package/quiqqer/invoice/bin/backend/controls/articles/Article'
            ], function (Win, Article) {
                new Win({
                    events: {
                        onSubmit: function (Win, article) {
                            var Instance = new Article(article);

                            if ("calculated_vatArray" in article) {
                                Instance.setVat(article.calculated_vatArray.vat);
                            }

                            self.$ArticleList.addArticle(Instance);
                        }
                    }
                }).open();

                self.$AddProduct.setAttribute('textimage', 'fa fa-plus');
            });
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

            if (this.$ArticleListSummary) {
                moofx(this.$ArticleListSummary.getElm()).animate({
                    bottom : -20,
                    opacity: 0
                }, {
                    duration: 250,
                    callback: function () {
                        this.$ArticleListSummary.destroy();
                        this.$ArticleListSummary = null;
                    }.bind(this)
                });
            }

            this.getContent().setStyle('padding', 0);

            return new Promise(function (resolve) {
                var Container = this.getContent().getElement('.container');

                if (!Container) {
                    Container = new Element('div', {
                        'class': 'container',
                        styles : {
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
                        this.$unloadCategory();
                        Container.set('html', '');
                        Container.setStyle('padding', 20);

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
         * @param {Boolean} destroyList - destroy the article list, default = true
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

            var Form = Container.getElement('form');

            if (!Form) {
                return;
            }

            var formData = QUIFormUtils.getFormData(Form);
            var data     = this.getAttribute('data') || {};

            // timefields
            if ("date_date" in formData &&
                "date_time" in formData) {
                this.setAttribute(
                    'date',
                    formData.date_date + ' ' + formData.date_time
                );
            }

            ['time_for_payment', 'project_name', 'payment_method'].each(function (entry) {
                if (!formData.hasOwnProperty(entry)) {
                    return;
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

            this.$AddSeparator = new QUISeparator();

            // buttons
            this.addButton({
                name     : 'save',
                text     : QUILocale.get('quiqqer/system', 'save'),
                textimage: 'fa fa-save',
                events   : {
                    onClick: this.save
                }
            });

            this.addButton(this.$AddSeparator);
            this.addButton(this.$AddProduct);

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

            Invoices.getTemporaryInvoice(this.getAttribute('invoiceId')).then(function (data) {
                this.setAttributes(data);

                if (data.articles.articles.length) {
                    this.$serializedList = {
                        articles: data.articles.articles
                    };
                }

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
        }
    });
});