/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoices
 *
 * Zeigt alle Rechnungsentwürfe an
 *
 * @require qui/QUI
 * @require qui/controls/desktop/Panel
 * @require qui/controls/windows/Confirm
 * @require controls/grid/Grid
 * @require package/quiqqer/invoice/bin/Invoices
 * @require Locale
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoices', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/windows/Confirm',
    'controls/grid/Grid',
    'package/quiqqer/invoice/bin/Invoices',
    'Locale'

], function (QUI, QUIPanel, QUIConfirm, Grid, Invoices, QUILocale) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIPanel,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoices',

        Binds: [
            'refresh',
            '$onCreate',
            '$onResize',
            '$onInject',
            '$onDestroy',
            '$clickPostInvoice',
            '$clickCreateInvoice',
            '$clickDeleteInvoice',
            '$clickCopyInvoice',
            '$clickPDF',
            '$onInvoicesChange'
        ],

        initialize: function (options) {
            this.setAttributes({
                icon : 'fa fa-money',
                title: QUILocale.get(lg, 'erp.panel.temporary.invoice.title')
            });

            this.parent(options);

            this.$Grid = null;

            this.addEvents({
                onCreate : this.$onCreate,
                onResize : this.$onResize,
                onInject : this.$onInject,
                onDestroy: this.$onDestroy
            });

            Invoices.addEvents({
                onDeleteInvoice : this.$onInvoicesChange,
                onSaveInvoice   : this.$onInvoicesChange,
                onCreateInvoice : this.$onInvoicesChange,
                onCopyInvoice   : this.$onInvoicesChange,
                onPostInvoice   : this.$onInvoicesChange,
                createCreditNote: this.$onInvoicesChange
            });
        },

        /**
         * Refresh the grid
         */
        refresh: function () {
            this.Loader.show();

            return Invoices.getTemporaryInvoicesList().then(function (result) {
                result.data = result.data.map(function (entry) {
                    var Icon = new Element('span');

                    switch (parseInt(entry.type)) {
                        // gutschrift
                        case 3:
                            Icon.addClass('fa fa-clipboard');
                            break;

                        // storno
                        case 4:
                            Icon.addClass('fa fa-ban');
                            break;

                        default:
                            Icon.addClass('fa fa-file-text-o');
                    }

                    entry.display_type = Icon;
                    entry.opener       = '&nbsp;';

                    return entry;
                });

                this.$Grid.setData(result);

                var Copy = this.$Grid.getButtons().filter(function (Btn) {
                    return Btn.getAttribute('name') === 'copy';
                })[0];

                var Delete = this.$Grid.getButtons().filter(function (Btn) {
                    return Btn.getAttribute('name') === 'delete';
                })[0];

                var PDF = this.$Grid.getButtons().filter(function (Btn) {
                    return Btn.getAttribute('name') === 'pdf';
                })[0];

                var Post = this.$Grid.getButtons().filter(function (Btn) {
                    return Btn.getAttribute('name') === 'post';
                })[0];

                Copy.disable();
                Delete.disable();
                PDF.disable();
                Post.disable();

                this.Loader.hide();
            }.bind(this));
        },

        /**
         * Opens a TemporaryInvoice Panel
         *
         * @param {String} invoiceId
         * @return {Promise}
         */
        openInvoice: function (invoiceId) {
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
                    resolve(Panel);
                });
            });
        },

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
         * Event Handling
         */

        /**
         * event : on panel create
         */
        $onCreate: function () {
            var self = this;

            // Buttons

            // Grid
            this.getContent().setStyles({
                padding: 10
            });

            var Container = new Element('div').inject(
                this.getContent()
            );

            this.$Grid = new Grid(Container, {
                pagination : true,
                buttons    : [{
                    name     : 'create',
                    text     : QUILocale.get(lg, 'temporary.btn.createInvoice'),
                    textimage: 'fa fa-plus',
                    events   : {
                        onClick: function (Btn) {
                            Btn.setAttribute('textimage', 'fa fa-spinner fa-spin');

                            self.$clickCreateInvoice(Btn).then(function () {
                                Btn.setAttribute('textimage', 'fa fa-plus');
                            });
                        }
                    }
                }, {
                    type: 'separator'
                }, {
                    name     : 'post',
                    disabled : true,
                    text     : QUILocale.get(lg, 'journal.btn.post'),
                    textimage: 'fa fa-check',
                    events   : {
                        onClick: this.$clickPostInvoice
                    }
                }, {
                    name     : 'copy',
                    disabled : true,
                    text     : QUILocale.get(lg, 'journal.btn.copyInvoice'),
                    textimage: 'fa fa-copy',
                    events   : {
                        onClick: this.$clickCopyInvoice
                    }
                }, {
                    name     : 'delete',
                    disabled : true,
                    text     : QUILocale.get(lg, 'temporary.btn.deleteInvoice'),
                    textimage: 'fa fa-trash',
                    events   : {
                        onClick: this.$clickDeleteInvoice
                    }
                }, {
                    type: 'separator'
                }, {
                    name     : 'pdf',
                    disabled : true,
                    text     : QUILocale.get(lg, 'journal.btn.pdf'),
                    textimage: 'fa fa-file-pdf-o',
                    events   : {
                        onClick: this.$clickPDF
                    }
                }],
                columnModel: [{
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
                    width    : 100
                }, {
                    header   : QUILocale.get('quiqqer/system', 'name'),
                    dataIndex: 'customer_name',
                    dataType : 'string',
                    width    : 130
                }, {
                    header   : QUILocale.get('quiqqer/system', 'date'),
                    dataIndex: 'date',
                    dataType : 'date',
                    width    : 150
                }, {
                    header   : QUILocale.get('quiqqer/system', 'c_user'),
                    dataIndex: 'c_user',
                    dataType : 'integer',
                    width    : 100
                }, {
                    header   : QUILocale.get('quiqqer/system', 'c_user'),
                    dataIndex: 'c_username',
                    dataType : 'integer',
                    width    : 130
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.status'),
                    dataIndex: 'paidstatus',
                    dataType : 'string',
                    width    : 120
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.netto'),
                    dataIndex: 'display_nettosum',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.vat'),
                    dataIndex: 'display_vatsum',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.sum'),
                    dataIndex: 'display_sum',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.paymentMethod'),
                    dataIndex: 'payment_title',
                    dataType : 'string',
                    width    : 120
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.timeForPayment'),
                    dataIndex: 'time_for_payment',
                    dataType : 'string',
                    width    : 120
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.paymentDate'),
                    dataIndex: 'paid_date',
                    dataType : 'date',
                    width    : 120
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.paid'),
                    dataIndex: 'display_paid',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.open'),
                    dataIndex: 'display_missing',
                    dataType : 'string',
                    width    : 100,
                    className: 'payment-status-amountCell'
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.brutto'),
                    dataIndex: 'isbrutto',
                    dataType : 'integer',
                    width    : 50
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.taxId'),
                    dataIndex: 'taxId',
                    dataType : 'string',
                    width    : 120
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.orderDate'),
                    dataIndex: 'order_id',
                    dataType : 'date',
                    width    : 130
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.processing'),
                    dataIndex: 'processing_status',
                    dataType : 'string',
                    width    : 150
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.comments'),
                    dataIndex: 'comments',
                    dataType : 'string',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.paymentData'),
                    dataIndex: 'payment_data',
                    dataType : 'string',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'journal.grid.hash'),
                    dataIndex: 'hash',
                    dataType : 'string',
                    width    : 200
                }]
            });

            this.$Grid.addEvents({
                onRefresh: this.refresh,

                onClick: function () {
                    var Copy = self.$Grid.getButtons().filter(function (Btn) {
                        return Btn.getAttribute('name') === 'copy';
                    })[0];

                    var Delete = self.$Grid.getButtons().filter(function (Btn) {
                        return Btn.getAttribute('name') === 'delete';
                    })[0];

                    var PDF = self.$Grid.getButtons().filter(function (Btn) {
                        return Btn.getAttribute('name') === 'pdf';
                    })[0];

                    var Post = self.$Grid.getButtons().filter(function (Btn) {
                        return Btn.getAttribute('name') === 'post';
                    })[0];

                    Copy.enable();
                    Delete.enable();
                    PDF.enable();
                    Post.enable();
                },

                onDblClick: function () {
                    self.openInvoice(
                        self.$Grid.getSelectedData()[0].id
                    );
                }
            });
        },

        /**
         * event : on panel resize
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
         * event: on panel inject
         */
        $onInject: function () {
            this.refresh();
        },

        /**
         * event: on panel destroy
         */
        $onDestroy: function () {
            Invoices.removeEvents({
                onDeleteInvoice : this.$onInvoicesChange,
                onCreateInvoice : this.$onInvoicesChange,
                onSaveInvoice   : this.$onInvoicesChange,
                onCopyInvoice   : this.$onInvoicesChange,
                onPostInvoice   : this.$onInvoicesChange,
                createCreditNote: this.$onInvoicesChange
            });
        },

        /**
         * Creates a new invoice
         *
         * @return {Promise}
         */
        $clickCreateInvoice: function () {
            return Invoices.createInvoice().then(function (invoiceId) {
                return this.openInvoice(invoiceId);
            }.bind(this));
        },

        /**
         * Post the selected invoice
         *
         * @param Button
         */
        $clickPostInvoice: function (Button) {
            if (typeOf(Button) !== 'qui/controls/buttons/Button') {
                return;
            }

            var selected = this.$Grid.getSelectedData(),
                oldImage = Button.getAttribute('textimage');

            if (!selected.length) {
                return;
            }

            selected = selected[0];

            Button.setAttribute('textimage', 'fa fa-spinner fa-spin');

            return Invoices.getMissingAttributes(selected.id).then(function (missing) {
                if (!Object.getLength(missing)) {
                    return Invoices.postInvoice(selected.id);
                }

                return QUI.getMessageHandler().then(function (MH) {
                    for (var i in missing) {
                        if (missing.hasOwnProperty(i)) {
                            MH.addError(missing[i]);
                        }
                    }
                });
            }).then(function () {
                Button.setAttribute('textimage', oldImage);
            });
        },

        /**
         * opens the delete dialog
         */
        $clickDeleteInvoice: function () {
            var selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            new QUIConfirm({
                title      : QUILocale.get(lg, 'dialog.ti.delete.title'),
                text       : QUILocale.get(lg, 'dialog.ti.delete.text'),
                information: QUILocale.get(lg, 'dialog.ti.delete.information', {
                    id: selected[0].id
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

                        Invoices.deleteInvoice(selected[0].id).then(function () {
                            Win.close();
                        }).then(function () {
                            Win.Loader.show();
                        });
                    }
                }
            }).open();
        },

        /**
         * Copy the temporary invoice and opens the invoice
         */
        $clickCopyInvoice: function () {
            var self     = this,
                selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            new QUIConfirm({
                title      : QUILocale.get(lg, 'dialog.ti.copy.title'),
                text       : QUILocale.get(lg, 'dialog.ti.copy.text'),
                information: QUILocale.get(lg, 'dialog.ti.copy.information', {
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

                        Invoices.copyTemporaryInvoice(selected[0].id).then(function (newId) {
                            Win.close();
                            return self.openInvoice(newId);
                        }).then(function () {
                            Win.Loader.show();
                        });
                    }
                }
            }).open();
        },

        /**
         * Export PDF of a temporary invoice
         *
         * @param Button
         */
        $clickPDF: function (Button) {
            var selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            selected = selected[0];
            Button.setAttribute('textimage', 'fa fa-spinner fa-spin');

            this.downloadPdf(selected.id).then(function () {
                Button.setAttribute('textimage', 'fa fa-file-pdf-o');
            });
        },

        /**
         * event: invoices changed something
         * create, delete, save, copy
         */
        $onInvoicesChange: function () {
            this.refresh();
        }
    });
});
