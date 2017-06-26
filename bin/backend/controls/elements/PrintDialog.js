/**
 * @module package/quiqqer/invoice/bin/backend/controls/elements/PrintDialog
 *
 * @require qui/QUI
 * @require qui/controls/windows/Confirm
 * @require qui/controls/buttons/Select
 * @require Locale
 * @require Mustache
 * @require package/quiqqer/invoice/bin/Invoices
 * @require text!package/quiqqer/invoice/bin/backend/controls/elements/PrintDialog.html
 * @require css!package/quiqqer/invoice/bin/backend/controls/elements/PrintDialog.css
 */
define('package/quiqqer/invoice/bin/backend/controls/elements/PrintDialog', [

    'qui/QUI',
    'qui/controls/windows/Confirm',
    'qui/controls/buttons/Select',
    'Locale',
    'Mustache',
    'package/quiqqer/invoice/bin/Invoices',

    'text!package/quiqqer/invoice/bin/backend/controls/elements/PrintDialog.html',
    'css!package/quiqqer/invoice/bin/backend/controls/elements/PrintDialog.css'

], function (QUI, QUIConfirm, QUISelect, QUILocale, Mustache, Invoices, template) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIConfirm,
        type   : 'package/quiqqer/invoice/bin/backend/controls/elements/PrintDialog',

        Binds: [
            '$onOpen',
            '$onOutputChange',
            '$onPrintFinish'
        ],

        options: {
            invoiceId: false,
            maxHeight: 400,
            maxWidth : 600
        },

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                icon     : 'fa fa-print',
                title    : QUILocale.get(lg, 'dialog.print.title'),
                autoclose: false
            });

            this.$Output = null;

            this.addEvents({
                onOpen  : this.$onOpen,
                onSubmit: this.$onSubmit
            });
        },

        /**
         * event: on open
         */
        $onOpen: function () {
            var self    = this,
                Content = this.getContent();

            this.Loader.show();
            this.getContent().set('html', '');

            var onError = function (error) {
                self.close().then(function () {
                    self.destroy();
                });

                QUI.getMessageHandler().then(function (MH) {
                    MH.addError(error);
                });
            };

            if (!this.getAttribute('invoiceId')) {
                onError('Es wurde keine Rechnungs-ID übergeben.');
                return;
            }

            Promise.all([
                Invoices.get(this.getAttribute('invoiceId')),
                Invoices.getTemplates()
            ]).then(function (result) {

                var invoiceData = result[0];
                var templates   = result[1];

                Content.set({
                    html: Mustache.render(template, {
                        invoiceNumber    : invoiceData.id_prefix + invoiceData.id,
                        textInvoiceNumber: QUILocale.get(lg, 'dialog.print.data.number'),
                        textOutput       : QUILocale.get(lg, 'dialog.print.data.output'),
                        textTemplate     : QUILocale.get(lg, 'dialog.print.data.template'),
                        textEmail        : QUILocale.get('quiqqer/quiqqer', 'recipient')
                    })
                });

                Content.addClass('quiqqer-invoice-printDialog');

                var Form = Content.getElement('form');

                for (var i = 0, len = templates.length; i < len; i++) {
                    new Element('option', {
                        value: templates[i].name,
                        html : templates[i].title
                    }).inject(Form.elements.template);
                }

                self.$Output = new QUISelect({
                    name  : 'output',
                    styles: {
                        border: 'none',
                        width : '100%'
                    },
                    events: {
                        onChange: self.$onOutputChange
                    }
                }).inject(Content.getElement('.field-output'));


                self.$Output.appendChild(
                    QUILocale.get(lg, 'dialog.print.data.output.print'),
                    'print',
                    'fa fa-print'
                );

                self.$Output.appendChild(
                    QUILocale.get(lg, 'dialog.print.data.output.pdf'),
                    'pdf',
                    'fa fa-file-pdf-o'
                );

                self.$Output.appendChild(
                    QUILocale.get(lg, 'dialog.print.data.output.email'),
                    'email',
                    'fa fa-envelope-o'
                );

                self.$Output.setValue('print');
                self.Loader.hide();
            }).catch(function (e) {
                onError(e);
            });
        },

        /**
         * event: on submit
         */
        $onSubmit: function () {
            var self = this,
                Run  = Promise.resolve();

            this.Loader.show();

            switch (this.$Output.getValue()) {
                case 'print':
                    Run = this.print();
                    break;

                case 'pdf':
                    Run = this.saveAsPdf();
                    break;

                case 'email':
                    Run = this.sendAsEmail();
                    break;
            }

            Run.then(function () {
                self.Loader.hide();
            });
        },

        /**
         * Print the invoice
         *
         * @return {Promise}
         */
        print: function () {
            var self      = this,
                invoiceId = this.getAttribute('invoiceId');

            return new Promise(function (resolve) {
                var id = 'print-invoice-' + invoiceId;

                new Element('iframe', {
                    src   : URL_OPT_DIR + 'quiqqer/invoice/bin/backend/printInvoice.php?' + Object.toQueryString({
                        invoiceId: invoiceId,
                        oid      : self.getId()
                    }),
                    id    : id,
                    styles: {
                        position: 'absolute',
                        top     : -200,
                        left    : -200,
                        width   : 50,
                        height  : 50
                    }
                }).inject(document.body);

                self.addEvent('onPrintFinish', function (self, pId) {
                    if (pId === invoiceId) {
                        resolve();
                    }
                });
            });
        },

        /**
         * event: on print finish
         *
         * @param {String|Number} id
         */
        $onPrintFinish: function (id) {
            this.fireEvent('printFinish', [this, id]);

            (function () {
                document.getElements('#print-invoice-' + id).destroy();
            }).delay(1000);
        },

        /**
         * Export the invoice as PDF
         *
         * @return {Promise}
         */
        saveAsPdf: function () {
            var self      = this,
                invoiceId = this.getAttribute('invoiceId');

            return new Promise(function (resolve) {
                var id = 'download-invoice-' + invoiceId;

                new Element('iframe', {
                    src   : URL_OPT_DIR + 'quiqqer/invoice/bin/backend/downloadInvoice.php?' + Object.toQueryString({
                        invoiceId: invoiceId,
                        oid      : self.getId()
                    }),
                    id    : id,
                    styles: {
                        position: 'absolute',
                        top     : -200,
                        left    : -200,
                        width   : 50,
                        height  : 50
                    }
                }).inject(document.body);

                (function () {
                    document.getElements('#' + id).destroy();
                    resolve();
                }).delay(2000, this);
            });
        },

        /**
         * Send the invoice to an E-Mail
         *
         * @return {Promise}
         */
        sendAsEmail: function () {
            var self      = this,
                invoiceId = this.getAttribute('invoiceId'),
                recipient = this.getElm().getElement('[name="recipient"]').value;

            return new Promise(function (resolve) {
                var id = 'mail-invoice-' + invoiceId;

                new Element('iframe', {
                    src   : URL_OPT_DIR + 'quiqqer/invoice/bin/backend/sendInvoice.php?' + Object.toQueryString({
                        invoiceId: invoiceId,
                        oid      : self.getId(),
                        recipient: recipient
                    }),
                    id    : id,
                    styles: {
                        position: 'absolute',
                        top     : -200,
                        left    : -200,
                        width   : 50,
                        height  : 50
                    }
                }).inject(document.body);

                (function () {
                    document.getElements('#' + id).destroy();
                    resolve();
                }).delay(2000, this);
            });
        },

        /**
         * event : on output change
         *
         * @return {Promise}
         */
        $onOutputChange: function () {
            var Recipient = this.getElm().getElement('[name="recipient"]');

            Recipient.getParent('tr').setStyle('display', 'none');

            switch (this.$Output.getValue()) {
                case 'print':
                    this.$onChangeToPrint();
                    break;

                case 'pdf':
                    this.$onChangeToPDF();
                    break;

                case 'email':
                    this.$onChangeToEmail();
                    break;
            }
        },

        /**
         * event: on output change -> to print
         */
        $onChangeToPrint: function () {
            var Submit = this.getButton('submit');

            Submit.setAttribute('text', QUILocale.get(lg, 'dialog.print.data.output.print.btn'));
            Submit.setAttribute('textimage', 'fa fa-print');

        },

        /**
         * event: on output change -> to pdf
         */
        $onChangeToPDF: function () {
            var Submit = this.getButton('submit');

            Submit.setAttribute('text', QUILocale.get(lg, 'dialog.print.data.output.pdf.btn'));
            Submit.setAttribute('textimage', 'fa fa-file-pdf-o');
        },

        /**
         * event: on output change -> to Email
         */
        $onChangeToEmail: function () {
            var Submit    = this.getButton('submit');
            var Recipient = this.getElm().getElement('[name="recipient"]');

            Recipient.getParent('tr').setStyle('display', null);

            Submit.setAttribute('text', QUILocale.get(lg, 'dialog.print.data.output.email.btn'));
            Submit.setAttribute('textimage', 'fa fa-envelope-o');

            Recipient.focus();
        }
    });
});