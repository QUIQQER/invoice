/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/Invoice
 * @author www.pcsg.de (Henning Leutz)
 *
 * Shows an invoice
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/Invoice', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/windows/Confirm',
    'package/quiqqer/invoice/bin/Invoices',
    'package/quiqqer/erp/bin/backend/controls/Comments',
    'package/quiqqer/customer/bin/backend/controls/customer/userFiles/Select',
    'qui/controls/elements/Sandbox',
    'utils/Lock',
    'Locale',
    'Ajax',
    'Mustache',

    'css!package/quiqqer/invoice/bin/backend/controls/panels/Invoice.css'

], function(QUI, QUIPanel, QUIButton, QUIConfirm, Invoices, Comments,
    CustomerFileSelect, Sandbox, Locker, QUILocale, QUIAjax, Mustache
) {
    'use strict';

    const lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIPanel,
        Type: 'package/quiqqer/invoice/bin/backend/controls/panels/Invoice',

        Binds: [
            'print',
            'storno',
            'download',
            'copy',
            'creditNote',
            'openInfo',
            'openArticles',
            'openPayments',
            'openHistory',
            'openComments',
            'openPreview',
            'openAddCommentDialog',
            '$onCreate',
            '$onInject',
            '$onDestroy',
            '$showLockMessage',
            'openInvoiceFiles',
            '$openXmlCategory'
        ],

        options: {
            hash: false,
            uuid: false
        },

        initialize: function(options) {
            this.setAttributes({
                icon: 'fa fa-file-text-o'
            });

            this.parent(options);

            if (this.getAttribute('invoiceId') && !this.getAttribute('hash')) {
                this.setAttribute('hash', this.getAttribute('invoiceId'));
            }

            if (this.getAttribute('uuid') && !this.getAttribute('hash')) {
                this.setAttribute('hash', this.getAttribute('uuid'));
            }

            this.$locked = false;

            this.addEvents({
                onCreate: this.$onCreate,
                onInject: this.$onInject,
                onDestroy: this.$onDestroy
            });
        },

        /**
         * Return the lock key
         *
         * @return {string}
         */
        $getLockKey: function() {
            return 'lock-invoice-' + this.getAttribute('hash');
        },

        /**
         * Return the lock group
         * @return {string}
         */
        $getLockGroups: function() {
            return 'quiqqer/invoice';
        },

        /**
         * Refresh the invoice data
         */
        doRefresh: function() {
            const self = this;

            return Promise.all([
                Invoices.get(this.getAttribute('hash')),
                Invoices.hasRefund(this.getAttribute('hash'))
            ]).then(function(response) {
                const data = response[0],
                    hasRefund = response[1];

                self.setAttribute('title', QUILocale.get(lg, 'erp.panel.invoice.title', {
                    id: data.id_with_prefix,
                    hash: data.hash
                }));

                self.setAttribute('data', data);
                self.setAttribute('hasRefund', hasRefund);

                // // refund button
                // var Refund = self.getButtons('actions').getChildren().filter(function (Btn) {
                //     return Btn.getAttribute('name') === 'refund';
                // })[0];
                //
                // if (hasRefund) {
                //     Refund.enable();
                // } else {
                //     Refund.disable();
                // }

                self.refresh();
            });
        },

        /**
         * event: on create
         */
        $onCreate: function() {
            require([
                'package/quiqqer/erp/bin/backend/controls/process/ProcessWindowButton'
            ], (ProcessWindowButton) => {
                new ProcessWindowButton({
                    hash: this.getAttribute('hash')
                }).inject(this.getHeader());
            });

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


            const Actions = new QUIButton({
                name: 'actions',
                text: QUILocale.get(lg, 'journal.btn.actions'),
                menuCorner: 'topRight',
                styles: {
                    'float': 'right'
                }
            });

            Actions.appendChild({
                icon: 'fa fa-times-circle-o',
                text: QUILocale.get(lg, 'erp.panel.invoice.button.storno'),
                styles: {
                    'float': 'right'
                },
                events: {
                    onClick: this.storno
                }
            });

            Actions.appendChild({
                icon: 'fa fa-copy',
                text: QUILocale.get(lg, 'erp.panel.invoice.button.copy'),
                events: {
                    onClick: this.copy
                }
            });

            Actions.appendChild({
                icon: 'fa fa-clipboard',
                text: QUILocale.get(lg, 'erp.panel.invoice.button.createCreditNote'),
                events: {
                    onClick: this.creditNote
                }
            });

            Actions.appendChild({
                icon: 'fa fa-download',
                text: QUILocale.get(lg, 'erp.panel.invoice.button.download'),
                events: {
                    onClick: this.download
                }
            });

            this.fireEvent('actionButtonCreate', [
                this,
                Actions
            ]);

            QUI.fireEvent('quiqqerInvoiceActionButtonCreate', [
                this,
                Actions
            ]);


            this.addButton(Actions);

            // create the buttons (top bar)
            this.addButton({
                textimage: 'fa fa-print',
                text: QUILocale.get(lg, 'erp.panel.invoice.button.print'),
                events: {
                    onClick: this.print
                },
                styles: {
                    'float': 'right'
                }
            });

            // create the categories (left bar)
            this.addCategory({
                icon: 'fa fa-info',
                name: 'info',
                title: QUILocale.get(lg, 'erp.panel.invoice.data'),
                text: QUILocale.get(lg, 'erp.panel.invoice.data'),
                events: {
                    onClick: this.openInfo
                }
            });

            /*
            this.addCategory({
                icon: 'fa fa-list',
                name: 'articles',
                title: QUILocale.get(lg, 'erp.panel.invoice.articles'),
                text: QUILocale.get(lg, 'erp.panel.invoice.articles'),
                events: {
                    onClick: this.openArticles
                }
            });
            */

            this.addCategory({
                icon: 'fa fa-money',
                name: 'payments',
                title: QUILocale.get(lg, 'erp.panel.invoice.payments'),
                text: QUILocale.get(lg, 'erp.panel.invoice.payments'),
                events: {
                    onClick: this.openPayments
                }
            });

            this.addCategory({
                icon: 'fa fa-history',
                name: 'history',
                title: QUILocale.get(lg, 'erp.panel.invoice.history'),
                text: QUILocale.get(lg, 'erp.panel.invoice.history'),
                events: {
                    onClick: this.openHistory
                }
            });

            this.addCategory({
                icon: 'fa fa-comments',
                name: 'comments',
                title: QUILocale.get(lg, 'erp.panel.invoice.comments'),
                text: QUILocale.get(lg, 'erp.panel.invoice.comments'),
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
                icon: 'fa fa-eye',
                name: 'preview',
                title: QUILocale.get(lg, 'erp.panel.invoice.preview'),
                text: QUILocale.get(lg, 'erp.panel.invoice.preview'),
                events: {
                    onClick: this.openPreview
                }
            });

            this.getContent().addClass('quiqqer-invoice-invoice');


            // invoice.xml panel api
            QUIAjax.get('package_quiqqer_invoice_ajax_invoices_panel_getCategories', (categories) => {
                let cat, title;

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


                    this.fireEvent('categoryCreate', [
                        this,
                        Actions
                    ]);

                    QUI.fireEvent('quiqqerInvoiceCategoryCreate', [
                        this,
                        Actions
                    ]);
                }
            }, {
                'package': 'quiqqer/invoice'
            });
        },

        /**
         * event: on inject
         */
        $onInject: function() {
            const self = this;

            this.Loader.show();

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
                self.openPreview();
            }).catch(function(e) {
                console.error(e);

                QUI.getMessageHandler().then(function(MH) {
                    MH.addError(e.getMessage());
                });

                self.destroy();
            });
        },

        /**
         * event: on destroy
         */
        $onDestroy: function() {
            Locker.unlock(
                this.$getLockKey(),
                this.$getLockGroups()
            );
        },

        /**
         * lock the complete panel
         */
        lockPanel: function() {
            this.getButtons('actions').disable();
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
                self.getButtons('actions').enable();
                self.getButtons('lock').hide();

                self.getButtons('lock').setAttribute(
                    'title',
                    QUILocale.get(lg, 'message.invoice.is.locked', isLocked)
                );

                return self.refresh();
            }).then(function() {
                return self.openInfo();
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
         * Opens the print dialog
         *
         * @return {Promise}
         */
        print: function() {
            const self = this,
                Data = self.getAttribute('data');

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

            return new Promise(function(resolve) {
                require([
                    'package/quiqqer/invoice/bin/backend/utils/Dialogs'
                ], function(Dialogs) {
                    Dialogs.openPrintDialog(Data.hash, entityType).then(resolve);
                });
            });
        },

        /**
         * Opens the copy dialog
         *
         * @return {Promise}
         */
        copy: function() {
            return new Promise((resolve) => {
                require([
                    'package/quiqqer/erp/bin/backend/controls/dialogs/CopyErpEntityDialog'
                ], (CopyErpEntityDialog) => {
                    new CopyErpEntityDialog({
                        hash: this.getAttribute('hash'),
                        entityPlugin: 'quiqqer/invoice',
                        events: {
                            onSuccess: resolve
                        }
                    }).open();
                });
            });
        },

        /**
         * Opens the copy dialog
         *
         * @return {Promise}
         */
        creditNote: function() {
            const self = this;

            return new Promise(function(resolve) {
                require([
                    'package/quiqqer/invoice/bin/backend/utils/Dialogs'
                ], function(Dialogs) {
                    Dialogs.openCreateCreditNoteDialog(self.getAttribute('data').hash).then(function(invoiceId) {
                        if (!invoiceId) {
                            return;
                        }

                        return new Promise(function(res) {
                            require([
                                'package/quiqqer/invoice/bin/backend/utils/Panels'
                            ], function(PanelUtils) {
                                PanelUtils.openTemporaryInvoice(invoiceId).then(res);
                            });
                        });
                    }).then(resolve);
                });
            });
        },

        download: function()
        {
            require(['package/quiqqer/invoice/bin/backend/utils/Dialogs'], (Dialogs)=> {
                Dialogs.openDownloadDialog(this.getAttribute('data').hash);
            });
        },

        /**
         * Opens the storno / cancellation dialog
         *
         * @return {Promise}
         */
        storno: function() {
            const self = this;

            return new Promise(function(resolve, reject) {
                require([
                    'package/quiqqer/invoice/bin/backend/utils/Dialogs'
                ], function(Dialogs) {
                    Dialogs.openStornoDialog(self.getAttribute('data').hash).then(function() {
                        return self.refresh();
                    }).then(function() {
                        resolve();
                    }).catch(function(Error) {
                        reject(Error);

                        QUI.getMessageHandler().then(function(MH) {
                            MH.addError(Error.getMessage());
                        });
                    });
                });
            });
        },

        //region Categories

        /**
         * Open the information
         */
        openInfo: function() {
            const self = this;

            this.Loader.show();
            this.getCategory('info').setActive();

            return this.$closeCategory().then(function(Container) {
                return new Promise(function(resolve) {
                    require([
                        'text!package/quiqqer/invoice/bin/backend/controls/panels/Invoice.Data.html'
                    ], function(template) {
                        let data = self.getAttribute('data');

                        if (typeOf(data) !== 'object') {
                            data = {};
                        }

                        data.textInvoiceStatus = QUILocale.get(lg, 'invoice.settings.processingStatus.title');
                        data.textInvoiceRecipient = QUILocale.get(lg, 'cutomerData');
                        data.textCustomer = QUILocale.get(lg, 'customer');
                        data.textCompany = QUILocale.get(lg, 'company');
                        data.textStreet = QUILocale.get(lg, 'street');
                        data.textZip = QUILocale.get(lg, 'zip');
                        data.textCity = QUILocale.get(lg, 'city');

                        data.textInvoiceDelivery = QUILocale.get(lg, 'deliveryAddress');
                        data.textDeliveryCompany = QUILocale.get(lg, 'company');
                        data.textDeliveryStreet = QUILocale.get(lg, 'street');
                        data.textDeliveryZip = QUILocale.get(lg, 'zip');
                        data.textDeliveryCity = QUILocale.get(lg, 'city');
                        data.textDeliveryCountry = QUILocale.get(lg, 'country');

                        data.textInvoiceData = QUILocale.get(lg, 'erp.panel.invoice.data.title');
                        data.textInvoiceDate = QUILocale.get(lg, 'erp.panel.invoice.data.date');
                        data.textProjectName = QUILocale.get(lg, 'erp.panel.invoice.data.projectName');
                        data.textOrderedBy = QUILocale.get(lg, 'erp.panel.invoice.data.orderedBy');
                        data.textEditor = QUILocale.get(lg, 'erp.panel.invoice.data.editor');
                        data.textContactPerson = QUILocale.get(lg, 'erp.panel.invoice.data.contactPerson');

                        data.textInvoicePayment = QUILocale.get(lg, 'erp.panel.invoice.data.payment');
                        data.textInvoicePaymentMethod = QUILocale.get(lg, 'erp.panel.invoice.data.paymentMethod');
                        data.textTermOfPayment = QUILocale.get(lg, 'erp.panel.invoice.data.termOfPayment');

                        data.textInvoiceText = QUILocale.get(lg, 'erp.panel.invoice.data.invoiceText');

                        if (data.additional_invoice_text === '') {
                            data.additional_invoice_text = '&nbsp;';
                        }

                        Container.set({
                            html: Mustache.render(template, data)
                        });

                        const Form = Container.getElement('form');
                        let address = {};

                        try {
                            address = JSON.decode(data.invoice_address);

                            Form.elements.customer.value =
                                address.salutation + ' ' + address.firstname + ' ' + address.lastname;
                            Form.elements.company.value = address.company;
                            Form.elements.street_no.value = address.street_no;
                            Form.elements.zip.value = address.zip;
                            Form.elements.city.value = address.city;
                        } catch (e) {
                            console.error(e);
                        }

                        // payment
                        try {
                            const paymentData = JSON.decode(data.payment_method_data);

                            if (typeof paymentData.paymentType !== 'undefined' &&
                                typeof paymentData.paymentType.title !== 'undefined') {
                                Form.elements.payment_method.value = paymentData.paymentType.title;
                            }
                        } catch (e) {
                        }

                        if (data.delivery_address !== '') {
                            Container.getElement('.invoice-delivery-data').setStyle('display', null);

                            try {
                                const deliveryAddress = JSON.decode(data.delivery_address);

                                if (typeof deliveryAddress.salutation === 'undefined') {
                                    deliveryAddress.salutation = '';
                                }

                                if (typeof deliveryAddress.firstname === 'undefined') {
                                    deliveryAddress.firstname = address.firstname;
                                }

                                if (typeof deliveryAddress.lastname === 'undefined') {
                                    deliveryAddress.lastname = address.lastname;
                                }

                                Form.elements['delivery-customer'].value = deliveryAddress.salutation + ' ' +
                                    deliveryAddress.firstname + ' ' +
                                    deliveryAddress.lastname;

                                Form.elements['delivery-company'].value = deliveryAddress.company;
                                Form.elements['delivery-street_no'].value = deliveryAddress.street_no;
                                Form.elements['delivery-zip'].value = deliveryAddress.zip;
                                Form.elements['delivery-city'].value = deliveryAddress.city;
                            } catch (e) {
                                console.error(e);
                            }
                        }

                        if (Form.elements.processing_status) {
                            Form.elements.processing_status.value = data.processing_status;
                        }

                        QUI.parse(Container).then(function() {
                            const Processing = QUI.Controls.getById(
                                Container.getElement('[name="processing_status"]').get('data-quiid')
                            );

                            Processing.addEvent('onChange', function() {
                                self.Loader.show();
                                self.setProcessingStatus(Processing.getValue()).then(function() {
                                    self.Loader.hide();
                                    self.showSavedIconAnimation();
                                });
                            });

                            resolve();
                        });
                    });
                });
            }).then(function() {
                return self.$openCategory();
            }).then(function() {
                self.Loader.hide();
            });
        },

        /**
         * open articles
         * @deprecated
         */
        openArticles: function() {
            return;
            const self = this;

            this.Loader.show();
            this.getCategory('articles').setActive();

            return Promise.all([
                this.$closeCategory(),
                Invoices.getInvoicePreview(self.getAttribute('data').hash, true)
                //Invoices.getArticlesHtml(self.getAttribute('data').id)
            ]).then(function(result) {
                const Container = result[0];

                return new Promise(function(resolve) {
                    Container.set('html', '');
                    Container.setStyle('padding', 0);

                    const Pager = new Element('div', {
                        'class': 'quiqqer-invoice-backend-invoice-previewContainer'
                    }).inject(Container);

                    new Sandbox({
                        content: result[1],
                        styles: {
                            border: 0,
                            height: '100%',
                            width: '100%'
                        },
                        events: {
                            onLoad: function(Box) {
                                Box.getElm().addClass('quiqqer-invoice-backend-invoice-preview');
                                resolve();
                            }
                        }
                    }).inject(Pager);
                });
            }).then(function() {
                return self.$openCategory();
            }).then(function() {
                self.Loader.hide();
            }).catch(function(err) {
                console.error(err.getMessage());
                console.error(err);
                self.Loader.hide();
            });
        },

        /**
         * Open payments list
         */
        openPayments: function() {
            const self = this;

            this.Loader.show();
            this.getCategory('payments').setActive();

            return this.$closeCategory().then(function(Container) {
                return new Promise(function(resolve) {
                    require([
                        'package/quiqqer/payment-transactions/bin/backend/controls/IncomingPayments/TransactionList'
                    ], function(TransactionList) {
                        new TransactionList({
                            Panel: self,
                            hash: self.getAttribute('data').hash,
                            entityType: 'Invoice',
                            disabled: self.$locked || self.getAttribute('data').paid_status === 1,
                            events: {
                                onLoad: resolve,
                                onLinkTransaction: (txId, Control) => {
                                    Invoices.linkTransaction(
                                        self.getAttribute('data').hash,
                                        txId
                                    ).then(function() {
                                        Control.refresh();
                                    });
                                }
                            }
                        }).inject(Container);
                    });
                });
            }).then(function() {
                return self.$openCategory();
            }).then(function() {
                self.Loader.hide();
            });
        },

        /**
         * open History
         *
         * @return {Promise}
         */
        openHistory: function() {
            const self = this;

            this.Loader.show();
            this.getCategory('history').setActive();

            return this.$closeCategory().then(function(Container) {
                return Promise.all([
                    Invoices.getInvoiceHistory(self.getAttribute('data').hash),
                    Container
                ]);
            }).then(function(result) {
                new Comments({
                    comments: result[0]
                }).inject(result[1]);
            }).then(function() {
                return self.$openCategory();
            }).then(function() {
                self.Loader.hide();
            });
        },

        /**
         * open comments
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

                return new Promise((resolve) => {
                    require([
                        'package/quiqqer/erp/bin/backend/controls/customerFiles/Grid'
                    ], (FileGrid) => {
                        new FileGrid({
                            hash: this.getAttribute('hash')
                        }).inject(Container);

                        resolve();
                    });
                });
            }).then(() => {
                this.Loader.hide();
                return this.$openCategory();
            }).catch((err) => {
                console.error('ERROR');
                console.error(err);

                return this.$openCategory();
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
                comments: this.getAttribute('data').comments
            }).inject(Container);
        },

        /**
         * open preview
         */
        openPreview: function() {
            const self = this;

            this.Loader.show();
            this.getCategory('preview').setActive();

            return this.$closeCategory().then(function(Container) {
                const StatusContainer = new Element('div', {
                    'class': 'quiqqer-invoice-backend-invoice-statusContainer'
                }).inject(Container);

                let data = self.getAttribute('data');

                if (typeOf(data) !== 'object') {
                    data = {};
                }

                new Element('span', {
                    html: QUILocale.get(lg, 'invoice.settings.processingStatus.preview.change'),
                    styles: {
                        color: '#666',
                        lineHeight: 30,
                        padding: '0 15px',
                        whiteSpace: 'nowrap'
                    }
                }).inject(StatusContainer);

                new Element('input', {
                    type: 'hidden',
                    name: 'processing_status',
                    'data-qui': 'package/quiqqer/invoice/bin/backend/controls/settings/ProcessingSelect',
                    value: data.processing_status
                }).inject(StatusContainer);

                return QUI.parse(StatusContainer).then(function() {
                    const Processing = QUI.Controls.getById(
                        Container.getElement('[name="processing_status"]').get('data-quiid')
                    );

                    Processing.addEvent('onChange', function() {
                        self.Loader.show();
                        self.setProcessingStatus(Processing.getValue()).then(function() {
                            self.Loader.hide();
                            self.showSavedIconAnimation();
                        });
                    });

                    return Container;
                });
            }).then(function(Container) {
                const FrameContainer = new Element('div', {
                    'class': 'quiqqer-invoice-backend-invoice-previewContainer',
                    styles: {
                        height: 'calc(100% - 30px)'
                    }
                }).inject(Container);

                Container.setStyle('overflow', 'hidden');
                Container.setStyle('padding', 0);
                Container.setStyle('height', '100%');

                return Invoices.getInvoicePreview(self.getAttribute('data').hash).then(function(html) {
                    new Sandbox({
                        content: html,
                        styles: {
                            height: '100%',
                            padding: 20,
                            width: '100%'
                        },
                        events: {
                            onLoad: function(Box) {
                                Box.getElm().addClass('quiqqer-invoice-backend-invoice-preview');
                            }
                        }
                    }).inject(FrameContainer);
                });
            }).then(function() {
                return self.$openCategory();
            }).then(function() {
                self.Loader.hide();
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
         * Close the current category
         *
         * @returns {Promise}
         */
        $closeCategory: function() {
            this.getContent().setStyle('padding', 0);

            return new Promise(function(resolve) {
                let Container = this.getContent().getElement('.container');

                if (!Container) {
                    Container = new Element('div', {
                        'class': 'container',
                        styles: {
                            height: '100%',
                            opacity: 0,
                            position: 'relative',
                            top: -50
                        }
                    }).inject(this.getContent());
                }

                Container.setStyle('overflow', null);

                moofx(Container).animate({
                    opacity: 0,
                    top: -50
                }, {
                    duration: 200,
                    callback: function() {
                        Container.set('html', '');
                        Container.setStyle('padding', 20);

                        resolve(Container);
                    }.bind(this)
                });
            }.bind(this));
        },

        //endregion

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
         * Set the processing status for the invoice
         *
         * @param {Number} processingStatus
         * @return {Promise}
         */
        setProcessingStatus: function(processingStatus) {
            const self = this;

            return new Promise(function(resolve) {
                require(['Ajax'], function(QUIAjax) {
                    QUIAjax.post('package_quiqqer_invoice_ajax_invoices_setStatus', function() {
                        let data = self.getAttribute('data');

                        if (typeOf(data) !== 'object') {
                            data = {};
                        }

                        data.processing_status = processingStatus;

                        self.setAttribute('data', data);

                        resolve();
                    }, {
                        'package': 'quiqqer/invoice',
                        invoiceId: self.getAttribute('hash'),
                        status: processingStatus
                    });
                });
            });
        },

        //region category stuff

        /**
         * @param Category
         */
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
