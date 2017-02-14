define('', [
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