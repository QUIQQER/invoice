/**
 *
 */
define('package/quiqqer/bill/bin/backend/classes/Invoices', [
    'qui/QUI',
    'qui/classes/QDOM',
    'Ajax'
], function (QUI, QDOM, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QDOM,
        Type: '',

        initialize: function (options) {
            this.parent(options);
        },

        /**
         * Return the data from a bill
         *
         * @param {Number} billId
         * @returns {Promise}
         */
        get: function (billId) {
            return new Promise(function (resolve) {

            });
        },

        /**
         * Return invoices for a grid
         *
         * @param {Object} params - Grid params
         * @returns {Promise}
         */
        getList: function (params) {
            return new Promise(function (resolve) {
                QUIAjax.get('');
                resolve();
            });
        }
    });
});
