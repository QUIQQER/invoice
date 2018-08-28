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
            maxHeight: 600,
            maxWidth : 400,
            invoiceId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onOpen: this.$onOpen
            });

            this.setAttributes({
                icon : 'fa fa-money',
                title: QUILocale.get(lg, 'quiqqer.invoice.refund.window.title', {
                    invoiceId: this.getAttribute('invoiceId')
                })
            });
        },

        /**
         * event: on open
         */
        $onOpen: function () {
            var self = this;

            this.Loader.show();
            this.getContent().set('html', '');

            new Refund({
                invoiceId: this.getAttribute('invoiceId'),
                events   : {
                    onLoad: function () {
                        self.Loader.hide();
                    }
                }
            }).inject(this.getContent());
        }
    });
});