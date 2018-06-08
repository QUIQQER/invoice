/**
 * @module package/quiqqer/invoice/bin/backend/utils/Dialogs
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/invoice/bin/backend/utils/Dialogs', [

    'qui/QUI',
    'Locale',
    'package/quiqqer/invoice/bin/Invoices',
    'qui/controls/windows/Confirm'

], function (QUI, QUILocale, Invoices, QUIConfirm) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return {

        /**
         * Opens the print dialog for a specific invoice
         *
         * @param {String} invoiceId - Invoice ID or Hash
         * @return {Promise}
         */
        openPrintDialog: function (invoiceId) {
            return new Promise(function (resolve) {
                require([
                    'package/quiqqer/invoice/bin/backend/controls/elements/PrintDialog'
                ], function (PrintDialog) {
                    new PrintDialog({
                        invoiceId: invoiceId,
                        events   : {
                            onOpen: resolve
                        }
                    }).open();
                });
            });
        },

        /**
         * Opens a storno / cancellation dialog for a specific invoice
         *
         * @param {String} invoiceId - Invoice ID or Hash
         * @return {Promise}
         */
        openStornoDialog: function (invoiceId) {
            return new Promise(function (resolve, reject) {
                new QUIConfirm({
                    icon       : 'fa fa-ban',
                    texticon   : 'fa fa-ban',
                    title      : QUILocale.get(lg, 'dialog.invoice.reversal.title', {
                        invoiceId: invoiceId
                    }),
                    text       : QUILocale.get(lg, 'dialog.invoice.reversal.text', {
                        invoiceId: invoiceId
                    }),
                    information: QUILocale.get(lg, 'dialog.invoice.reversal.information', {
                        invoiceId: invoiceId
                    }),
                    autoclose  : false,
                    ok_button  : {
                        text     : QUILocale.get(lg, 'dialog.invoice.reversal.submit'),
                        textimage: 'fa fa-ban'
                    },
                    maxHeight  : 500,
                    maxWidth   : 750,
                    events     : {
                        onOpen  : function (Win) {
                            var Container = Win.getContent().getElement('.textbody');

                            // #locale
                            var Label = new Element('label', {
                                html  : '<span>' + QUILocale.get(lg, 'dialog.invoice.reversal.reason.title') + '</span>',
                                styles: {
                                    display   : 'block',
                                    fontWeight: 'bold',
                                    marginTop : 20,
                                    width     : 'calc(100% - 100px)'
                                }
                            }).inject(Container);

                            var Reason = new Element('textarea', {
                                name       : 'reason',
                                autofocus  : true,
                                placeholder: QUILocale.get(lg, 'dialog.invoice.reversal.reason.placeholder'),
                                styles     : {
                                    height   : 160,
                                    marginTop: 10,
                                    width    : '100%'
                                }
                            }).inject(Label);

                            Reason.focus();
                        },
                        onSubmit: function (Win) {
                            var value = Win.getContent().getElement('[name="reason"]').value;

                            if (value === '') {
                                return;
                            }

                            Win.Loader.show();

                            Invoices.reversalInvoice(invoiceId, value).then(function () {
                                Win.close();
                                resolve();
                            }).catch(function (Exception) {
                                Win.close();
                                reject(Exception);
                            });
                        },

                        onCancel: resolve
                    }
                }).open();
            });
        },

        /**
         * Alias for openStornoDialog()
         *
         * @param {String} invoiceId - Invoice ID or Hash
         * @return {*|Promise}
         */
        openCancellationDialog: function (invoiceId) {
            return this.openStornoDialog(invoiceId);
        },

        /**
         * Alias for openStornoDialog()
         *
         * @param {String} invoiceId - Invoice ID or Hash
         * @return {*|Promise}
         */
        openReversalDialog: function (invoiceId) {
            return this.openStornoDialog(invoiceId);
        },

        /**
         * Opens a copy dialog for a specific invoice
         *
         * @param {String} invoiceId - Invoice ID or Hash
         * @return {Promise}
         */
        openCopyDialog: function (invoiceId) {
            return new Promise(function (resolve) {
                new QUIConfirm({
                    title      : QUILocale.get(lg, 'dialog.invoice.copy.title'),
                    text       : QUILocale.get(lg, 'dialog.invoice.copy.text'),
                    information: QUILocale.get(lg, 'dialog.invoice.copy.information', {
                        id: invoiceId
                    }),
                    icon       : 'fa fa-copy',
                    texticon   : 'fa fa-copy',
                    maxHeight  : 400,
                    maxWidth   : 600,
                    autoclose  : false,
                    ok_button  : {
                        text     : QUILocale.get('quiqqer/system', 'copy'),
                        textimage: 'fa fa-copy'
                    },
                    events     : {
                        onSubmit: function (Win) {
                            Win.Loader.show();

                            Invoices.copyInvoice(invoiceId).then(function (newId) {
                                Win.close();
                                resolve(newId);
                            }).then(function () {
                                Win.Loader.hide();
                            });
                        }
                    }
                }).open();
            });
        },

        /**
         * Opens a credit note dialog for a specific invoice
         *
         * @param {String} invoiceId - Invoice ID or Hash
         * @return {Promise}
         */
        openCreateCreditNoteDialog: function (invoiceId) {
            return new Promise(function (resolve, reject) {
                new QUIConfirm({
                    icon       : 'fa fa-clipboard',
                    texticon   : 'fa fa-clipboard',
                    title      : QUILocale.get(lg, 'dialog.invoice.createCreditNote.title', {
                        invoiceId: invoiceId
                    }),
                    text       : QUILocale.get(lg, 'dialog.invoice.createCreditNote.text', {
                        invoiceId: invoiceId
                    }),
                    information: QUILocale.get(lg, 'dialog.invoice.createCreditNote.information', {
                        invoiceId: invoiceId
                    }),
                    autoclose  : false,
                    ok_button  : {
                        text     : QUILocale.get(lg, 'dialog.invoice.createCreditNote.submit'),
                        textimage: 'fa fa-clipboard'
                    },
                    maxHeight  : 400,
                    maxWidth   : 600,
                    events     : {
                        onSubmit: function (Win) {
                            Win.Loader.show();

                            Invoices.createCreditNote(invoiceId).then(function (newId) {
                                resolve(newId);
                                Win.close();
                            }).catch(function (Err) {
                                Win.Loader.hide();
                                console.error(Err);

                                reject(Err);
                            });
                        },
                        onCancel: function () {
                            resolve(false);
                        }
                    }
                }).open();
            });
        }
    };
});
