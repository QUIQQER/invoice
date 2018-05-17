/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/Journal
 * @author www.pcsg.de (Henning Leutz)
 *
 * List all posted invoices
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/Journal', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/Separator',
    'qui/controls/buttons/Select',
    'qui/controls/windows/Confirm',
    'controls/grid/Grid',
    'package/quiqqer/invoice/bin/Invoices',
    'package/quiqqer/invoice/bin/backend/controls/elements/TimeFilter',
    'Locale',
    'Mustache',

    'text!package/quiqqer/invoice/bin/backend/controls/panels/Journal.InvoiceDetails.html',
    'text!package/quiqqer/invoice/bin/backend/controls/panels/Journal.Total.html',
    'css!package/quiqqer/invoice/bin/backend/controls/panels/Journal.css'

], function (QUI, QUIPanel, QUIButton, QUISeparator, QUISelect, QUIConfirm, Grid, Invoices, TimeFilter,
             QUILocale, Mustache, templateInvoiceDetails, templateTotal) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/panels/Journal',

        Binds: [
            'refresh',
            'toggleTotal',
            'showTotal',
            'closeTotal',
            '$onCreate',
            '$onDestroy',
            '$onResize',
            '$onInject',
            '$onInvoicesChange',
            '$refreshButtonStatus',
            '$onPDFExportButtonClick',
            '$onAddPaymentButtonClick',
            '$onClickCopyInvoice',
            '$onClickInvoiceDetails',
            '$onClickOpenInvoice',
            '$onClickCreateCredit',
            '$onClickReversal'
        ],

        initialize: function (options) {
            this.setAttributes({
                icon : 'fa fa-money',
                title: QUILocale.get(lg, 'erp.panel.invoice.text')
            });

            this.parent(options);

            this.$Grid       = null;
            this.$Status     = null;
            this.$TimeFilter = null;
            this.$Total      = null;

            this.addEvents({
                onCreate: this.$onCreate,
                onResize: this.$onResize,
                onInject: this.$onInject,
                onDelete: this.$onDestroy
            });

            Invoices.addEvents({
                onPostInvoice: this.$onInvoicesChange
            });
        },

        /**
         * Refresh the grid
         */
        refresh: function () {
            this.Loader.show();

            if (!this.$Grid) {
                return;
            }

            Invoices.search({
                perPage: this.$Grid.options.perPage,
                page   : this.$Grid.options.page
            }, {
                from       : this.$TimeFilter.getValue().from,
                to         : this.$TimeFilter.getValue().to,
                paid_status: [
                    this.$Status.getValue()
                ]
            }).then(function (result) {
                var gridData = result.grid;

                gridData.data = gridData.data.map(function (entry) {
                    var Icon = new Element('span');

                    switch (parseInt(entry.type)) {
                        // gutschrift
                        case 3:
                            Icon.addClass('fa fa-clipboard');
                            Icon.set('title', QUILocale.get(lg, 'invoice.type.creditNote'));
                            break;

                        // storno
                        case 4:
                            Icon.addClass('fa fa-check-circle-o');
                            Icon.set('title', QUILocale.get(lg, 'invoice.type.reversal'));
                            break;

                        case 5:
                            Icon.addClass('fa fa-times-circle-o');
                            Icon.set('title', QUILocale.get(lg, 'invoice.type.cancel'));
                            entry.className = 'journal-grid-cancel';
                            break;

                        default:
                            Icon.addClass('fa fa-file-text-o');
                            Icon.set('title', QUILocale.get(lg, 'invoice.type.invoice'));
                    }

                    switch (parseInt(entry.isbrutto)) {
                        case 1:
                            entry.display_isbrutto = new Element('span', {
                                'class': 'fa fa-minus'
                            });
                            break;

                        case 2:
                            entry.display_isbrutto = new Element('span', {
                                'class': 'fa fa-check'
                            });
                            break;
                    }

                    entry.display_type = Icon;
                    entry.opener       = '&nbsp;';

                    if ("overdue" in entry && entry.overdue) {
                        entry.className = 'journal-grid-overdue';
                    }

                    return entry;
                });


                this.$Grid.setData(gridData);
                this.$refreshButtonStatus();

                this.$Total.set(
                    'html',
                    Mustache.render(templateTotal, result.total)
                );

                this.Loader.hide();
            }.bind(this));
        },

        /**
         * refresh the button status
         * disabled or enabled
         */
        $refreshButtonStatus: function () {
            if (!this.$Grid) {
                return;
            }

            var selected = this.$Grid.getSelectedData(),
                buttons  = this.$Grid.getButtons();

            var Actions = buttons.filter(function (Button) {
                return Button.getAttribute('name') === 'actions';
            })[0];

            var Payment = Actions.getChildren().filter(function (Button) {
                return Button.getAttribute('name') === 'addPayment';
            })[0];

            var PDF = buttons.filter(function (Button) {
                return Button.getAttribute('name') === 'printPdf';
            })[0];

            var Open = buttons.filter(function (Button) {
                return Button.getAttribute('name') === 'open';
            })[0];

            if (selected.length) {
                if (selected[0].paid_status === 1 ||
                    selected[0].paid_status === 5) {
                    Payment.disable();
                } else {
                    Payment.enable();
                }

                Open.enable();
                PDF.enable();
                Actions.enable();
                return;
            }

            Open.disable();
            Actions.disable();
            PDF.disable();
        },

        /**
         * event : on create
         */
        $onCreate: function () {
            // Buttons
            this.addButton({
                name     : 'total',
                text     : QUILocale.get(lg, 'journal.btn.total'),
                textimage: 'fa fa-calculator',
                events   : {
                    onClick: this.toggleTotal
                }
            });

            this.$Status = new QUISelect({
                showIcons: false,
                styles   : {
                    'float': 'right'
                },
                events   : {
                    onChange: this.refresh
                }
            });

            this.$Status.appendChild(
                QUILocale.get(lg, 'journal.paidstatus.all'),
                ''
            );

            this.$Status.appendChild(
                QUILocale.get(lg, 'journal.paidstatus.open'),
                Invoices.PAYMENT_STATUS_OPEN
            );

            this.$Status.appendChild(
                QUILocale.get(lg, 'journal.paidstatus.paid'),
                Invoices.PAYMENT_STATUS_PAID
            );

            this.$Status.appendChild(
                QUILocale.get(lg, 'journal.paidstatus.partial'),
                Invoices.PAYMENT_STATUS_PART
            );

            this.$Status.appendChild(
                QUILocale.get(lg, 'journal.paidstatus.canceled'),
                Invoices.PAYMENT_STATUS_CANCELED
            );

            this.addButton(this.$Status);

            var Separator = new QUISeparator();
            this.addButton(Separator);

            Separator.getElm().setStyles({
                'float': 'right'
            });

            this.$TimeFilter = new TimeFilter({
                name  : 'timeFilter',
                styles: {
                    'float': 'right'
                },
                events: {
                    onChange: this.refresh
                }
            });

            this.addButton(this.$TimeFilter);

            // Grid
            this.getContent().setStyles({
                padding : 10,
                position: 'relative'
            });

            var Container = new Element('div').inject(this.getContent());

            var Actions = new QUIButton({
                name      : 'actions',
                text      : QUILocale.get(lg, 'journal.btn.actions'),
                menuCorner: 'topRight',
                styles    : {
                    'float': 'right'
                }
            });

            Actions.appendChild({
                name  : 'addPayment',
                text  : QUILocale.get(lg, 'journal.btn.paymentBook'),
                icon  : 'fa fa-money',
                events: {
                    onClick: this.$onAddPaymentButtonClick
                }
            });

            Actions.appendChild({
                name  : 'cancel',
                text  : QUILocale.get(lg, 'journal.btn.cancelInvoice'),
                icon  : 'fa fa-times-circle-o',
                events: {
                    onClick: this.$onClickReversal
                }
            });

            Actions.appendChild({
                name  : 'copy',
                text  : QUILocale.get(lg, 'journal.btn.copyInvoice'),
                icon  : 'fa fa-copy',
                events: {
                    onClick: this.$onClickCopyInvoice
                }
            });

            Actions.appendChild({
                name  : 'createCreditNote',
                text  : QUILocale.get(lg, 'journal.btn.createCreditNote'),
                icon  : 'fa fa-clipboard',
                events: {
                    onClick: this.$onClickCreateCredit
                }
            });


            this.$Grid = new Grid(Container, {
                pagination           : true,
                accordion            : true,
                autoSectionToggle    : false,
                openAccordionOnClick : false,
                toggleiconTitle      : '',
                accordionLiveRenderer: this.$onClickInvoiceDetails,
                buttons              : [Actions, {
                    name     : 'open',
                    text     : QUILocale.get(lg, 'journal.btn.open'),
                    textimage: 'fa fa-file-o',
                    disabled : true,
                    events   : {
                        onClick: this.$onClickOpenInvoice
                    }
                }, {
                    name     : 'printPdf',
                    text     : QUILocale.get(lg, 'journal.btn.pdf'),
                    textimage: 'fa fa-print',
                    disabled : true,
                    events   : {
                        onClick: this.$onPDFExportButtonClick
                    }
                }],
                columnModel          : [{
                    header   : '&nbsp;',
                    dataIndex: 'opener',
                    dataType : 'int',
                    width    : 30
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.type'),
                    dataIndex: 'display_type',
                    dataType : 'node',
                    width    : 30
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.invoiceNo'),
                    dataIndex: 'id',
                    dataType : 'integer',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.orderNo'),
                    dataIndex: 'order_id',
                    dataType : 'integer',
                    width    : 80
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.customerNo'),
                    dataIndex: 'customer_id',
                    dataType : 'integer',
                    width    : 100,
                    className: 'clickable'
                }, {
                    header   : QUILocale.get('quiqqer/system', 'name'),
                    dataIndex: 'customer_name',
                    dataType : 'string',
                    width    : 130,
                    className: 'clickable'
                }, {
                    header   : QUILocale.get('quiqqer/system', 'date'),
                    dataIndex: 'date',
                    dataType : 'date',
                    width    : 100
                }, {
                    header   : QUILocale.get('quiqqer/system', 'c_user'),
                    dataIndex: 'c_username',
                    dataType : 'string',
                    width    : 130
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.status'),
                    dataIndex: 'paid_status_display',
                    dataType : 'string',
                    width    : 120
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.netto'),
                    dataIndex: 'display_nettosum',
                    dataType : 'currency',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.vat'),
                    dataIndex: 'display_vatsum',
                    dataType : 'currency',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.sum'),
                    dataIndex: 'display_sum',
                    dataType : 'currency',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.paymentMethod'),
                    dataIndex: 'payment_title',
                    dataType : 'string',
                    width    : 180
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.timeForPayment'),
                    dataIndex: 'time_for_payment',
                    dataType : 'date',
                    width    : 120
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.paymentDate'),
                    dataIndex: 'paid_date',
                    dataType : 'date',
                    width    : 120
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.paid'),
                    dataIndex: 'display_paid',
                    dataType : 'currency',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.open'),
                    dataIndex: 'display_toPay',
                    dataType : 'currency',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.brutto'),
                    dataIndex: 'display_isbrutto',
                    dataType : 'node',
                    width    : 50
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.taxId'),
                    dataIndex: 'taxId',
                    dataType : 'string',
                    width    : 120
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.orderDate'),
                    dataIndex: 'order_date',
                    dataType : 'date',
                    width    : 140
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.dunning'),
                    dataIndex: 'dunning_level_display',
                    dataType : 'string',
                    width    : 80
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.processingStatus'),
                    dataIndex: 'processing',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.hash'),
                    dataIndex: 'hash',
                    dataType : 'string',
                    width    : 280,
                    className: 'monospace'
                }]
            });

            this.$Grid.addEvents({
                onRefresh : this.refresh,
                onClick   : this.$refreshButtonStatus,
                onDblClick: this.$onClickOpenInvoice
            });


            this.$Total = new Element('div', {
                'class': 'journal-total'
            }).inject(this.getContent());
        },

        /**
         * event : on resize
         */
        $onResize: function () {
            if (!this.$Grid) {
                return;
            }

            var Body = this.getContent();

            if (!Body) {
                return;
            }

            var size = Body.getSize();

            this.$Grid.setHeight(size.y - 20);
            this.$Grid.setWidth(size.x - 20);
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var value = this.$Status.getValue();

            if (value === '' || !value) {
                this.$Status.setValue('');
            }
        },

        /**
         * event: on panel destroy
         */
        $onDestroy: function () {
            Invoices.removeEvents({
                onPostInvoice: this.$onInvoicesChange
            });
        },

        /**
         * event: invoices changed something
         */
        $onInvoicesChange: function () {
            this.refresh();
        },

        //region Buttons events

        /**
         * event : on PDF Export button click
         */
        $onPDFExportButtonClick: function (Button) {
            var selectedData = this.$Grid.getSelectedData();

            if (!selectedData.length) {
                return;
            }

            Button.setAttribute('textimage', 'fa fa-spinner fa-spin');

            selectedData = selectedData[0];

            require([
                'package/quiqqer/invoice/bin/backend/controls/elements/PrintDialog'
            ], function (PrintDialog) {
                new PrintDialog({
                    invoiceId: selectedData.id
                }).open();

                Button.setAttribute('textimage', 'fa fa-print');
            });


            /*
             selectedData = selectedData[0];

             this.downloadPdf(selectedData.id).then(function () {
             Button.setAttribute('textimage', 'fa fa-file-pdf-o');
             });

             */
        },

        /**
         * event : on payment add button click
         */
        $onAddPaymentButtonClick: function (Button) {
            var self         = this,
                selectedData = this.$Grid.getSelectedData();

            if (!selectedData.length) {
                return;
            }

            Button.setAttribute('textimage', 'fa fa-spinner fa-spin');

            var hash = selectedData[0].hash;

            require([
                'package/quiqqer/invoice/bin/backend/controls/panels/payments/AddPaymentWindow'
            ], function (AddPaymentWindow) {
                new AddPaymentWindow({
                    hash  : hash,
                    events: {
                        onSubmit: function (Win, data) {
                            self.addPayment(
                                hash,
                                data.amount,
                                data.payment_method,
                                data.date
                            ).then(function () {
                                Button.setAttribute('textimage', 'fa fa-money');
                                self.refresh();
                            });
                        },

                        onClose: function () {
                            Button.setAttribute('textimage', 'fa fa-money');
                        }
                    }
                }).open();
            });
        },

        /**
         * Copy the temporary invoice and opens the invoice
         */
        $onClickCopyInvoice: function () {
            var self     = this,
                selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            new QUIConfirm({
                title      : QUILocale.get(lg, 'dialog.invoice.copy.title'),
                text       : QUILocale.get(lg, 'dialog.invoice.copy.text'),
                information: QUILocale.get(lg, 'dialog.invoice.copy.information', {
                    id: selected[0].id
                }),
                icon       : 'fa fa-copy',
                texticon   : 'fa fa-copy',
                maxHeight  : 400,
                maxWidth   : 600,
                autoclose  : false,
                ok_button  : {
                    text     : QUILocale.get('quiqqer/system', 'copy'),
                    textimage: 'fa fa-copy'
                },
                events     : {
                    onSubmit: function (Win) {
                        Win.Loader.show();

                        Invoices.copyInvoice(selected[0].id).then(function (newId) {
                            Win.close();

                            return self.openTemporaryInvoice(newId);
                        }).then(function () {
                            Win.Loader.show();
                        });
                    }
                }
            }).open();
        },

        /**
         * Open the accordion details of the invoice
         *
         * @param {Object} data
         */
        $onClickInvoiceDetails: function (data) {
            var row        = data.row,
                ParentNode = data.parent;

            ParentNode.setStyle('padding', 10);
            ParentNode.set('html', '<div class="fa fa-spinner fa-spin"></div>');

            Invoices.get(this.$Grid.getDataByRow(row).id).then(function (result) {
                var articles = [];

                if ("articles" in result) {
                    try {
                        articles = JSON.decode(result.articles);
                    } catch (e) {
                    }
                }

                var list = articles.articles;

                for (var i = 0, len = list.length; i < len; i++) {
                    list[i].position  = i + 1;
                    list[i].articleNo = list[i].articleNo || '---';
                }

                ParentNode.set('html', Mustache.render(templateInvoiceDetails, {
                    articles       : list,
                    calculations   : articles.calculations,
                    display_sum    : result.display_sum,
                    display_subsum : result.display_subsum,
                    display_vatsum : result.display_vatsum,
                    textPosition   : '#',
                    textArticleNo  : QUILocale.get(lg, 'invoice.products.articleNo'),
                    textDescription: QUILocale.get(lg, 'invoice.products.description'),
                    textQuantity   : QUILocale.get(lg, 'invoice.products.quantity'),
                    textUnitPrice  : QUILocale.get(lg, 'invoice.products.unitPrice'),
                    textTotalPrice : QUILocale.get(lg, 'invoice.products.price')
                }));
            });
        },

        /**
         * event: on click open invoice
         *
         * @param {object} data - cell data
         * @return {Promise}
         */
        $onClickOpenInvoice: function (data) {
            if (typeof data !== 'undefined' &&
                (data.cell.get('data-index') === 'customer_id' ||
                    data.cell.get('data-index') === 'customer_name')) {

                var self = this;

                return new Promise(function (resolve) {
                    require(['utils/Panels'], function (PanelUtils) {
                        PanelUtils.openUserPanel(
                            self.$Grid.getDataByRow(data.row).customer_id
                        );

                        resolve();
                    });
                });
            }

            var selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return Promise.resolve();
            }

            return this.openInvoice(selected[0].id);
        },

        /**
         * Opens the create credit dialog
         *
         * @return {Promise}
         */
        $onClickCreateCredit: function () {
            var selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return Promise.resolve();
            }

            var self      = this,
                invoiceId = selected[0].id;

            return new Promise(function (resolve) {

                new QUIConfirm({
                    icon       : 'fa fa-clipboard',
                    texticon   : 'fa fa-clipboard',
                    title      : QUILocale.get(lg, 'dialog.invoice.createCreditNote.title', {
                        invoiceId: invoiceId
                    }),
                    text       : QUILocale.get(lg, 'dialog.invoice.createCreditNote.text', {
                        invoiceId: invoiceId
                    }),
                    information: QUILocale.get(lg, 'dialog.invoice.createCreditNote.information', {
                        invoiceId: invoiceId
                    }),
                    autoclose  : false,
                    ok_button  : {
                        text     : QUILocale.get(lg, 'dialog.invoice.createCreditNote.submit'),
                        textimage: 'fa fa-clipboard'
                    },
                    maxHeight  : 400,
                    maxWidth   : 600,
                    events     : {
                        onSubmit: function (Win) {
                            Win.Loader.show();

                            Invoices.createCreditNote(invoiceId).then(function (newId) {
                                return self.openTemporaryInvoice(newId);
                            }).then(function () {
                                Win.close();
                            });
                        },

                        onCancel: resolve
                    }
                }).open();

            });
        },

        /**
         * Opens the reversal dialog
         * - Invoice cancel
         * - Storno
         *
         * @return {Promise}
         */
        $onClickReversal: function () {
            var selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return Promise.resolve();
            }

            var self      = this,
                invoiceId = selected[0].id;

            return new Promise(function (resolve) {

                new QUIConfirm({
                    icon       : 'fa fa-ban',
                    texticon   : 'fa fa-ban',
                    title      : QUILocale.get(lg, 'dialog.invoice.reversal.title', {
                        invoiceId: invoiceId
                    }),
                    text       : QUILocale.get(lg, 'dialog.invoice.reversal.text', {
                        invoiceId: invoiceId
                    }),
                    information: QUILocale.get(lg, 'dialog.invoice.reversal.information', {
                        invoiceId: invoiceId
                    }),
                    autoclose  : false,
                    ok_button  : {
                        text     : QUILocale.get(lg, 'dialog.invoice.reversal.submit'),
                        textimage: 'fa fa-ban'
                    },
                    maxHeight  : 500,
                    maxWidth   : 750,
                    events     : {
                        onOpen  : function (Win) {
                            var Container = Win.getContent().getElement('.textbody');

                            // #locale
                            var Label = new Element('label', {
                                html  : '<span>Stornierungsgrund</span>', // #locale
                                styles: {
                                    display   : 'block',
                                    fontWeight: 'bold',
                                    marginTop : 20,
                                    width     : 'calc(100% - 100px)'
                                }
                            }).inject(Container);

                            var Reason = new Element('textarea', {
                                name       : 'reason',
                                autofocus  : true,
                                placeholder: 'Bitte geben Sie einen Stornierungsgrund ein.', // #locale
                                styles     : {
                                    height   : 160,
                                    marginTop: 10,
                                    width    : '100%'
                                }
                            }).inject(Label);

                            Reason.focus();
                        },
                        onSubmit: function (Win) {
                            Win.Loader.show();

                            Invoices.reversalInvoice(
                                invoiceId,
                                Win.getContent().getElement('[name="reason"]').value
                            ).then(function () {
                                return self.refresh();
                            }).then(function () {
                                Win.close();
                            }).catch(function (Error) {
                                Win.Loader.hide();

                                QUI.getMessageHandler().then(function (MH) {
                                    MH.addError(Error.getMessage());
                                });
                            });
                        },

                        onCancel: resolve
                    }
                }).open();

            });
        },

        //endregion

        /**
         * Download an invoice
         *
         * @param {Number|String} invoiceId
         */
        downloadPdf: function (invoiceId) {
            return new Promise(function (resolve) {
                var id = 'download-invoice-' + invoiceId;

                new Element('iframe', {
                    src   : URL_OPT_DIR + 'quiqqer/invoice/bin/backend/downloadInvoice.php?' + Object.toQueryString({
                        invoiceId: invoiceId
                    }),
                    id    : id,
                    styles: {
                        position: 'absolute',
                        top     : -200,
                        left    : -200,
                        width   : 50,
                        height  : 50
                    }
                }).inject(document.body);

                (function () {
                    // document.getElements('#' + id).destroy();
                    resolve();
                }).delay(1000, this);
            });
        },

        /**
         * Open an invoice panel
         *
         * @param {Number} invoiceId - ID of the invoice
         * @return {Promise}
         */
        openInvoice: function (invoiceId) {
            return new Promise(function (resolve) {
                require([
                    'package/quiqqer/invoice/bin/backend/controls/panels/Invoice',
                    'utils/Panels'
                ], function (InvoicePanel, PanelUtils) {
                    var Panel = new InvoicePanel({
                        invoiceId: invoiceId
                    });

                    PanelUtils.openPanelInTasks(Panel);
                    resolve(Panel);
                });
            });
        },

        /**
         * Add a payment to an invoice
         * @param {String|Number} hash
         * @param {String|Number} amount
         * @param {String} paymentMethod
         * @param {String|Number} date
         */
        addPayment: function (hash, amount, paymentMethod, date) {
            var self = this;

            this.Loader.show();

            return Invoices.addPaymentToInvoice(
                hash,
                amount,
                paymentMethod,
                date
            ).then(function () {
                return self.refresh();
            }).then(function () {
                self.Loader.hide();
            }).catch(function (err) {
                console.error(err);
            });
        },

        /**
         * Opens a temporary invoice
         *
         * @param {String|Number} invoiceId
         * @return {Promise}
         */
        openTemporaryInvoice: function (invoiceId) {
            return new Promise(function (resolve) {
                require([
                    'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice',
                    'utils/Panels'
                ], function (TemporaryInvoice, PanelUtils) {
                    var Panel = new TemporaryInvoice({
                        invoiceId: invoiceId,
                        '#id'    : invoiceId
                    });

                    PanelUtils.openPanelInTasks(Panel);
                    resolve();
                });
            });
        },

        /**
         * Toggle the total display
         */
        toggleTotal: function () {
            if (parseInt(this.$Total.getStyle('opacity')) === 1) {
                this.hideTotal();
                return;
            }

            this.showTotal();
        },

        /**
         * Show the total display
         */
        showTotal: function () {
            this.getButtons('total').setActive();
            this.getContent().setStyle('overflow', 'hidden');

            return new Promise(function (resolve) {
                this.$Total.setStyles({
                    display: 'inline-block',
                    opacity: 0
                });

                this.$Grid.setHeight(this.getContent().getSize().y - 130);

                moofx(this.$Total).animate({
                    bottom : 1,
                    opacity: 1
                }, {
                    duration: 200,
                    callback: resolve
                });
            }.bind(this));
        },

        /**
         * Hide the total display
         */
        hideTotal: function () {
            var self = this;

            this.getButtons('total').setNormal();

            return new Promise(function (resolve) {
                self.$Grid.setHeight(self.getContent().getSize().y - 20);

                moofx(self.$Total).animate({
                    bottom : -20,
                    opacity: 0
                }, {
                    duration: 200,
                    callback: function () {
                        self.$Total.setStyles({
                            display: 'none'
                        });

                        resolve();
                    }
                });
            });
        }
    });
});
