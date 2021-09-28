/**
 * @module package/quiqqer/invoice/bin/backend/classes/ProcessingStatus
 * @author www.pcsg.de (Henning Leutz)
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
         * @param {Object} [Options]
         * @return {Promise}
         */
        createProcessingStatus: function (id, color, title, Options) {
            Options = Options || {};

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
                    options  : JSON.encode(Options),
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
         * @param {Object} [Options]
         * @return {Promise}
         */
        updateProcessingStatus: function (id, color, title, Options) {
            Options = Options || {};

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_processingStatus_update', resolve, {
                    'package': 'quiqqer/invoice',
                    id       : id,
                    color    : color,
                    title    : JSON.encode(title),
                    options  : JSON.encode(Options),
                    onError  : reject
                });
            });
        }
    });
});
