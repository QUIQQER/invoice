/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice
 *
 * Edit a Temporary Invoice and created a posted invoice
 *
 * @require qui/QUI
 * @require qui/controls/desktop/Panel
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice', [
    'qui/QUI',
    'qui/controls/desktop/Panel'
], function (QUI, QUIPanel) {
    "use strict";

    return new Class({

        Extends: QUIPanel,
        Type: '',

        options: {
            invoiceId: false
        },

        initialize: function (options) {
            this.setAttributes({
                icon: 'fa fa-money'
            });

            this.parent(options);
        }
    });
});