/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/Journal
 * @author www.pcsg.de (Henning Leutz)
 *
 * List all posted invoices
 */
define(
    'package/quiqqer/invoice/bin/backend/controls/panels/Journal',
    [

        'qui/QUI',
        'qui/controls/desktop/Panel',
        'qui/controls/buttons/Button',
        'qui/controls/buttons/Separator',
        'qui/controls/buttons/Select',
        'qui/controls/contextmenu/Item',
        'controls/grid/Grid',
        'package/quiqqer/invoice/bin/Invoices',
        'package/quiqqer/invoice/bin/backend/utils/Dialogs',
        'package/quiqqer/erp/bin/backend/controls/elements/TimeFilter',
        'Locale',
        'Ajax',
        'Mustache',

        'text!package/quiqqer/invoice/bin/backend/controls/panels/Journal.Total.html',

        'css!package/quiqqer/invoice/bin/backend/controls/panels/Journal.css',
        'css!package/quiqqer/erp/bin/backend/payment-status.css'

    ],
    function(
        QUI,
        QUIPanel,
        QUIButton,
        QUISeparator,
        QUISelect,
        QUIContextMenuItem,
        Grid,
        Invoices,
        Dialogs,
        TimeFilter,
        QUILocale,
        QUIAjax,
        Mustache,
        templateTotal
    ) {
        'use strict';

        const lg = 'quiqqer/invoice';

        return new Class({

            Extends: QUIPanel,
            Type: 'package/quiqqer/invoice/bin/backend/controls/panels/Journal',

            Binds: [
                'refresh',
                'toggleTotal',
                'showTotal',
                'closeTotal',
                '$onCreate',
                '$onDestroy',
                '$onShow',
                '$onResize',
                '$onInject',
                '$onInvoicesChange',
                '$refreshButtonStatus',
                '$onClickCreateInvoice',
                '$onPDFExportButtonClick',
                '$onAddPaymentButtonClick',
                '$onClickCopyInvoice',
                '$onClickInvoiceDetails',
                '$onClickOpenInvoice',
                '$onClickCreateCredit',
                '$onClickReversal',
                '$onSearchKeyUp',
                'linkTransaction'
            ],

            initialize: function(options) {
                this.setAttributes({
                    icon: 'fa fa-money',
                    title: QUILocale.get(lg, 'erp.panel.invoice.text')
                });

                this.parent(options);

                this.$Grid = null;
                this.$Status = null;
                this.$TimeFilter = null;
                this.$Total = null;
                this.$totalsOpen = false;
                this.$Search = null;
                this.$Currency = null;

                this.$currentSearch = '';
                this.$searchDelay = null;
                this.$periodFilter = null;
                this.$loaded = false;

                this.addEvents({
                    onCreate: this.$onCreate,
                    onResize: this.$onResize,
                    onInject: this.$onInject,
                    onDelete: this.$onDestroy,
                    onShow: this.$onShow
                });

                Invoices.addEvents({
                    onPostInvoice: this.$onInvoicesChange
                });
            },

            /**
             * Refresh the grid
             */
            refresh: function() {
                this.Loader.show();

                if (!this.$Grid) {
                    return;
                }

                if (this.$loaded) {
                    this.$periodFilter = this.$TimeFilter.getValue();
                }

                let status = '',
                    from = '',
                    to = '';

                this.$currentSearch = this.$Search.value;

                if (this.$currentSearch !== '') {
                    this.disableFilter();
                } else {
                    this.enableFilter();

                    status = this.$Status.getValue();
                    from = this.$TimeFilter.getValue().from;
                    to = this.$TimeFilter.getValue().to;
                }

                this.$Grid.setAttribute('exportName', this.$TimeFilter.$Select.$placeholderText);

                Invoices.search({
                    perPage: this.$Grid.options.perPage,
                    page: this.$Grid.options.page,
                    sortBy: this.$Grid.options.sortBy,
                    sortOn: this.$Grid.options.sortOn
                }, {
                    from: from,
                    to: to,
                    paid_status: [status],
                    search: this.$currentSearch,
                    currency: this.$Currency.getAttribute('value')
                }).then(function(result) {
                    let gridData = result.grid;

                    gridData.data = gridData.data.map(function(entry) {
                        const Icon = new Element('span');

                        switch (parseInt(entry.type)) {
                            // gutschrift
                            case 3:
                                Icon.addClass('fa fa-clipboard');
                                Icon.set('title', QUILocale.get(lg, 'invoice.type.creditNote'));
                                break;

                            // storno
                            case 4:
                                Icon.addClass('fa fa-ban');
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
                                    'class': 'fa fa-check'
                                });
                                break;

                            default:
                                entry.display_isbrutto = new Element('span', {
                                    'class': 'fa fa-minus'
                                });
                                break;
                        }

                        entry.display_type = Icon;
                        entry.opener = '&nbsp;';

                        // overdue, ignore storno
                        if ('overdue' in entry && entry.overdue && parseInt(entry.type) !== 5) {
                            entry.className = 'journal-grid-overdue';
                        }

                        return entry;
                    });


                    this.$Grid.setData(gridData);
                    this.$refreshButtonStatus();

                    this.$Total.set('html', Mustache.render(templateTotal, result.total));

                    this.Loader.hide();
                }.bind(this)).catch(function(err) {
                    console.error(err);
                    this.Loader.hide();
                }.bind(this));
            },

            /**
             * refresh the button status
             * disabled or enabled
             */
            $refreshButtonStatus: function() {
                if (!this.$Grid) {
                    return;
                }

                const selected = this.$Grid.getSelectedData(), buttons = this.$Grid.getButtons();

                const Actions = buttons.filter(function(Button) {
                    return Button.getAttribute('name') === 'actions';
                })[0];

                const Payment = Actions.getChildren().filter(function(Button) {
                    return Button.getAttribute('name') === 'addPayment';
                })[0];

                const CreditNote = Actions.getChildren().filter(function(Button) {
                    return Button.getAttribute('name') === 'createCreditNote';
                })[0];

                const PDF = this.$Grid.getButton('printPdf');
                const Create = this.$Grid.getButton('create');

                if (selected.length === 1 && parseInt(selected[0].type) === 3) {
                    CreditNote.disable(); // a credit note can't create a credit note
                } else {
                    CreditNote.enable();
                }

                if (selected.length) {
                    if (selected[0].paid_status === 1 || selected[0].paid_status === 5) {
                        Payment.disable();
                    } else {
                        Payment.enable();
                    }

                    PDF.enable();
                    Actions.enable();
                    return;
                }

                Actions.disable();
                PDF.disable();
            },

            /**
             * event : on create
             */
            $onCreate: function() {
                // Buttons
                this.addButton({
                    name: 'total',
                    text: QUILocale.get(lg, 'journal.btn.total'),
                    textimage: 'fa fa-calculator',
                    events: {
                        onClick: this.toggleTotal
                    }
                });

                // currency
                this.$Currency = new QUIButton({
                    name: 'currency',
                    disabled: true,
                    showIcons: false,
                    events: {
                        onChange: function(Menu, Item) {
                            self.$Currency.setAttribute('value', Item.getAttribute('value'));
                            self.$Currency.setAttribute('text', Item.getAttribute('value'));
                            self.refresh();
                        }
                    }
                });

                this.addButton(this.$Currency);


                this.$Status = new QUISelect({
                    showIcons: false,
                    styles: {
                        'float': 'right'
                    },
                    events: {
                        onChange: this.refresh
                    }
                });

                this.$Status.appendChild(QUILocale.get(lg, 'journal.paidstatus.all'), '');
                this.$Status.appendChild(QUILocale.get(lg, 'journal.paidstatus.open'), Invoices.PAYMENT_STATUS_OPEN);
                this.$Status.appendChild(QUILocale.get(lg, 'journal.paidstatus.paid'), Invoices.PAYMENT_STATUS_PAID);
                this.$Status.appendChild(QUILocale.get(lg, 'journal.paidstatus.partial'), Invoices.PAYMENT_STATUS_PART);
                this.$Status.appendChild(
                    QUILocale.get(lg, 'journal.paidstatus.canceled'),
                    Invoices.PAYMENT_STATUS_CANCELED
                );
                this.$Status.appendChild(QUILocale.get(lg, 'journal.paidstatus.debit'), Invoices.PAYMENT_STATUS_DEBIT);

                this.addButton(this.$Status);

                const self = this,
                    Separator = new QUISeparator();

                this.addButton(Separator);

                Separator.getElm().setStyles({
                    'float': 'right'
                });

                this.$TimeFilter = new TimeFilter({
                    name: 'timeFilter',
                    styles: {
                        'float': 'right'
                    },
                    events: {
                        onChange: this.refresh,
                        onPeriodSelectClose: function(Filter) {
                            self.$periodFilter = Filter.getValue();
                        },
                        onPeriodSelectOpen: function(Filter) {
                            if (self.$periodFilter) {
                                Filter.setAttribute('from', self.$periodFilter.from);
                                Filter.setAttribute('to', self.$periodFilter.to);
                            }
                        }
                    }
                });

                this.addButton(this.$TimeFilter);


                this.addButton({
                    type: 'separator',
                    styles: {
                        'float': 'right'
                    }
                });

                this.addButton({
                    name: 'search',
                    icon: 'fa fa-search',
                    styles: {
                        'float': 'right'
                    },
                    events: {
                        onClick: this.refresh
                    }
                });

                this.$Search = new Element('input', {
                    type: 'search',
                    name: 'search-value',
                    placeholder: 'Search...',
                    styles: {
                        'float': 'right',
                        margin: '10px 0 0 0',
                        width: 200
                    },
                    events: {
                        keyup: this.$onSearchKeyUp,
                        search: this.$onSearchKeyUp,
                        click: this.$onSearchKeyUp
                    }
                });

                this.addButton(this.$Search);


                // Grid
                this.getContent().setStyles({
                    padding: 10,
                    position: 'relative'
                });

                const Container = new Element('div').inject(this.getContent());

                const Actions = new QUIButton({
                    name: 'actions',
                    text: QUILocale.get(lg, 'journal.btn.actions'),
                    menuCorner: 'topRight',
                    position: 'right'
                });

                Actions.appendChild({
                    name: 'addPayment',
                    text: QUILocale.get(lg, 'journal.btn.paymentBook'),
                    icon: 'fa fa-money',
                    events: {
                        onClick: this.$onAddPaymentButtonClick
                    }
                });

                Actions.appendChild({
                    name: 'cancel',
                    text: QUILocale.get(lg, 'journal.btn.cancelInvoice'),
                    icon: 'fa fa-times-circle-o',
                    events: {
                        onClick: this.$onClickReversal
                    }
                });

                Actions.appendChild({
                    name: 'copy',
                    text: QUILocale.get(lg, 'journal.btn.copyInvoice'),
                    icon: 'fa fa-copy',
                    events: {
                        onClick: this.$onClickCopyInvoice
                    }
                });

                Actions.appendChild({
                    name: 'createCreditNote',
                    text: QUILocale.get(lg, 'journal.btn.createCreditNote'),
                    icon: 'fa fa-clipboard',
                    events: {
                        onClick: this.$onClickCreateCredit
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


                this.$Grid = new Grid(Container, {
                    storageKey: 'quiqqer-invoice-grid-journal',
                    titleSort: true,
                    pagination: true,
                    serverSort: true,
                    accordion: true,
                    autoSectionToggle: false,
                    openAccordionOnClick: false,
                    toggleiconTitle: '',
                    accordionLiveRenderer: this.$onClickInvoiceDetails,
                    exportData: true,
                    exportTypes: {
                        csv: true,
                        json: true,
                        xls: true
                    },
                    buttons: [
                        {
                            name: 'create',
                            text: QUILocale.get(lg, 'journal.btn.create'),
                            textimage: 'fa fa-plus',
                            events: {
                                onClick: this.$onClickCreateInvoice
                            }
                        },
                        {
                            name: 'printPdf',
                            text: QUILocale.get(lg, 'journal.btn.pdf'),
                            textimage: 'fa fa-print',
                            disabled: true,
                            position: 'right',
                            events: {
                                onClick: this.$onPDFExportButtonClick
                            }
                        },
                        Actions
                    ],
                    columnModel: [
                        {
                            header: '&nbsp;',
                            dataIndex: 'opener',
                            dataType: 'int',
                            width: 30,
                            showNotInExport: true,
                            export: false
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.type'),
                            dataIndex: 'display_type',
                            dataType: 'node',
                            width: 30,
                            showNotInExport: true,
                            export: false
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.invoiceNo'),
                            dataIndex: 'id_with_prefix',
                            dataType: 'integer',
                            width: 100
                        },
                        {
                            header: QUILocale.get('quiqqer/system', 'name'),
                            dataIndex: 'customer_name',
                            dataType: 'string',
                            width: 200,
                            className: 'clickable',
                            sortable: false
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.customerNo'),
                            dataIndex: 'customer_id_display',
                            dataType: 'string',
                            width: 90,
                            className: 'clickable'
                        }, {
                            dataIndex: 'customer_id',
                            dataType: 'string',
                            hidden: true
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.status'),
                            dataIndex: 'paid_status_display',
                            dataType: 'html',
                            width: 120,
                            export: false,
                            className: 'grid-align-center'
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.date'),
                            dataIndex: 'date',
                            dataType: 'date',
                            width: 90
                        },
                        {
                            header: QUILocale.get('quiqqer/core', 'project'),
                            dataIndex: 'project_name',
                            dataType: 'string',
                            width: 160
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.status'),
                            dataIndex: 'paid_status_clean',
                            dataType: 'html',
                            width: 120,
                            hidden: true,
                            export: true
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.sum'),
                            type: 'html',
                            dataIndex: 'display_sum',
                            dataType: 'currency',
                            width: 100,
                            className: 'payment-status-amountCell'
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.netto'),
                            type: 'html',
                            dataIndex: 'display_nettosum',
                            dataType: 'currency',
                            width: 100,
                            className: 'payment-status-amountCell'
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.vat'),
                            type: 'html',
                            dataIndex: 'display_vatsum',
                            dataType: 'currency',
                            width: 100,
                            className: 'payment-status-amountCell',
                            sortable: false
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.paymentMethod'),
                            dataIndex: 'payment_title',
                            dataType: 'string',
                            width: 180
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.timeForPayment'),
                            dataIndex: 'time_for_payment',
                            dataType: 'date',
                            width: 120
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.paymentDate'),
                            dataIndex: 'paid_date',
                            dataType: 'date',
                            width: 120
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.paid'),
                            type: 'html',
                            dataIndex: 'display_paid',
                            dataType: 'currency',
                            width: 100,
                            className: 'payment-status-amountCell',
                            sortable: false
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.open'),
                            type: 'html',
                            dataIndex: 'display_toPay',
                            dataType: 'currency',
                            width: 100,
                            className: 'payment-status-amountCell',
                            sortable: false
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.brutto'),
                            dataIndex: 'display_isbrutto',
                            dataType: 'node',
                            width: 50,
                            sortable: false
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.taxId'),
                            dataIndex: 'taxId',
                            dataType: 'string',
                            width: 105
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.orderNo'),
                            dataIndex: 'order_id',
                            dataType: 'integer',
                            width: 80
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.orderDate'),
                            dataIndex: 'order_date',
                            dataType: 'date',
                            width: 140
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.dunning'),
                            dataIndex: 'dunning_level_display',
                            dataType: 'string',
                            width: 80
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.processing'),
                            dataIndex: 'processing_status_display',
                            dataType: 'html',
                            width: 150
                        },
                        {
                            header: QUILocale.get('quiqqer/system', 'c_date'),
                            dataIndex: 'c_date',
                            dataType: 'date',
                            width: 140
                        },
                        {
                            header: QUILocale.get('quiqqer/system', 'c_user'),
                            dataIndex: 'c_username',
                            dataType: 'string',
                            width: 130,
                            sortable: false
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.hash'),
                            dataIndex: 'hash',
                            dataType: 'string',
                            width: 280,
                            className: 'monospace'
                        },
                        {
                            header: QUILocale.get(lg, 'journal.grid.globalProcessId'),
                            dataIndex: 'globalProcessId',
                            dataType: 'string',
                            width: 280,
                            className: 'monospace clickable'
                        },
                        {
                            dataIndex: 'payment_method',
                            dataType: 'string',
                            hidden: true
                        },
                        {
                            dataIndex: 'type',
                            dataType: 'number',
                            hidden: true
                        }, {
                            dataIndex: 'id',
                            dataType: 'integer',
                            hidden: true
                        }
                    ]
                });

                this.$Grid.addEvents({
                    onRefresh: this.refresh,
                    onClick: this.$refreshButtonStatus,
                    onDblClick: this.$onClickOpenInvoice
                });


                this.$Total = new Element('div', {
                    'class': 'journal-total'
                }).inject(this.getContent());
            },

            /**
             * event : on resize
             */
            $onResize: function() {
                if (!this.$Grid) {
                    return;
                }

                const Body = this.getContent();

                if (!Body) {
                    return;
                }

                let size = Body.getSize(),
                    yOffset = 20;

                if (this.$totalsOpen) {
                    yOffset = 130;
                }

                this.$Grid.setHeight(size.y - yOffset);
                this.$Grid.setWidth(size.x - 20);
            },

            /**
             * event: on inject
             */
            $onInject: function() {
                const self = this,
                    value = this.$Status.getValue();

                if (value === '' || !value) {
                    this.$Status.setValue('');
                }

                this.$loaded = true;

                QUIAjax.get([
                    'package_quiqqer_currency_ajax_getAllowedCurrencies',
                    'package_quiqqer_currency_ajax_getDefault'
                ], function(currencies, currency) {
                    let i, len, entry, text;

                    if (!currencies.length || currencies.length === 1) {
                        self.$Currency.hide();
                        return;
                    }

                    for (i = 0, len = currencies.length; i < len; i++) {
                        entry = currencies[i];

                        text = entry.code + ' ' + entry.sign;
                        text = text.trim();

                        self.$Currency.appendChild(new QUIContextMenuItem({
                            name: entry.code,
                            value: entry.code,
                            text: text
                        }));
                    }

                    self.$Currency.enable();
                    self.$Currency.setAttribute('value', currency.code);
                    self.$Currency.setAttribute('text', currency.code);
                }, {
                    'package': 'quiqqer/currency'
                });

                this.$Currency.getContextMenu(function(ContextMenu) {
                    ContextMenu.setAttribute('showIcons', false);
                });
            },

            /**
             * event: on panel destroy
             */
            $onDestroy: function() {
                Invoices.removeEvents({
                    onPostInvoice: this.$onInvoicesChange
                });
            },

            /**
             * event: on show
             */
            $onShow: function() {
                if (this.$Search.value !== '') {
                    return;
                }

                this.refresh();
            },

            /**
             * event: invoices changed something
             */
            $onInvoicesChange: function() {
                this.refresh();
            },

            //region Buttons events

            $onClickCreateInvoice: function() {
                return Invoices.createInvoice().then(function(invoiceId) {
                    require([
                        'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice',
                        'utils/Panels'
                    ], function(TemporaryInvoice, PanelUtils) {
                        const Panel = new TemporaryInvoice({
                            invoiceId: invoiceId,
                            '#id': invoiceId
                        });

                        PanelUtils.openPanelInTasks(Panel);
                    });
                }.bind(this)).catch(function(Exception) {
                    QUI.getMessageHandler().then(function(MH) {
                        if (typeof Exception.getMessage !== 'undefined') {
                            MH.addError(Exception.getMessage());
                            return;
                        }

                        console.error(Exception);
                    });
                });
            },

            /**
             * event : on PDF Export button click
             */
            $onPDFExportButtonClick: function(Button) {
                const selectedData = this.$Grid.getSelectedData();

                if (!selectedData.length) {
                    return;
                }

                Button.setAttribute('textimage', 'fa fa-spinner fa-spin');

                let Entry = selectedData[0], entityType;

                switch (parseInt(Entry.type)) {
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
                    require(['package/quiqqer/invoice/bin/backend/utils/Dialogs'], function(Dialogs) {
                        Dialogs.openPrintDialog(Entry.hash, entityType).catch((err) => {
                            QUI.getMessageHandler().then((MH) => {
                                MH.addError(err.getMessage());
                            });
                        });
                        Button.setAttribute('textimage', 'fa fa-print');
                        resolve();
                    });
                });
            },

            /**
             * event : on payment add button click
             */
            $onAddPaymentButtonClick: function(Button) {
                const self = this, selectedData = this.$Grid.getSelectedData();

                if (!selectedData.length) {
                    return;
                }

                Button.setAttribute('textimage', 'fa fa-spinner fa-spin');

                const hash = selectedData[0].hash;

                require([
                    'package/quiqqer/payment-transactions/bin/backend/controls/IncomingPayments/AddPaymentWindow'
                ], function(AddPaymentWindow) {
                    new AddPaymentWindow({
                        entityId: hash,
                        entityType: 'Invoice',
                        events: {
                            onSubmit: function(Win, data) {
                                self.addPayment(hash, data.amount, data.payment_method, data.date).then(function() {
                                    Button.setAttribute('textimage', 'fa fa-money');
                                    self.refresh();
                                });
                            },

                            onSubmitExisting: (txId, Win) => {
                                self.linkTransaction(hash, txId).then(function() {
                                    Button.setAttribute('textimage', 'fa fa-money');
                                    self.refresh();
                                });
                            },

                            onClose: function() {
                                Button.setAttribute('textimage', 'fa fa-money');
                            }
                        }
                    }).open();
                });
            },

            /**
             * Copy the temporary invoice and opens the invoice
             *
             * @return {Promise}
             */
            $onClickCopyInvoice: function() {
                const selected = this.$Grid.getSelectedData();

                if (!selected.length) {
                    return Promise.resolve(false);
                }

                const hash = selected[0].hash;

                return new Promise(function(resolve) {
                    require([
                        'package/quiqqer/erp/bin/backend/controls/dialogs/CopyErpEntityDialog'
                    ], (CopyErpEntityDialog) => {
                        new CopyErpEntityDialog({
                            hash: hash,
                            entityPlugin: 'quiqqer/invoice',
                            events: {
                                onSuccess: resolve
                            }
                        }).open();
                    });
                });
            },

            /**
             * Open the accordion details of the invoice
             *
             * @param {Object} data
             */
            $onClickInvoiceDetails: function(data) {
                const row = data.row, ParentNode = data.parent;

                ParentNode.setStyle('padding', 10);
                ParentNode.set('html', '<div class="fa fa-spinner fa-spin"></div>');

                Invoices.getArticleHtml(this.$Grid.getDataByRow(row).hash).then(function(result) {
                    ParentNode.set('html', '');

                    new Element('div', {
                        'class': 'invoices-invoice-details',
                        html: result
                    }).inject(ParentNode);
                });
            },

            /**
             * event: on click open invoice
             *
             * @param {object} data - cell data
             * @return {Promise}
             */
            $onClickOpenInvoice: function(data) {
                if (typeof data !== 'undefined' && typeof data.cell !== 'undefined' &&
                    (data.cell.get('data-index') === 'customer_id' || data.cell.get('data-index') === 'customer_name')) {

                    const self = this, Cell = data.cell, position = Cell.getPosition(),
                        rowData = this.$Grid.getDataByRow(data.row);

                    return new Promise(function(resolve) {
                        require([
                            'qui/controls/contextmenu/Menu',
                            'qui/controls/contextmenu/Item'
                        ], function(QUIMenu, QUIMenuItem) {
                            const Menu = new QUIMenu({
                                events: {
                                    onBlur: function() {
                                        Menu.hide();
                                        Menu.destroy();
                                    }
                                }
                            });

                            Menu.appendChild(new QUIMenuItem({
                                icon: rowData.display_type.className,
                                text: QUILocale.get(lg, 'journal.contextMenu.open.invoice'),
                                events: {
                                    onClick: function() {
                                        self.openInvoice(rowData.hash);
                                    }
                                }
                            }));

                            Menu.appendChild(new QUIMenuItem({
                                icon: 'fa fa-user-o',
                                text: QUILocale.get(lg, 'journal.contextMenu.open.user'),
                                events: {
                                    onClick: function() {
                                        require(
                                            ['package/quiqqer/customer/bin/backend/Handler'],
                                            function(CustomerHandler) {
                                                CustomerHandler.openCustomer(rowData.customer_id);
                                            }
                                        );
                                    }
                                }
                            }));

                            Menu.inject(document.body);
                            Menu.setPosition(position.x, position.y + 30);
                            Menu.setTitle(rowData.id);
                            Menu.show();
                            Menu.focus();

                            resolve();
                        });
                    });
                }

                if (typeof data !== 'undefined' && data.cell.get('data-index') === 'globalProcessId') {
                    const rowData = this.$Grid.getDataByRow(data.row);

                    if (rowData.globalProcessId && rowData.globalProcessId !== '') {
                        require([
                            'package/quiqqer/erp/bin/backend/controls/process/ProcessWindow'
                        ], function(ProcessWindow) {
                            new ProcessWindow({
                                globalProcessId: rowData.globalProcessId
                            }).open();
                        });

                        return Promise.resolve();
                    }
                }

                const selected = this.$Grid.getSelectedData();

                if (!selected.length) {
                    return Promise.resolve();
                }

                return this.openInvoice(selected[0].hash);
            },

            /**
             * Opens the create credit dialog
             *
             * @return {Promise}
             */
            $onClickCreateCredit: function() {
                const selected = this.$Grid.getSelectedData();

                if (!selected.length) {
                    return Promise.resolve();
                }

                const self = this, invoiceId = selected[0].hash;

                return new Promise(function(resolve) {
                    require([
                        'package/quiqqer/invoice/bin/backend/utils/Dialogs'
                    ], function(Dialogs) {
                        Dialogs.openCreateCreditNoteDialog(invoiceId).then(function(newId) {
                            if (!newId) {
                                return;
                            }

                            return self.openTemporaryInvoice(newId);
                        }).then(resolve);
                    });
                });
            },

            /**
             * Opens the reversal dialog
             * - Invoice cancel
             * - Storno
             *
             * @return {Promise}
             */
            $onClickReversal: function() {
                const selected = this.$Grid.getSelectedData();

                if (!selected.length) {
                    return Promise.resolve();
                }

                const self = this, invoiceId = selected[0].hash;

                return new Promise(function(resolve) {
                    require([
                        'package/quiqqer/invoice/bin/backend/utils/Dialogs'
                    ], function(Dialogs) {
                        Dialogs.openReversalDialog(invoiceId).then(function(result) {
                            Dialogs.openPrintDialog(result.reversalHash, 'Canceled');
                            return self.refresh();
                        }).then(resolve).catch(function(Exception) {
                            QUI.getMessageHandler().then(function(MH) {
                                console.error(Exception);

                                if (typeof Exception.getMessage !== 'undefined') {
                                    MH.addError(Exception.getMessage());
                                    return;
                                }

                                MH.addError(Exception);
                            });

                            resolve();
                        });
                    });
                });
            },

            //endregion

            /**
             * Download an invoice
             *
             * @param {Number|String} invoiceId
             */
            downloadPdf: function(invoiceId) {
                return new Promise(function(resolve) {
                    const id = 'download-invoice-' + invoiceId;

                    new Element('iframe', {
                        src: URL_OPT_DIR + 'quiqqer/invoice/bin/backend/downloadInvoice.php?' + Object.toQueryString({
                            invoiceId: invoiceId
                        }),
                        id: id,
                        styles: {
                            position: 'absolute',
                            top: -200,
                            left: -200,
                            width: 50,
                            height: 50
                        }
                    }).inject(document.body);

                    (function() {
                        // document.getElements('#' + id).destroy();
                        resolve();
                    }).delay(1000, this);
                });
            },

            /**
             * Open an invoice panel
             *
             * @param {Number|String} hash - ID or hash of the invoice
             * @return {Promise}
             */
            openInvoice: function(hash) {
                return new Promise(function(resolve) {
                    require([
                        'package/quiqqer/invoice/bin/backend/controls/panels/Invoice',
                        'utils/Panels'
                    ], function(InvoicePanel, PanelUtils) {
                        const Panel = new InvoicePanel({
                            hash: hash,
                            '#id': hash
                        });

                        PanelUtils.openPanelInTasks(Panel);
                        resolve(Panel);
                    });
                });
            },

            /**
             * Add a payment to an invoice
             *
             * @param {String|Number} hash
             * @param {String|Number} amount
             * @param {String} paymentMethod
             * @param {String|Number} date
             *
             * @return {Promise}
             */
            addPayment: function(hash, amount, paymentMethod, date) {
                const self = this;

                this.Loader.show();

                return Invoices.addPaymentToInvoice(hash, amount, paymentMethod, date).then(function() {
                    return self.refresh();
                }).then(function() {
                    self.Loader.hide();
                }).catch(function(err) {
                    console.error(err);
                });
            },

            /**
             * Link an existing transaction to an invoice.
             *
             * @param {String} invoiceHash
             * @param {String} txId
             *
             * @return {Promise<void>}
             */
            linkTransaction: function(invoiceHash, txId) {
                this.Loader.show();

                return Invoices.linkTransaction(invoiceHash, txId).then(() => {
                    return this.refresh();
                }).then(() => {
                    this.Loader.hide();
                }).catch(function(err) {
                    console.error(err);
                });
            },

            /**
             * Opens a temporary invoice
             *
             * @param {String|Number} invoiceId
             * @return {Promise}
             */
            openTemporaryInvoice: function(invoiceId) {
                return new Promise(function(resolve) {
                    require([
                        'package/quiqqer/invoice/bin/backend/utils/Panels'
                    ], function(PanelUtils) {
                        PanelUtils.openTemporaryInvoice(invoiceId).then(resolve);
                    });
                });
            },

            /**
             * Toggle the total display
             *
             * @return {Promise}
             */
            toggleTotal: function() {
                if (parseInt(this.$Total.getStyle('opacity')) === 1) {
                    return this.hideTotal();
                }

                return this.showTotal();
            },

            /**
             * Show the total display
             *
             * @return {Promise}
             */
            showTotal: function() {
                this.getButtons('total').setActive();
                this.getContent().setStyle('overflow', 'hidden');

                this.$totalsOpen = true;

                return new Promise(function(resolve) {
                    this.$Total.setStyles({
                        display: 'inline-block',
                        opacity: 0
                    });

                    this.$Grid.setHeight(this.getContent().getSize().y - 130);

                    moofx(this.$Total).animate({
                        bottom: 1,
                        opacity: 1
                    }, {
                        duration: 200,
                        callback: resolve
                    });
                }.bind(this));
            },

            /**
             * Hide the total display
             *
             * @return {Promise}
             */
            hideTotal: function() {
                const self = this;

                this.$totalsOpen = false;

                this.getButtons('total').setNormal();

                return new Promise(function(resolve) {
                    self.$Grid.setHeight(self.getContent().getSize().y - 20);

                    moofx(self.$Total).animate({
                        bottom: -20,
                        opacity: 0
                    }, {
                        duration: 200,
                        callback: function() {
                            self.$Total.setStyles({
                                display: 'none'
                            });

                            resolve();
                        }
                    });
                });
            },

            /**
             * Disable the filter
             */
            disableFilter: function() {
                this.$TimeFilter.disable();
                this.$Status.disable();
            },

            /**
             * Enable the filter
             */
            enableFilter: function() {
                this.$TimeFilter.enable();
                this.$Status.enable();
            },

            /**
             * key up event at the search input
             *
             * @param {DOMEvent} event
             */
            $onSearchKeyUp: function(event) {
                if (event.key === 'up' || event.key === 'down' || event.key === 'left' || event.key === 'right') {
                    return;
                }

                if (this.$searchDelay) {
                    clearTimeout(this.$searchDelay);
                }

                if (event.type === 'click') {
                    // workaround, cancel needs time to clear
                    (function() {
                        if (this.$currentSearch !== this.$Search.value) {
                            this.$searchDelay = (function() {
                                this.refresh();
                            }).delay(250, this);
                        }
                    }).delay(100, this);
                }

                if (this.$currentSearch === this.$Search.value) {
                    return;
                }

                if (event.key === 'enter') {
                    this.$searchDelay = (function() {
                        this.refresh();
                    }).delay(250, this);
                }
            }
        });
    }
);
