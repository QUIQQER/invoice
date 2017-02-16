/**
 * @module package/quiqqer/invoice/bin/backend/classes/Invoices
 */
define('package/quiqqer/invoice/bin/backend/classes/Invoices', [
    'qui/QUI',
    'qui/classes/DOM',
    'Ajax'
], function (QUI, QUIDOM, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIDOM,
        Type: 'package/quiqqer/invoice/bin/backend/classes/Invoices',

        initialize: function (options) {
            this.parent(options);
        },

        /**
         * Return the data from an invoice
         *
         * @param {Number|String} invoiceId
         * @returns {Promise}
         */
        get: function (invoiceId) {
            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_get', resolve, {
                    'package': 'quiqqer/invoice',
                    id: invoiceId
                });
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
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_list', resolve, {
                    'package': 'quiqqer/invoice',
                    params: JSON.encode(params)
                });
            });
        },

        /**
         * Return invoices for a grid
         *
         * @param {Object} params - Grid params
         * @returns {Promise}
         */
        getTemporaryInvoicesList: function (params) {
            return new Promise(function (resolve) {
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_temporary_list', resolve, {
                    'package': 'quiqqer/invoice',
                    params: JSON.encode(params)
                });
            });
        },

        /**
         * Create a new temporary invoice
         *
         * @returns {Promise}
         */
        createInvoice: function () {
            return new Promise(function (resolve) {
                QUIAjax.post('package_quiqqer_invoice_ajax_invoices_create', resolve, {
                    'package': 'quiqqer/invoice'
                });
            });
        }
    });
});
