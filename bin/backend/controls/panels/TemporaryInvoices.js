/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoices
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
        Type: 'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoices',

        Binds: [
            'refresh',
            '$onCreate',
            '$onResize',
            '$onInject',
            '$onDestroy',
            '$clickCreateInvoice',
            '$clickDeleteInvoice',
            '$clickCopyInvoice',
            '$onInvoicesChange'
        ],

        initialize: function (options) {
            this.setAttributes({
                icon: 'fa fa-money',
                title: QUILocale.get(lg, 'erp.panel.temporary.invoice.text')
            });

            this.parent(options);

            this.$Grid = null;

            this.addEvents({
                onCreate: this.$onCreate,
                onResize: this.$onResize,
                onInject: this.$onInject,
                onDestroy: this.$onDestroy
            });

            Invoices.addEvents({
                onDeleteInvoice: this.$onInvoicesChange,
                onCreateInvoice: this.$onInvoicesChange,
                onCopyInvoice: this.$onInvoicesChange
            });
        },

        /**
         * Refresh the grid
         */
        refresh: function () {
            this.Loader.show();

            return Invoices.getTemporaryInvoicesList().then(function (result) {
                this.$Grid.setData(result);

                var Copy = this.$Grid.getButtons().filter(function (Btn) {
                    return Btn.getAttribute('name') == 'copy';
                })[0];

                var Delete = this.$Grid.getButtons().filter(function (Btn) {
                    return Btn.getAttribute('name') == 'delete';
                })[0];

                var PDF = this.$Grid.getButtons().filter(function (Btn) {
                    return Btn.getAttribute('name') == 'pdf';
                })[0];

                Copy.disable();
                Delete.disable();
                PDF.disable();

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
                        invoiceId: invoiceId
                    });

                    PanelUtils.openPanelInTasks(Panel);
                    resolve();
                });
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
            this.addButton({
                text: 'Summe anzeigen',
                textimage: 'fa fa-calculator'
            });

            this.getContent().setStyles({
                padding: 10
            });

            // Grid
            var Container = new Element('div').inject(
                this.getContent()
            );

            this.$Grid = new Grid(Container, {
                pagination: true,
                buttons: [{
                    name: 'create',
                    text: QUILocale.get(lg, 'temporary.btn.createInvoice'),
                    textimage: 'fa fa-plus',
                    events: {
                        onClick: function (Btn) {
                            Btn.setAttribute('textimage', 'fa fa-spinner fa-spin');

                            self.$clickCreateInvoice().then(function () {
                                Btn.setAttribute('textimage', 'fa fa-plus');
                            });
                        }
                    }
                }, {
                    name: 'copy',
                    disabled: true,
                    text: QUILocale.get(lg, 'journal.btn.copyInvoice'),
                    textimage: 'fa fa-copy',
                    events: {
                        onClick: this.$clickCopyInvoice
                    }
                }, {
                    name: 'delete',
                    disabled: true,
                    text: QUILocale.get(lg, 'temporary.btn.deleteInvoice'),
                    textimage: 'fa fa-trash',
                    events: {
                        onClick: this.$clickDeleteInvoice
                    }
                }, {
                    type: 'seperator'
                }, {
                    name: 'pdf',
                    disabled: true,
                    text: QUILocale.get(lg, 'journal.btn.pdf'),
                    textimage: 'fa fa-file-pdf-o',
                    events: {
                        onClick: function () {
                        }
                    }
                }],
                columnModel: [{
                    header: QUILocale.get(lg, 'journal.grid.invoiceNo'),
                    dataIndex: 'id',
                    dataType: 'integer',
                    width: 100
                }, {
                    header: QUILocale.get(lg, 'journal.grid.orderNo'),
                    dataIndex: 'order_id',
                    dataType: 'integer',
                    width: 80
                }, {
                    header: QUILocale.get(lg, 'journal.grid.customerNo'),
                    dataIndex: 'customer_id',
                    dataType: 'integer',
                    width: 100
                }, {
                    header: QUILocale.get('quiqqer/system', 'name'),
                    dataIndex: 'customer_name',
                    dataType: 'string',
                    width: 130
                }, {
                    header: QUILocale.get('quiqqer/system', 'date'),
                    dataIndex: 'date',
                    dataType: 'date',
                    width: 150
                }, {
                    header: QUILocale.get('quiqqer/system', 'username'),
                    dataIndex: 'c_user',
                    dataType: 'integer',
                    width: 130
                }, {
                    header: QUILocale.get(lg, 'journal.grid.status'),
                    dataIndex: 'paidstatus',
                    dataType: 'string',
                    width: 120
                }, {
                    header: QUILocale.get(lg, 'journal.grid.netto'),
                    dataIndex: 'display_nettosum',
                    dataType: 'currency',
                    width: 80
                }, {
                    header: QUILocale.get(lg, 'journal.grid.vat'),
                    dataIndex: 'display_vatsum',
                    dataType: 'currency',
                    width: 80
                }, {
                    header: QUILocale.get(lg, 'journal.grid.sum'),
                    dataIndex: 'display_sum',
                    dataType: 'currency',
                    width: 80
                }, {
                    header: QUILocale.get(lg, 'journal.grid.paymentMethod'),
                    dataIndex: 'payment_method',
                    dataType: 'string',
                    width: 120
                }, {
                    header: QUILocale.get(lg, 'journal.grid.paymentTerm'),
                    dataIndex: 'payment_time',
                    dataType: 'string',
                    width: 120
                }, {
                    header: QUILocale.get(lg, 'journal.grid.paymentDate'),
                    dataIndex: 'paid_date',
                    dataType: 'date',
                    width: 120
                }, {
                    header: QUILocale.get(lg, 'journal.grid.paid'),
                    dataIndex: 'display_paid',
                    dataType: 'currency',
                    width: 80
                }, {
                    header: QUILocale.get(lg, 'journal.grid.open'),
                    dataIndex: 'display_missing',
                    dataType: 'currency',
                    width: 80
                }, {
                    header: QUILocale.get(lg, 'journal.grid.brutto'),
                    dataIndex: 'isbrutto',
                    dataType: 'integer',
                    width: 50
                }, {
                    header: QUILocale.get(lg, 'taxid'),
                    dataIndex: 'taxid',
                    dataType: 'string',
                    width: 120
                }, {
                    header: QUILocale.get(lg, 'journal.grid.orderDate'),
                    dataIndex: 'order_id',
                    dataType: 'date',
                    width: 130
                    // }, {
                    //     header: QUILocale.get(lg, 'journal.grid.dunning'),
                    //     dataIndex: 'dunning_level',
                    //     dataType: 'integer',
                    //     width: 80
                }, {
                    header: QUILocale.get(lg, 'journal.grid.processing'),
                    dataIndex: 'processing_status',
                    dataType: 'string',
                    width: 150
                }, {
                    header: QUILocale.get(lg, 'journal.grid.comments'),
                    dataIndex: 'comments',
                    dataType: 'string',
                    width: 100
                }, {
                    header: QUILocale.get(lg, 'journal.grid.paymentData'),
                    dataIndex: 'payment_data',
                    dataType: 'string',
                    width: 100
                }, {
                    header: QUILocale.get(lg, 'journal.grid.hash'),
                    dataIndex: 'hash',
                    dataType: 'string',
                    width: 200
                }]
            });

            this.$Grid.addEvents({
                onRefresh: this.refresh,

                onClick: function () {
                    var Copy = self.$Grid.getButtons().filter(function (Btn) {
                        return Btn.getAttribute('name') == 'copy';
                    })[0];

                    var Delete = self.$Grid.getButtons().filter(function (Btn) {
                        return Btn.getAttribute('name') == 'delete';
                    })[0];

                    var PDF = self.$Grid.getButtons().filter(function (Btn) {
                        return Btn.getAttribute('name') == 'pdf';
                    })[0];

                    Copy.enable();
                    Delete.enable();
                    PDF.enable();
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
                onDeleteInvoice: this.$onInvoicesChange,
                onCreateInvoice: this.$onInvoicesChange,
                onCopyInvoice: this.$onInvoicesChange
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
            }.bind(this)).then(function () {
                return this.refresh();
            }.bind(this));
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
                title: QUILocale.get(lg, 'dialog.ti.delete.title'),
                text: QUILocale.get(lg, 'dialog.ti.delete.text'),
                information: QUILocale.get(lg, 'dialog.ti.delete.information', {
                    id: selected[0].id
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
            var self = this,
                selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            new QUIConfirm({
                title: QUILocale.get(lg, 'dialog.ti.copy.title'),
                text: QUILocale.get(lg, 'dialog.ti.copy.text'),
                information: QUILocale.get(lg, 'dialog.ti.copy.information', {
                    id: selected[0].id
                }),
                icon: 'fa fa-copy',
                texticon: 'fa fa-copy',
                maxHeight: 400,
                maxWidth: 600,
                autoclose: false,
                ok_button: {
                    text: QUILocale.get('quiqqer/system', 'copy'),
                    textimage: 'fa fa-copy'
                },
                events: {
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
         * event: invoices changed something
         * create, delete, save, copy
         */
        $onInvoicesChange: function () {
            this.refresh();
        }
    });
});
