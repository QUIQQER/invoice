/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/refund/Window
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/refund/Window', [

    'qui/QUI',
    'qui/controls/windows/Confirm',
    'Locale',
    'package/quiqqer/invoice/bin/backend/controls/panels/refund/Refund'

], function (QUI, QUIConfirm, QUILocale, Refund) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIConfirm,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/panels/refund/Window',

        Binds: [
            '$onOpen'
        ],

        options: {
            maxHeight : 800,
            maxWidth  : 600,
            invoiceId : false,
            autoRefund: true
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onOpen: this.$onOpen
            });

            this.setAttributes({
                icon : 'fa fa-money',
                title: ''
            });

            this.$Refund = null;
        },

        /**
         * event: on open
         */
        $onOpen: function () {
            var self = this;

            this.Loader.show();
            this.getContent().set('html', '');

            this.$Refund = new Refund({
                invoiceId : this.getAttribute('invoiceId'),
                autoRefund: this.getAttribute('autoRefund'),
                events    : {
                    onLoad: function () {
                        self.setAttribute('title', QUILocale.get(lg, 'quiqqer.invoice.refund.window.title', {
                            invoiceId: self.$Refund.getValues().invoiceId
                        }));

                        self.refresh();
                        self.Loader.hide();
                    },

                    onOpenTransactionList: function () {
                        self.getButton('submit').disable();
                    },

                    onOpenRefund: function () {
                        self.getButton('submit').enable();
                    }
                }
            }).inject(this.getContent());
        },

        /**
         *
         * @return {*|Array|{txid: *, invoiceId: *, refund: *, message: *}}
         */
        getValues: function () {
            return this.$Refund.getValues();
        },

        /**
         * Submit the window
         */
        submit: function () {
            var self = this;

            this.Loader.show();

            this.$Refund.submit().then(function () {
                self.fireEvent('submit', [self]);
                self.Loader.show();
                self.close();
            }).catch(function () {
                self.Loader.hide();
            });
        }
    });
});
