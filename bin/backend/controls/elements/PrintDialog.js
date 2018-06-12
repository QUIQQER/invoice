/**
 * @module package/quiqqer/invoice/bin/backend/controls/elements/PrintDialog
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/invoice/bin/backend/controls/elements/PrintDialog', [

    'qui/QUI',
    'qui/controls/windows/Confirm',
    'qui/controls/buttons/Select',
    'qui/controls/elements/Sandbox',
    'Locale',
    'Mustache',
    'Users',
    'package/quiqqer/invoice/bin/Invoices',

    'text!package/quiqqer/invoice/bin/backend/controls/elements/PrintDialog.html',
    'css!package/quiqqer/invoice/bin/backend/controls/elements/PrintDialog.css'

], function (QUI, QUIConfirm, QUISelect, QUISandbox, QUILocale, Mustache, Users, Invoices, template) {
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
            maxHeight: 800,
            maxWidth : 1400
        },

        initialize: function (options) {
            this.parent(options);

            this.setAttributes({
                icon         : 'fa fa-print',
                title        : QUILocale.get(lg, 'dialog.print.title'),
                autoclose    : false,
                cancel_button: {
                    textimage: 'fa fa-close',
                    text     : QUILocale.get('quiqqer/system', 'close')
                }
            });

            this.$Output      = null;
            this.$Preview     = null;
            this.$invoiceData = null;
            this.$cutomerMail = null;

            this.addEvents({
                onOpen     : this.$onOpen,
                onSubmit   : this.$onSubmit,
                onOpenBegin: function () {
                    var winSize = QUI.getWindowSize();
                    var height  = 800;
                    var width   = 1400;

                    if (winSize.y * 0.9 < height) {
                        height = winSize.y * 0.9;
                    }

                    if (winSize.x * 0.9 < width) {
                        width = winSize.x * 0.9;
                    }

                    this.setAttribute('maxHeight', height);
                    this.setAttribute('maxWidth', width);
                }.bind(this)
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
                console.error(error);

                self.close().then(function () {
                    self.destroy();
                });

                QUI.getMessageHandler().then(function (MH) {
                    if (typeof error === 'object' && typeof error.getMessage !== 'undefined') {
                        MH.addError(error.getMessage());
                        return;
                    }

                    MH.addError(error);
                });
            };

            if (!this.getAttribute('invoiceId')) {
                onError('No invoice ID was given.');
                return;
            }

            Promise.all([
                Invoices.get(this.getAttribute('invoiceId')),
                Invoices.getTemplates(),
                Invoices.getInvoicePreview(this.getAttribute('invoiceId'))
            ]).then(function (result) {
                var templates = result[1],
                    html      = result[2],
                    prfx      = '';

                self.$invoiceData = result[0];

                if (typeof self.$invoiceData.id_prefix !== 'undefined') {
                    prfx = self.$invoiceData.id_prefix;
                }

                Content.set({
                    html: Mustache.render(template, {
                        invoiceNumber    : prfx + self.$invoiceData.id,
                        textInvoiceNumber: QUILocale.get(lg, 'dialog.print.data.number'),
                        textOutput       : QUILocale.get(lg, 'dialog.print.data.output'),
                        textTemplate     : QUILocale.get(lg, 'dialog.print.data.template'),
                        textEmail        : QUILocale.get('quiqqer/quiqqer', 'recipient')
                    })
                });

                Content.addClass('quiqqer-invoice-printDialog');

                self.$Preview = Content.getElement('.quiqqer-invoice-printDialog-preview');

                new QUISandbox({
                    content: html,
                    styles : {
                        height : 1240,
                        padding: 20,
                        width  : 874
                    },
                    events : {
                        onLoad: function (Box) {
                            Box.getElm().addClass('quiqqer-invoice-printDialog-invoice-preview');
                        }
                    }
                }).inject(self.$Preview);

                var Form     = Content.getElement('form'),
                    selected = '';

                for (var i = 0, len = templates.length; i < len; i++) {
                    new Element('option', {
                        value: templates[i].name,
                        html : templates[i].title
                    }).inject(Form.elements.template);

                    if (templates[i].default) {
                        selected = templates[i].name;
                    }
                }

                Form.elements.template.value = selected;

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

                if (typeof self.$invoiceData.customer_data !== 'undefined') {
                    var data = JSON.decode(self.$invoiceData.customer_data);

                    if (data && typeof data.email !== 'undefined') {
                        self.$cutomerMail = data.email;
                    }

                    if (data && self.$cutomerMail === null || self.$cutomerMail === '') {
                        return new Promise(function (resolve) {
                            // get customer id
                            Users.get(data.id).load().then(function (User) {
                                self.$cutomerMail = User.getAttribute('email');
                                resolve();
                            }).catch(function (Exception) {
                                //onError(Exception);
                                resolve();
                            });
                        });
                    }
                }
            }).then(function () {
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
                var id      = 'print-invoice-' + invoiceId,
                    Content = self.getContent(),
                    Form    = Content.getElement('form');

                self.Loader.show();

                new Element('iframe', {
                    src   : URL_OPT_DIR + 'quiqqer/invoice/bin/backend/printInvoice.php?' + Object.toQueryString({
                        invoiceId: invoiceId,
                        oid      : self.getId(),
                        template : Form.elements.template.value
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
                this.close();
            }).delay(1000, this);
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
                var id      = 'download-invoice-' + invoiceId,
                    Content = self.getContent(),
                    Form    = Content.getElement('form');

                new Element('iframe', {
                    src   : URL_OPT_DIR + 'quiqqer/invoice/bin/backend/downloadInvoice.php?' + Object.toQueryString({
                        invoiceId: invoiceId,
                        oid      : self.getId(),
                        template : Form.elements.template.value
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
                var id      = 'mail-invoice-' + invoiceId,
                    Content = self.getContent(),
                    Form    = Content.getElement('form');

                new Element('iframe', {
                    src   : URL_OPT_DIR + 'quiqqer/invoice/bin/backend/sendInvoice.php?' + Object.toQueryString({
                        invoiceId: invoiceId,
                        oid      : self.getId(),
                        recipient: recipient,
                        template : Form.elements.template.value
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

            if (this.$cutomerMail && Recipient.value === '') {
                Recipient.value = this.$cutomerMail;
            }

            Recipient.focus();
        }
    });
});