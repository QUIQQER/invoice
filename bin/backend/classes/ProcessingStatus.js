/**
 * @module package/quiqqer/invoice/bin/backend/classes/ProcessingStatus
 *
 * @require qui/QUI
 * @require qui/classes/DOM
 * @require Ajax
 *
 * @event onCreateInvoice [self, invoiceId]
 * @event onDeleteInvoice [self, invoiceId]
 * @event onCopyInvoice [self, invoiceId, newId]
 */
define('package/quiqqer/invoice/bin/backend/classes/ProcessingStatus', [

    'qui/QUI',
    'qui/classes/DOM',
    'Ajax'

], function (QUI, QUIDOM, QUIAjax) {
    "use strict";

    return new Class({

        Extends: QUIDOM,
        Type   : 'package/quiqqer/invoice/bin/backend/classes/ProcessingStatus',

        initialize: function (options) {
            this.parent(options);
        },

        /**
         * Return the processing status list for a grid
         *
         * @return {Promise}
         */
        getList: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_processingStatus_list', resolve, {
                    'package': 'quiqqer/invoice',
                    onError  : reject
                });
            });
        },

        /**
         * Return next available ID
         *
         * @return {Promise}
         */
        getNextId: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_processingStatus_getNextId', resolve, {
                    'package': 'quiqqer/invoice',
                    onError  : reject
                });
            });
        },

        /**
         * Create a new processing status
         *
         * @param {String|Number} id - Processing Status ID
         * @param {String} color
         * @param {Object} title - {de: '', en: ''}
         * @return {Promise}
         */
        createProcessingStatus: function (id, color, title) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_processingStatus_create', function (result) {
                    require([
                        'package/quiqqer/translator/bin/Translator'
                    ], function (Translator) {
                        Translator.refreshLocale().then(function () {
                            resolve(result);
                        });
                    });
                }, {
                    'package': 'quiqqer/invoice',
                    id       : id,
                    color    : color,
                    title    : JSON.encode(title),
                    onError  : reject
                });
            });
        },

        /**
         * Delete a processing status
         *
         * @param {String|Number} id - Processing Status ID
         * @return {Promise}
         */
        deleteProcessingStatus: function (id) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_processingStatus_delete', function () {
                    require([
                        'package/quiqqer/translator/bin/Translator'
                    ], function (Translator) {
                        Translator.refreshLocale().then(function () {
                            resolve();
                        });
                    });
                }, {
                    'package': 'quiqqer/invoice',
                    id       : id,
                    onError  : reject
                });
            });
        },

        /**
         * Return the status data
         *
         * @param {String|Number} id - Processing Status ID
         * @return {Promise}
         */
        getProcessingStatus: function (id) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_processingStatus_get', resolve, {
                    'package': 'quiqqer/invoice',
                    id       : id,
                    onError  : reject
                });
            });
        },

        /**
         * Return the status data
         *
         * @param {String|Number} id - Processing Status ID
         * @param {String} color
         * @param {Object} title - {de: '', en: ''}
         * @return {Promise}
         */
        updateProcessingStatus: function (id, color, title) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_processingStatus_update', resolve, {
                    'package': 'quiqqer/invoice',
                    id       : id,
                    color    : color,
                    title    : JSON.encode(title),
                    onError  : reject
                });
            });
        }
    });
});
