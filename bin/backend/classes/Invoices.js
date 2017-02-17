/**
 * @module package/quiqqer/invoice/bin/backend/classes/Invoices
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
 *
 * @event onCreateInvoice [self, invoiceId]
 * @event onDeleteInvoice [self, invoiceId]
 * @event onCopyInvoice [self, invoiceId, newId]
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
         * Return the data from a temporary invoice
         *
         * @param {Number|String} invoiceId
         * @returns {Promise}
         */
        getTemporaryInvoice: function (invoiceId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_temporary_get', resolve, {
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
            var self = this;
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_invoices_create', function (newId) {
                    self.fireEvent('copyInvoice', [self, newId]);
                    resolve(newId);
                }, {
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
            var self = this;
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_invoices_temporary_delete', function () {
                    self.fireEvent('deleteInvoice', [self, invoiceId]);
                    resolve();
                }, {
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
        copyTemporaryInvoice: function (invoiceId) {
            var self = this;
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_invoices_temporary_copy', function (newId) {
                    self.fireEvent('copyInvoice', [self, invoiceId, newId]);
                    resolve(newId);
                }, {
                    'package': 'quiqqer/invoice',
                    invoiceId: invoiceId,
                    onError: reject,
                    showError: false
                });
            });
        }
    });
});
