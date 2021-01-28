/**
 * @module package/quiqqer/invoice/bin/backend/utils/Dialogs
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/invoice/bin/backend/utils/Dialogs', [

    'qui/QUI',
    'Locale',
    'package/quiqqer/invoice/bin/Invoices',
    'qui/controls/windows/Confirm',

    'css!package/quiqqer/invoice/bin/backend/utils/Dialogs.css'

], function (QUI, QUILocale, Invoices, QUIConfirm) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return {

        /**
         * Opens the print dialog for a specific invoice
         *
         * @param {String} invoiceId - Invoice ID or Hash
         * @param {String} [entityType]
         * @return {Promise}
         */
        openPrintDialog: function (invoiceId, entityType) {
            entityType = entityType || 'Invoice';

            return Invoices.getInvoiceHistory(invoiceId).then(function (comments) {
                return new Promise(function (resolve) {
                    require([
                        'package/quiqqer/erp/bin/backend/controls/OutputDialog'
                    ], function (OutputDialog) {
                        new OutputDialog({
                            entityId  : invoiceId,
                            entityType: entityType,
                            comments  : comments.length ? comments : false
                        }).open();

                        resolve();
                    });
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
            return Invoices.get(invoiceId).then(function (result) {
                var id = result.id_prefix + result.id;

                return new Promise(function (resolve, reject) {
                    new QUIConfirm({
                        icon       : 'fa fa-ban',
                        texticon   : 'fa fa-ban',
                        title      : QUILocale.get(lg, 'dialog.invoice.reversal.title', {
                            invoiceId: id
                        }),
                        text       : QUILocale.get(lg, 'dialog.invoice.reversal.text', {
                            invoiceId: id
                        }),
                        information: QUILocale.get(lg, 'dialog.invoice.reversal.information', {
                            invoiceId: id
                        }),
                        autoclose  : false,
                        ok_button  : {
                            text     : QUILocale.get(lg, 'dialog.invoice.reversal.submit'),
                            textimage: 'fa fa-ban'
                        },
                        maxHeight  : 500,
                        maxWidth   : 750,
                        events     : {
                            onOpen: function (Win) {
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

                                Invoices.reversalInvoice(result.hash, value).then(function (result) {
                                    Win.close();
                                    resolve(result);
                                }).catch(function (Exception) {
                                    Win.close();
                                    reject(Exception);
                                });
                            },

                            onCancel: resolve
                        }
                    }).open();
                });
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
            return Invoices.get(invoiceId).then(function (result) {
                var id = result.id_prefix + result.id;

                return new Promise(function (resolve) {
                    new QUIConfirm({
                        title      : QUILocale.get(lg, 'dialog.invoice.copy.title'),
                        text       : QUILocale.get(lg, 'dialog.invoice.copy.text'),
                        information: QUILocale.get(lg, 'dialog.invoice.copy.information', {
                            id: id
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

                                Invoices.copyInvoice(result.hash).then(function (newId) {
                                    Win.close();
                                    resolve(newId);
                                }).then(function () {
                                    Win.Loader.hide();
                                });
                            }
                        }
                    }).open();
                });
            });
        },

        /**
         * Opens a credit note dialog for a specific invoice
         *
         * @param {String} invoiceId - Invoice ID or Hash
         * @return {Promise}
         */
        openCreateCreditNoteDialog: function (invoiceId) {
            var self = this;

            return Invoices.get(invoiceId).then(function (result) {
                var paymentHasRefund = false;
                var id               = result.id_prefix + result.id;

                return new Promise(function (resolve, reject) {
                    new QUIConfirm({
                        icon       : 'fa fa-clipboard',
                        texticon   : 'fa fa-clipboard',
                        title      : QUILocale.get(lg, 'dialog.invoice.createCreditNote.title', {
                            invoiceId: id
                        }),
                        text       : QUILocale.get(lg, 'dialog.invoice.createCreditNote.text', {
                            invoiceId: id
                        }),
                        information: QUILocale.get(lg, 'dialog.invoice.createCreditNote.information', {
                            invoiceId: id
                        }),
                        autoclose  : false,
                        ok_button  : {
                            text     : QUILocale.get(lg, 'dialog.invoice.createCreditNote.submit'),
                            textimage: 'fa fa-clipboard'
                        },
                        maxHeight  : 400,
                        maxWidth   : 600,
                        events     : {
                            onOpen: function (Win) {
                                Win.Loader.show();

                                Invoices.hasRefund(id).then(function (hasRefund) {
                                    paymentHasRefund = hasRefund;

                                    if (!paymentHasRefund) {
                                        Win.Loader.hide();
                                        return;
                                    }

                                    var Content = Win.getContent(),
                                        Body    = Content.getElement('.textbody');

                                    new Element('label', {
                                        'class': 'quiqqer-invoice-dialog-refund-label',
                                        html   : '<input type="checkbox" name="refund" />' + QUILocale.get(lg, 'dialog.invoice.createCreditNote.refund'),
                                        styles : {
                                            cursor   : 'pointer',
                                            display  : 'block',
                                            marginTop: 20
                                        }
                                    }).inject(Body);

                                    Win.Loader.hide();
                                });
                            },

                            onSubmit: function (Win) {
                                Win.Loader.show();

                                var Content = Win.getContent(),
                                    Refund  = Content.getElement('[name="refund"]');

                                var createInvoice = function (values) {
                                    values = values || {};

                                    Invoices.createCreditNote(result.hash, values).then(function (newId) {
                                        resolve(newId);
                                        Win.close();
                                    }).catch(function (Err) {
                                        Win.Loader.hide();
                                        console.error(Err);

                                        reject(Err);
                                    });
                                };

                                if (paymentHasRefund && Refund.checked) {
                                    self.openRefundWindow(invoiceId).then(function (RefundWindow) {
                                        if (!RefundWindow) {
                                            Win.Loader.hide();
                                            return;
                                        }

                                        createInvoice({
                                            refund: RefundWindow.getValues()
                                        });
                                    }).catch(function (Err) {
                                        Win.Loader.hide();
                                        console.error(Err);
                                    });
                                    return;
                                }

                                createInvoice();
                            },

                            onCancel: function () {
                                resolve(false);
                            }
                        }
                    }).open();
                });
            });
        },

        /**
         *
         * @param invoiceId
         * @return {Promise}
         */
        openRefundWindow: function (invoiceId) {
            return new Promise(function (resolve) {
                require([
                    'package/quiqqer/invoice/bin/backend/controls/panels/refund/Window'
                ], function (RefundWindow) {
                    new RefundWindow({
                        invoiceId : invoiceId,
                        autoRefund: false,
                        events    : {
                            onSubmit: resolve,
                            onCancel: function () {
                                resolve(false);
                            }
                        }
                    }).open();
                });
            });
        }
    };
});
