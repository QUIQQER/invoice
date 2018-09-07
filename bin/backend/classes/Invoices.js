/**
 * @module package/quiqqer/invoice/bin/backend/classes/Invoices
 * @author www.pcsg.de (Henning Leutz)
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

        PAYMENT_STATUS_OPEN    : 0,
        PAYMENT_STATUS_PAID    : 1,
        PAYMENT_STATUS_PART    : 2,
        PAYMENT_STATUS_ERROR   : 4,
        PAYMENT_STATUS_CANCELED: 5,
        PAYMENT_STATUS_DEBIT   : 11,

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
         * Return the time for payment value
         *
         * @param {Number|String} userId - ID of the user
         * @return {Promise}
         */
        getPaymentTime: function (userId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_temporary_getTimeForPayment', resolve, {
                    'package': 'quiqqer/invoice',
                    uid      : userId,
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Return the article html from an invoice
         *
         * @param {Number|String} invoiceId
         * @returns {Promise}
         */
        getArticleHtml: function (invoiceId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_getArticleHtml', resolve, {
                    'package': 'quiqqer/invoice',
                    invoiceId: invoiceId,
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Return the article html from an temporary invoice
         *
         * @param {Number|String} invoiceId
         * @returns {Promise}
         */
        getArticleHtmlFromTemporary: function (invoiceId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_temporary_getArticleHtml', resolve, {
                    'package': 'quiqqer/invoice',
                    invoiceId: invoiceId,
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Is the user a netto or brutto user?
         *
         * @param userId
         * @return {Promise}
         */
        isNetto: function (userId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_temporary_isNetto', resolve, {
                    'package': 'quiqqer/invoice',
                    uid      : userId,
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Search invoices
         *
         * @param {Object} params - Grid Query Params
         * @param {Object} filter - Filter
         * @returns {Promise}
         */
        search: function (params, filter) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_search', resolve, {
                    'package': 'quiqqer/invoice',
                    params   : JSON.encode(params),
                    filter   : JSON.encode(filter),
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Checks if the invoice has a refund functionality
         *
         * @param {number} invoiceId
         * @return {Promise}
         */
        hasRefund: function (invoiceId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_hasRefund', resolve, {
                    'package': 'quiqqer/invoice',
                    invoiceId: invoiceId,
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Return an invoice setting
         *
         * @param {String} section
         * @param {String} key
         * @return {Promise}
         */
        getSetting: function (section, key) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_setting', resolve, {
                    'package': 'quiqqer/invoice',
                    section  : section,
                    key      : key,
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Returns the invoice templates
         *
         * @return {Promise}
         */
        getTemplates: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_settings_templates', resolve, {
                    'package': 'quiqqer/invoice',
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
                    self.fireEvent('createInvoice', [self, newId]);
                    resolve(newId);
                }, {
                    'package': 'quiqqer/invoice',
                    onError  : reject,
                    showError: false
                });
            });
        },

        /**
         * Create a new temporary invoice
         *
         * @param {number} invoiceId
         * @param {Object} [data]
         *
         *
         * @returns {Promise}
         */
        createCreditNote: function (invoiceId, data) {
            var self = this;

            data = data || {};

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_invoices_createCreditNote', function (newId) {
                    self.fireEvent('createCreditNote', [self, newId]);
                    resolve(newId);
                }, {
                    'package'  : 'quiqqer/invoice',
                    onError    : reject,
                    invoiceId  : invoiceId,
                    invoiceData: JSON.encode(data),
                    showError  : false
                });
            });
        },

        /**
         * Cancellation of an invoice
         *
         * @param {String|Number} invoiceId
         * @param {String} reason
         * @returns {Promise}
         */
        reversalInvoice: function (invoiceId, reason) {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_invoices_reversal', function (reversalId) {
                    self.fireEvent('createCreditNote', [self, invoiceId, reversalId]);
                    resolve(invoiceId, reversalId);
                }, {
                    'package': 'quiqqer/invoice',
                    onError  : reject,
                    invoiceId: invoiceId,
                    reason   : reason,
                    showError: false
                });
            });
        },

        /**
         * Alias for reversalInvoice
         *
         * @param {String|Number} invoiceId
         * @return {Promise}
         */
        cancellationInvoice: function (invoiceId) {
            return this.reversalInvoice(invoiceId);
        },

        /**
         * Delete a temporary invoice
         *
         * @param {String|Number} invoiceId
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
         * Save a temporary invoice
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
         * @return {Promise} - [hash]
         */
        postInvoice: function (invoiceId) {
            var self = this;

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_invoices_temporary_post', function (hash) {
                    self.fireEvent('postInvoice', [self, invoiceId, hash]);
                    resolve(hash);
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
         * Return the combined history of the invoice
         * - history, transaction, comments
         *
         * @param {String} invoiceId
         * @return {Promise}
         */
        getInvoiceHistory: function (invoiceId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_getHistory', resolve, {
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
         * @param {Number|String} invoiceId - id or hash
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
         * Return the invoice html form a temporary invoice
         *
         * @param {String} invoiceId
         * @returns {Promise}
         */
        getInvoicePreview: function (invoiceId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_invoices_preview', resolve, {
                    'package': 'quiqqer/invoice',
                    invoiceId: invoiceId,
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
        getTemporaryInvoiceHtml: function (invoiceId, data) {
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
         * Return the invoice html form a temporary invoice
         *
         * @param {String} invoiceId
         * @param {Object} data
         * @returns {Promise}
         */
        getTemporaryInvoicePreview: function (invoiceId, data) {
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
        },

        /**
         * Return the article summary
         *
         * @param {Object} article - Article attributes
         * @return {Promise}
         */
        getArticleSummary: function (article) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_invoices_temporary_product_summary', resolve, {
                    'package': 'quiqqer/invoice',
                    onError  : reject,
                    article  : JSON.encode(article)
                });
            });
        },

        /**
         * Return the articles from an invoice as HTML
         *
         * @param {String|Number} invoiceId
         * @return {Promise}
         */
        getArticlesHtml: function (invoiceId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_invoices_articleHtml', resolve, {
                    'package': 'quiqqer/invoice',
                    invoiceId: invoiceId,
                    onError  : reject,
                    showError: false
                });
            });
        }
    });
});
