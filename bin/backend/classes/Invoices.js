/**
 * @module package/quiqqer/invoice/bin/backend/classes/Invoices
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
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
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_get', resolve, {
                    'package': 'quiqqer/invoice',
                    invoiceId: invoiceId,
                    onError: reject,
                    showError: false
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
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_list', resolve, {
                    'package': 'quiqqer/invoice',
                    params: JSON.encode(params),
                    onError: reject,
                    showError: false
                });
            });
        },

        /**
         * Return invoices for a grid
         *
         * @param {Object} [params] - Grid params
         * @returns {Promise}
         */
        getTemporaryInvoicesList: function (params) {
            params = params || {};

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_temporary_list', resolve, {
                    'package': 'quiqqer/invoice',
                    params: JSON.encode(params),
                    onError: reject,
                    showError: false
                });
            });
        },

        /**
         * Create a new temporary invoice
         *
         * @returns {Promise}
         */
        createInvoice: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_invoices_create', resolve, {
                    'package': 'quiqqer/invoice',
                    onError: reject,
                    showError: false
                });
            });
        },

        /**
         * Delete a temporary invoice
         *
         * @param {String} invoiceId
         * @returns {Promise}
         */
        deleteInvoice: function (invoiceId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_invoices_temporary_delete', resolve, {
                    'package': 'quiqqer/invoice',
                    invoiceId: invoiceId,
                    onError: reject,
                    showError: false
                });
            });
        },

        /**
         * Copy a temporary invoice
         *
         * @param {String} invoiceId
         * @returns {Promise}
         */
        copyInvoice: function (invoiceId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_invoices_temporary_copy', resolve, {
                    'package': 'quiqqer/invoice',
                    invoiceId: invoiceId,
                    onError: reject,
                    showError: false
                });
            });
        }
    });
});
