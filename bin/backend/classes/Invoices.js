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
        Type   : 'package/quiqqer/invoice/bin/backend/classes/Invoices',

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
                    onError  : reject,
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
                    params   : JSON.encode(params),
                    onError  : reject,
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
                    onError  : reject,
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
                    params   : JSON.encode(params),
                    onError  : reject,
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
                    onError  : reject,
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
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Delete a temporary invoice
         *
         * @param {String} invoiceId
         * @param {Object} data
         * @returns {Promise}
         */
        saveInvoice: function (invoiceId, data) {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_invoices_temporary_save', function () {
                    self.fireEvent('saveInvoice', [self, invoiceId, data]);
                    resolve();
                }, {
                    'package': 'quiqqer/invoice',
                    invoiceId: invoiceId,
                    data     : JSON.encode(data),
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Post a invoice
         *
         * @param {String} invoiceId
         * @return {Promise}
         */
        postInvoice: function (invoiceId) {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_invoices_temporary_post', function (id) {
                    self.fireEvent('postInvoice', [self, invoiceId, id]);
                    resolve(id);
                }, {
                    'package': 'quiqqer/invoice',
                    invoiceId: invoiceId,
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Copy a invoice
         *
         * @param {String} invoiceId
         * @return {Promise}
         */
        copyInvoice: function (invoiceId) {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_invoices_copy', function (id) {
                    self.fireEvent('copyInvoice', [self, invoiceId, id]);
                    resolve(id);
                }, {
                    'package': 'quiqqer/invoice',
                    invoiceId: invoiceId,
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Add a payment to an invoice
         *
         * @param {Number|String} invoiceId
         * @param {Number} amount
         * @param {String} paymentMethod
         * @param {Number|String} [date]
         */
        addPaymentToInvoice: function (invoiceId, amount, paymentMethod, date) {
            var self = this;

            date = date || false;

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_invoices_addPayment', function (id) {
                    self.fireEvent('addPaymentToInvoice', [self, invoiceId, id]);
                    resolve(id);
                }, {
                    'package'    : 'quiqqer/invoice',
                    invoiceId    : invoiceId,
                    amount       : amount,
                    paymentMethod: paymentMethod,
                    date         : date,
                    onError      : reject,
                    showError    : false
                });
            });
        },

        /**
         * Return the invoice html
         *
         * @param {String} invoiceId
         * @param {Object} data
         * @returns {Promise}
         */
        getInvoiceHtml: function (invoiceId, data) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_invoices_temporary_html', resolve, {
                    'package': 'quiqqer/invoice',
                    invoiceId: invoiceId,
                    data     : JSON.encode(data),
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Return the invoice html
         *
         * @param {String} invoiceId
         * @param {Object} data
         * @returns {Promise}
         */
        getInvoicePreviewHtml: function (invoiceId, data) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_invoices_temporary_previewhtml', resolve, {
                    'package': 'quiqqer/invoice',
                    invoiceId: invoiceId,
                    data     : JSON.encode(data),
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Return the missing attributes of an invoice
         *
         * @param invoiceId
         * @return {Promise}
         */
        getMissingAttributes: function (invoiceId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_invoices_temporary_missing', resolve, {
                    'package': 'quiqqer/invoice',
                    invoiceId: invoiceId,
                    onError  : reject,
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
                    onError  : reject,
                    showError: false
                });
            });
        }
    });
});
