/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/payments/AddPayment
 *
 * @event onLoad [self]
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/payments/AddPayment', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/utils/Form',
    'Mustache',
    'package/quiqqer/payments/bin/backend/Payments',
    'package/quiqqer/invoice/bin/Invoices',

    'text!package/quiqqer/invoice/bin/backend/controls/panels/payments/AddPayment.html'

], function (QUI, QUIControl, QUIFormUtils, Mustache, Payments, Invoices, template) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/panels/payments/AddPayment',

        Binds: [
            '$onInject'
        ],

        options: {
            invoiceId     : false,
            payment_method: '957669f3146ceebe4267bf15ee3b9dc6' // cash
        },

        initialize: function (options) {
            this.parent(options);

            this.$Form = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * Create the DomNode Element
         *
         * @return {Element|null}
         */
        create: function () {
            this.$Elm = new Element('div', {
                html: Mustache.render(template)
            });

            this.$Form = this.$Elm.getElement('form');

            this.$Form.addEvent('submit', function (event) {
                event.stop();

                this.fireEvent('submit');
            }.bind(this));

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            Promise.all([
                Payments.getPayments(),
                Invoices.get(this.getAttribute('invoiceId'))
            ]).then(function (result) {
                var payments = result[0],
                    invoice  = result[1];

                var Payments  = this.getElm().getElement('[name="payment_method"]');
                var DateInput = this.getElm().getElement('[name="date"]');
                var Amount    = this.getElm().getElement('[name="amount"]');

                new Element('option', {
                    html : '',
                    value: ''
                }).inject(Payments);

                Amount.value = invoice.toPay;

                for (var payment in payments) {
                    if (!payments.hasOwnProperty(payment)) {
                        continue;
                    }

                    new Element('option', {
                        html : payments[payment].title,
                        value: payment
                    }).inject(Payments);
                }

                Payments.value        = this.getAttribute('payment_method');
                DateInput.valueAsDate = new Date();

                this.fireEvent('load', [this]);
            }.bind(this));
        },

        /**
         * Return the form data
         *
         * @return {Object}
         */
        getValue: function () {
            return QUIFormUtils.getFormData(this.$Form);
        },

        /**
         * Focus the amount field
         */
        focus: function () {
            this.getElm().getElement('[name="amount"]').focus();
        }
    });
});