/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/Journal
 *
 * List all posted invoices
 *
 * @require qui/QUI
 * @require qui/controls/desktop/Panel
 * @require qui/controls/buttons/Select
 * @require controls/grid/Grid
 * @require package/quiqqer/invoice/bin/Invoices
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/Journal', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/buttons/Select',
    'controls/grid/Grid',
    'package/quiqqer/invoice/bin/Invoices',
    'Locale'

], function (QUI, QUIPanel, QUISelect, Grid, Invoices, QUILocale) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIPanel,
        Type: 'package/quiqqer/invoice/bin/backend/controls/panels/Journal',

        Binds: [
            'refresh',
            '$onCreate',
            '$onResize',
            '$onInject'
        ],

        initialize: function (options) {
            this.setAttributes({
                icon: 'fa fa-money',
                title: QUILocale.get(lg, 'erp.panel.invoice.text')
            });

            this.parent(options);

            this.$Grid = null;
            this.$Status = null;

            this.addEvents({
                onCreate: this.$onCreate,
                onResize: this.$onResize,
                onInject: this.$onInject
            });
        },

        /**
         * Refresh the grid
         */
        refresh: function () {
            this.Loader.show();

            Invoices.getList().then(function (result) {
                this.$Grid.setData(result);
                this.Loader.hide();
            }.bind(this));
        },

        /**
         * event : on create
         */
        $onCreate: function () {
            // Buttons
            this.addButton({
                text: 'Summe anzeigen',
                textimage: 'fa fa-calculator'
            });

            this.$Status = new QUISelect({
                showIcons: false,
                styles: {
                    'float': 'right'
                },
                events: {
                    onChange: this.refresh
                }
            });

            this.$Status.appendChild(QUILocale.get(lg, 'journal.paidstatus.all'), 'all');
            this.$Status.appendChild(QUILocale.get(lg, 'journal.paidstatus.open'), 'open');
            this.$Status.appendChild(QUILocale.get(lg, 'journal.paidstatus.paid'), 'paid');
            this.$Status.appendChild(QUILocale.get(lg, 'journal.paidstatus.partial'), 'partial');
            this.$Status.appendChild(QUILocale.get(lg, 'journal.paidstatus.canceled'), 'canceled');
            this.$Status.appendChild(QUILocale.get(lg, 'journal.paidstatus.debit'), 'debit');

            this.addButton(this.$Status);
            //
            // this.addButton({
            //     text: 'Summe anzeigen',
            //     textimage: 'fa fa-calculator'
            // });


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
                    text: QUILocale.get(lg, 'journal.btn.paymentBook'),
                    textimage: 'fa fa-money',
                    events: {
                        onClick: function () {
                        }
                    }
                }, {
                    text: QUILocale.get(lg, 'journal.btn.pdf'),
                    textimage: 'fa fa-file-pdf-o',
                    events: {
                        onClick: function () {
                        }
                    }
                }, {
                    type: 'seperator'
                }, {
                    text: QUILocale.get(lg, 'journal.btn.cancelInvoice'),
                    textimage: 'fa fa-remove',
                    events: {
                        onClick: function () {
                        }
                    }
                }, {
                    text: QUILocale.get(lg, 'journal.btn.copyInvoice'),
                    textimage: 'fa fa-copy',
                    events: {
                        onClick: function () {
                        }
                    }
                }, {
                    type: 'seperator'
                }, {
                    text: QUILocale.get(lg, 'journal.btn.createCredit'),
                    textimage: 'fa fa-clipboard',
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
                    dataIndex: 'orderid',
                    dataType: 'integer',
                    width: 80
                }, {
                    header: QUILocale.get(lg, 'journal.grid.customerNo'),
                    dataIndex: 'uid',
                    dataType: 'integer',
                    width: 100
                }, {
                    header: QUILocale.get('quiqqer/system', 'name'),
                    dataIndex: 'customer',
                    dataType: 'string',
                    width: 130
                }, {
                    header: QUILocale.get('quiqqer/system', 'date'),
                    dataIndex: 'date',
                    dataType: 'date',
                    width: 150
                }, {
                    header: QUILocale.get('quiqqer/system', 'username'),
                    dataIndex: 'username',
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
                    dataIndex: 'payment',
                    dataType: 'string',
                    width: 120
                }, {
                    header: QUILocale.get(lg, 'journal.grid.paymentTerm'),
                    dataIndex: 'payment_time',
                    dataType: 'string',
                    width: 120
                }, {
                    header: QUILocale.get(lg, 'journal.grid.paymentDate'),
                    dataIndex: 'paiddate',
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
                    dataIndex: 'vatid',
                    dataType: 'string',
                    width: 120
                }, {
                    header: QUILocale.get(lg, 'journal.grid.orderDate'),
                    dataIndex: 'orderdate',
                    dataType: 'date',
                    width: 130
                }, {
                    header: QUILocale.get(lg, 'journal.grid.dunning'),
                    dataIndex: 'dunning_level',
                    dataType: 'integer',
                    width: 80
                }, {
                    header: QUILocale.get(lg, 'journal.grid.processing'),
                    dataIndex: 'processing_text',
                    dataType: 'string',
                    width: 150
                }, {
                    header: QUILocale.get(lg, 'journal.grid.comments'),
                    dataIndex: 'comments',
                    dataType: 'string',
                    width: 100
                }, {
                    header: QUILocale.get(lg, 'journal.grid.paymentData'),
                    dataIndex: 'paymentData',
                    dataType: 'string',
                    width: 100
                }, {
                    header: QUILocale.get(lg, 'journal.grid.hash'),
                    dataIndex: 'hash',
                    dataType: 'string',
                    width: 200
                }]
            });
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
                this.$Status.setValue('all');
            }
        }
    });
});