/**
 * @module package/quiqqer/invoice/bin/backend/controls/Invoice
 * @author www.pcsg.de (Henning Leutz)
 *
 * Displays a posted Invoice
 */
define('package/quiqqer/invoice/bin/backend/controls/Invoice', [

    'qui/QUI',
    'qui/controls/Control'

], function (QUI, QUIControl) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/Invoice',

        options: {
            invoiceId: false
        },

        initialize: function (options) {
            this.setAttributes({
                icon: 'fa fa-money'
            });

            this.parent(options);
        },

        /**
         * Create the domnode element
         *
         * @returns {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();


            return this.$Elm;
        }
    });
});