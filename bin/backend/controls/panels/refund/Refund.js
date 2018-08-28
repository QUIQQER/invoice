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
            invoiceId: false
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

            QUIAjax.get('package_quiqqer_invoice_ajax_invoices_getTransactions', function (transactions) {
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
                }

                if (transactions.length === 1) {
                    // self.openRefund();

                    self.openTransactionList();
                } else {
                    self.openTransactionList();
                }

                self.fireEvent('load', [self]);
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
         * open transactions list
         */
        openTransactionList: function () {
            var self = this;

            this.getElm().set('html', Mustache.render(templateRefundList, {
                transactions: this.$transactions
            }));

            this.getElm().getElements('.quiqqer-invoice-refund-list-entry')
                .addEvent('click', function (event) {
                    var Target = event.target;

                    if (!Target.hasClass('quiqqer-invoice-refund-list-entry')) {
                        Target = Target.getParent('.quiqqer-invoice-refund-list-entry');
                    }

                    self.$txId = Target.getElement('input').get('value');
                    self.openRefund();
                });
        },

        /**
         * open the refund template
         */
        openRefund: function () {
            console.log(this.$txId);
        }
    });
});
