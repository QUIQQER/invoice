/**
 * @module package/quiqqer/invoice/bin/backend/utils/Dialogs
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event (global) onQuiqqerInvoiceCreateCreditNoteDialogOpen [invoiceId, Win]
 * @event (global) onQuiqqerInvoiceCreateCreditNoteDialogSubmit [creditNoteId, Win]
 */
define('package/quiqqer/invoice/bin/backend/utils/Dialogs', [

    'qui/QUI',
    'Locale',
    'package/quiqqer/invoice/bin/Invoices',
    'qui/controls/windows/Confirm',
    'qui/controls/windows/Popup',

    'css!package/quiqqer/invoice/bin/backend/utils/Dialogs.css'

], function (QUI, QUILocale, Invoices, QUIConfirm, QUIPopup) {
    'use strict';

    const lg = 'quiqqer/invoice';

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
                            entityId: invoiceId,
                            entityType: entityType,
                            entityPlugin: 'quiqqer/invoice',
                            comments: comments.length ? comments : false
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
                const id = result.id_prefix + result.id;

                return new Promise(function (resolve, reject) {
                    new QUIConfirm({
                        icon: 'fa fa-ban',
                        texticon: 'fa fa-ban',
                        title: QUILocale.get(lg, 'dialog.invoice.reversal.title', {
                            invoiceId: id
                        }),
                        text: QUILocale.get(lg, 'dialog.invoice.reversal.text', {
                            invoiceId: id
                        }),
                        information: QUILocale.get(lg, 'dialog.invoice.reversal.information', {
                            invoiceId: id
                        }),
                        autoclose: false,
                        ok_button: {
                            text: QUILocale.get(lg, 'dialog.invoice.reversal.submit'),
                            textimage: 'fa fa-ban'
                        },
                        maxHeight: 500,
                        maxWidth: 750,
                        events: {
                            onOpen: function (Win) {
                                const Container = Win.getContent().getElement('.textbody');

                                // #locale
                                const Label = new Element('label', {
                                    html: '<span>' + QUILocale.get(
                                        lg,
                                        'dialog.invoice.reversal.reason.title'
                                    ) + '</span>',
                                    styles: {
                                        display: 'block',
                                        fontWeight: 'bold',
                                        marginTop: 20,
                                        width: 'calc(100% - 100px)'
                                    }
                                }).inject(Container);

                                const Reason = new Element('textarea', {
                                    name: 'reason',
                                    autofocus: true,
                                    placeholder: QUILocale.get(lg, 'dialog.invoice.reversal.reason.placeholder'),
                                    styles: {
                                        height: 160,
                                        marginTop: 10,
                                        width: '100%'
                                    }
                                }).inject(Label);

                                Reason.focus();
                            },

                            onSubmit: function (Win) {
                                const Reason = Win.getContent().getElement('[name="reason"]');
                                const value = Reason.value;

                                if (value === '') {
                                    Reason.focus();
                                    Reason.required = true;

                                    if ('reportValidity' in Reason) {
                                        Reason.reportValidity();
                                    }

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
                const id = result.id_prefix + result.id;

                return new Promise(function (resolve) {
                    new QUIConfirm({
                        title: QUILocale.get(lg, 'dialog.invoice.copy.title'),
                        text: QUILocale.get(lg, 'dialog.invoice.copy.text'),
                        information: QUILocale.get(lg, 'dialog.invoice.copy.information', {
                            id: id
                        }),
                        icon: 'fa fa-copy',
                        texticon: 'fa fa-copy',
                        maxHeight: 400,
                        maxWidth: 600,
                        autoclose: false,
                        ok_button: {
                            text: QUILocale.get('quiqqer/system', 'copy'),
                            textimage: 'fa fa-copy'
                        },
                        events: {
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
            const self = this;

            return Invoices.get(invoiceId).then(function (result) {
                let paymentHasRefund = false;
                const id = result.id_prefix + result.id;

                return new Promise(function (resolve, reject) {
                    new QUIConfirm({
                        icon: 'fa fa-clipboard',
                        texticon: 'fa fa-clipboard',
                        title: QUILocale.get(lg, 'dialog.invoice.createCreditNote.title', {
                            invoiceId: id
                        }),
                        text: QUILocale.get(lg, 'dialog.invoice.createCreditNote.text', {
                            invoiceId: id
                        }),
                        information: QUILocale.get(lg, 'dialog.invoice.createCreditNote.information', {
                            invoiceId: id
                        }),
                        autoclose: false,
                        ok_button: {
                            text: QUILocale.get(lg, 'dialog.invoice.createCreditNote.submit'),
                            textimage: 'fa fa-clipboard'
                        },
                        maxHeight: 400,
                        maxWidth: 600,
                        events: {
                            onOpen: function (Win) {
                                Win.Loader.show();

                                Invoices.hasRefund(id).then(function (hasRefund) {
                                    paymentHasRefund = hasRefund;

                                    QUI.fireEvent('quiqqerInvoiceCreateCreditNoteDialogOpen', [id, Win]);

                                    if (!paymentHasRefund) {
                                        Win.Loader.hide();
                                        return;
                                    }

                                    const Content = Win.getContent(),
                                        Body = Content.getElement('.textbody');

                                    new Element('label', {
                                        'class': 'quiqqer-invoice-dialog-refund-label',
                                        html: '<input type="checkbox" name="refund" />' + QUILocale.get(
                                            lg,
                                            'dialog.invoice.createCreditNote.refund'
                                        ),
                                        styles: {
                                            cursor: 'pointer',
                                            display: 'block',
                                            marginTop: 20
                                        }
                                    }).inject(Body);

                                    Win.Loader.hide();
                                });
                            },

                            onSubmit: function (Win) {
                                Win.Loader.show();

                                const Content = Win.getContent(),
                                    Refund = Content.getElement('[name="refund"]');

                                const createInvoice = function (values) {
                                    values = values || {};

                                    Invoices.createCreditNote(result.hash, values).then(function (newId) {
                                        QUI.fireEvent(
                                            'quiqqerInvoiceCreateCreditNoteDialogSubmit',
                                            [newId, Win]
                                        );

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
                        invoiceId: invoiceId,
                        autoRefund: false,
                        events: {
                            onSubmit: resolve,
                            onCancel: function () {
                                resolve(false);
                            }
                        }
                    }).open();
                });
            });
        },

        openDownloadDialog: function (hash) {
            new QUIConfirm({
                icon: 'fa fa-download',
                title: QUILocale.get(lg, 'dialog.invoice.download.title'),
                maxHeight: 400,
                maxWidth: 600,
                autoclose: false,
                ok_button: {
                    text: QUILocale.get(lg, 'dialog.invoice.download.button'),
                    textimage: 'fa fa-download'
                },
                events: {
                    onOpen: function (Win) {
                        Win.Loader.show();

                        const Content = Win.getContent();
                        Content.classList.add('quiqqer-invoice-download-dialog');

                        Content.set(
                            'html',

                            '<h3>' + QUILocale.get(lg, 'dialog.invoice.download.header') + '</h3>' +
                            QUILocale.get(lg, 'dialog.invoice.download.text') +
                            '<select class="quiqqer-invoice-download-dialog-select">' +
                            '   <option value="PDF">E-Rechnung (ZUGFeRD EN16931 - PDF)</option>' +
                            '   <option value="PROFILE_BASIC">ZUGFeRD Basic (XML)</option>' +
                            '   <option value="PROFILE_EN16931">ZUGFeRD EN16931 (XML)</option>' +
                            '   <option value="PROFILE_EXTENDED">ZUGFeRD Extended (XML)</option>' +
                            '   <option value="PROFILE_XRECHNUNG_2_3">XRechnung 2.3 (XML)</option>' +
                            '   <option value="PROFILE_XRECHNUNG_3">XRechnung 3 (XML)</option>' +
                            '</select>'
                        );

                        Win.Loader.hide();
                    },

                    onSubmit: function (Win) {
                        Win.Loader.show();

                        const Select = Win.getElm().querySelector('select');

                        require([
                            URL_OPT_DIR + 'bin/quiqqer-asset/downloadjs/downloadjs/download.js'
                        ], function (download) {
                            const url = URL_OPT_DIR + 'quiqqer/invoice/bin/backend/download.php?' +
                                new URLSearchParams({
                                    invoice: hash,
                                    type: Select.value
                                }).toString();

                            fetch(url).then(response => {
                                if (!response.ok) {
                                    throw new Error("Fehler beim Download: " + response.statusText);
                                }

                                let filename = "invoice.pdf"; // Fallback-Dateiname
                                const contentDisposition = response.headers.get("Content-Disposition");

                                if (contentDisposition) {
                                    const match = contentDisposition.match(/filename="?([^"]+)"?/);
                                    if (match) {
                                        filename = match[1];
                                    }
                                }

                                return response.blob().then(blob => ({blob, filename}));
                            }).then(({blob, filename}) => {
                                download(blob, filename);
                                Win.Loader.hide();
                            }).catch(error => {
                                Win.Loader.hide();
                            });
                        });
                    }
                }
            }).open();
        }
    };
});
