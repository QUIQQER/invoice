/**
 * @modue package/quiqqer/invoice/bin/backend/controls/panels/Journal.Payments
 *
 * @event onLoad [self]
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/Journal.Payments', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'controls/grid/Grid',
    'package/quiqqer/invoice/bin/Invoices',
    'Locale',
    'Ajax'

], function (QUI, QUIControl, QUIConfirm, Grid, Invoices, QUILocale, QUIAjax) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Type   : 'package/quiqqer/invoice/bin/backend/controls/panels/Journal.Payments',
        Extends: QUIControl,

        Binds: [
            '$onInject',
            'openAddPaymentDialog'
        ],

        options: {
            invoiceId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.$Grid = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * Resize the control
         */
        resize: function () {
            this.parent();

            if (!this.$Elm) {
                return;
            }

            this.$Grid.setHeight(this.$Elm.getSize().y);
        },

        /**
         * Refresh the data and the display
         */
        refresh: function () {
            var self = this;

            Invoices.get(this.getAttribute('invoiceId')).then(function (result) {
                var payments = [];

                try {
                    payments = JSON.decode(result.paid_data);
                } catch (e) {
                }

                if (!payments) {
                    payments = [];
                }

                var AddButton = self.$Grid.getButtons().filter(function (Button) {
                    return Button.getAttribute('name') === 'add';
                })[0];

                if (result.paid_status !== 1) {
                    AddButton.enable();
                } else {
                    AddButton.disable();
                }

                return payments;
            }).then(function (payments) {
                return new Promise(function (resolve) {
                    QUIAjax.get('package_quiqqer_invoice_ajax_invoices_payments_format', function (data) {
                        self.$Grid.setData({
                            data: data
                        });

                        self.fireEvent('load', [self]);
                        resolve();
                    }, {
                        'package': 'quiqqer/invoice',
                        payments : JSON.encode(payments)
                    });
                });
            });
        },

        /**
         * Creates the DomNode Element
         *
         * @return {Element}
         */
        create: function () {
            this.$Elm = this.parent();

            this.$Elm.setStyles({
                height: '100%'
            });

            var Container = new Element('div', {
                styles: {
                    height: '100%'
                }
            }).inject(this.$Elm);

            this.$Grid = new Grid(Container, {
                buttons    : [{
                    name    : 'add',
                    text    : QUILocale.get(lg, 'journal.btn.paymentBook'),
                    disabled: true,
                    events  : {
                        onClick: this.openAddPaymentDialog
                    }
                }],
                columnModel: [{
                    header   : QUILocale.get(lg, 'journal.payments.date'),
                    dataIndex: 'date',
                    dataType : 'date',
                    width    : 100
                }, {
                    header   : QUILocale.get(lg, 'journal.payments.amount'),
                    dataIndex: 'amount',
                    dataType : 'number',
                    className: 'journal-grid-amount',
                    width    : 200
                }, {
                    header   : QUILocale.get(lg, 'journal.payments.paymentMethod'),
                    dataIndex: 'payment',
                    dataType : 'string',
                    width    : 200
                }]
            });

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            this.resize();
            this.refresh();
        },

        /**
         * Opens the add payment dialog
         */
        openAddPaymentDialog: function () {
            var self = this;

            var Button = this.$Grid.getButtons().filter(function (Button) {
                return Button.getAttribute('name') === 'add';
            })[0];

            Button.setAttribute('textimage', 'fa fa-spinner fa-spin');

            require([
                'package/quiqqer/invoice/bin/backend/controls/panels/payments/AddPaymentWindow'
            ], function (AddPaymentWindow) {
                new AddPaymentWindow({
                    invoiceId: self.getAttribute('invoiceId'),
                    events   : {
                        onSubmit: function (Win, data) {
                            Invoices.addPaymentToInvoice(
                                self.getAttribute('invoiceId'),
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
        }
    });
});