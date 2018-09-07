/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/refund/Window
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/refund/Refund', [

    'qui/QUI',
    'qui/controls/Control',
    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/invoice/bin/backend/controls/panels/refund/Refund.html',
    'text!package/quiqqer/invoice/bin/backend/controls/panels/refund/Refund.List.html',

    'css!package/quiqqer/invoice/bin/backend/controls/panels/refund/Refund.css'

], function (QUI, QUIControl, QUILocale, QUIAjax, Mustache,
             templateRefund, templateRefundList) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/panels/refund/Refund',

        Binds: [
            '$onInject'
        ],

        options: {
            invoiceId : false,
            autoRefund: true
        },

        initialize: function (options) {
            this.parent(options);

            this.$transactions = [];
            this.$txId         = [];

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * Create the DOMNode Element
         *
         * @return {Element}
         */
        create: function () {
            this.$Elm = this.parent();

            this.$Elm.addClass('quiqqer-invoice-backend-refund');
            this.$Elm.set('html', '');

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self = this;

            QUIAjax.get([
                'package_quiqqer_invoice_ajax_invoices_getTransactions',
                'package_quiqqer_invoice_ajax_invoices_get'
            ], function (transactions, invoice) {
                self.setAttribute('invoiceId', invoice.id_prefix + invoice.id);

                if (!transactions.length) {
                    self.$Elm.set(
                        'html',
                        QUILocale.get(lg, 'quiqqer.invoice.refund.not.available.no.transactions')
                    );

                    self.fireEvent('load', [self]);

                    return;
                }

                self.$transactions = transactions;

                for (var i = 0, len = self.$transactions.length; i < len; i++) {
                    self.$transactions[i].currency = JSON.decode(self.$transactions[i].currency);
                    self.$transactions[i].data     = JSON.decode(self.$transactions[i].data);
                }

                if (transactions.length === 1) {
                    self.$txId = transactions[0].txid;
                    self.openRefund().then(function () {
                        self.fireEvent('load', [self]);
                    });
                } else {
                    self.openTransactionList().then(function () {
                        self.fireEvent('load', [self]);
                    });
                }
            }, {
                'package': 'quiqqer/invoice',
                invoiceId: this.getAttribute('invoiceId'),
                onError  : function (err) {
                    console.error(err);
                    self.fireEvent('load', [self]);
                }
            });
        },

        /**
         * Return the current values
         *
         * @return {{txid: Array|*, invoiceId: *, refund: *, message: *}}
         */
        getValues: function () {
            var refund = '', message = '';

            var Refund  = this.getElm().getElement('[name="refund"]'),
                Message = this.getElm().getElement('[name="customer-message"]');

            if (Refund) {
                refund = Refund.value;
            }

            if (Message) {
                message = Message.value;
            }

            return {
                txid     : this.$txId,
                invoiceId: this.getAttribute('invoiceId'),
                refund   : refund,
                message  : message
            };
        },

        /**
         * open transactions list
         */
        openTransactionList: function () {
            var self = this;

            this.getElm().set('html', Mustache.render(templateRefundList, {
                transactions: this.$transactions
            }));

            this.fireEvent('openTransactionList', [this]);

            this.getElm().getElements('.quiqqer-invoice-refund-list-entry').addEvent('click', function (event) {
                var Target = event.target;

                if (!Target.hasClass('quiqqer-invoice-refund-list-entry')) {
                    Target = Target.getParent('.quiqqer-invoice-refund-list-entry');
                }

                self.$txId = Target.getElement('input').get('value');
                self.openRefund();

                event.stop();
            });

            return Promise.resolve();
        },

        /**
         * open the refund template
         */
        openRefund: function () {
            var self  = this,
                Child = this.getElm().getFirst();

            var Transaction = this.$transactions.filter(function (TX) {
                return TX.txid === self.$txId;
            })[0];

            if (!Child) {
                Child = new Element('div');
            }

            this.fireEvent('openRefund', [this]);

            return new Promise(function (resolve) {
                moofx(Child).animate({
                    opacity: 0,
                    left   : -20
                }, {
                    callback: function () {
                        self.getElm().set('html', Mustache.render(templateRefund, {
                            Transaction: Transaction,
                            id         : self.getAttribute('invoiceId'),
                            txId       : Transaction.txid,
                            amount     : Transaction.amount,
                            currency   : Transaction.currency.sign,

                            textData     : QUILocale.get(lg, 'quiqqer.refund.data'),
                            textInvoiceNo: QUILocale.get(lg, 'quiqqer.refund.invoiceNo'),
                            textContact  : QUILocale.get(lg, 'quiqqer.refund.contactData'),
                            textTxId     : QUILocale.get(lg, 'quiqqer.refund.txid'),
                            textPayment  : QUILocale.get(lg, 'quiqqer.refund.original.payment'),
                            textRefund   : QUILocale.get(lg, 'quiqqer.refund.refundAmount'),
                            textMessage  : QUILocale.get(lg, 'quiqqer.refund.message'),
                            information  : QUILocale.get(lg, 'quiqqer.refund.information')
                        }));

                        var Child = self.getElm().getFirst();

                        Child.setStyle('left', -20);

                        moofx(Child).animate({
                            opacity: 1,
                            left   : 0
                        }, {
                            callback: resolve
                        });
                    }
                });
            });
        },

        /**
         * Execute the refund
         *
         * @return {Promise}
         */
        submit: function () {
            var self = this;

            if (this.getAttribute('autoRefund') === false) {
                return Promise.resolve({
                    txid     : self.$txId,
                    invoiceId: self.getAttribute('invoiceId'),
                    refund   : self.getElm().getElement('[name="refund"]').value,
                    message  : self.getElm().getElement('[name="customer-message"]').value
                });
            }

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_invoices_refund', function () {
                    resolve({
                        txid     : self.$txId,
                        invoiceId: self.getAttribute('invoiceId'),
                        refund   : self.getElm().getElement('[name="refund"]').value,
                        message  : self.getElm().getElement('[name="customer-message"]').value
                    });
                }, {
                    'package': 'quiqqer/invoice',
                    txid     : self.$txId,
                    invoiceId: self.getAttribute('invoiceId'),
                    refund   : self.getElm().getElement('[name="refund"]').value,
                    message  : self.getElm().getElement('[name="customer-message"]').value,
                    onError  : reject
                });
            });
        }
    });
});
