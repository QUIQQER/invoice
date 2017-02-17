/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice
 *
 * Edit a Temporary Invoice and created a posted invoice
 *
 * @require qui/QUI
 * @require qui/controls/desktop/Panel
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/windows/Confirm',
    'package/quiqqer/invoice/bin/Invoices',
    'Locale'

], function (QUI, QUIPanel, QUIConfirm, Invoices, QUILocale) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIPanel,
        Type: 'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice',

        Binds: [
            '$onCreate',
            '$onInject',
            '$onDestroy',
            '$onDeleteInvoice',
            '$clickDelete'
        ],

        options: {
            invoiceId: false
        },

        initialize: function (options) {
            this.setAttributes({
                icon: 'fa fa-money'
            });

            this.parent(options);

            this.addEvents({
                onCreate: this.$onCreate,
                onInject: this.$onInject,
                onDestroy: this.$onDestroy
            });

            Invoices.addEvents({
                onDeleteInvoice: this.$onDeleteInvoice
            });
        },

        /**
         *
         */
        addArticle: function () {

        },

        /**
         * Event Handling
         */

        /**
         * event: on create
         */
        $onCreate: function () {
            this.addButton({
                icon: 'fa fa-trash',
                styles: {
                    'float': 'right'
                },
                events: {
                    onClick: this.$clickDelete
                }
            });

            this.addCategory({
                icon: 'fa fa-info',
                text: 'Rechnungsdaten',
                events: {
                    onClick: function () {
                    }
                }
            });

            this.addCategory({
                icon: 'fa fa-list',
                text: 'Positionen (Produkte)',
                events: {
                    onClick: function () {
                    }
                }
            });

            this.addCategory({
                icon: 'fa fa-check',
                text: 'Überprüfung',
                events: {
                    onClick: function () {
                    }
                }
            });
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            this.Loader.show();

            if (!this.getAttribute('invoiceId')) {
                this.destroy();
                return;
            }

            Invoices.getTemporaryInvoice(this.getAttribute('invoiceId')).then(function (data) {
                this.setAttribute('title', data.id);

                this.refresh();
                this.Loader.hide();

            }.bind(this)).catch(function (Exception) {
                QUI.getMessageHandler().then(function (MH) {
                    MH.addError(Exception.getMessage());
                });

                this.destroy();
            }.bind(this));
        },

        /**
         * event: on panel destroy
         */
        $onDestroy: function () {
            Invoices.removeEvents({
                onDeleteInvoice: this.$onDeleteInvoice
            });
        },

        /**
         * opens the delete dialog
         */
        $clickDelete: function () {
            var self = this;

            new QUIConfirm({
                title: QUILocale.get(lg, 'dialog.ti.delete.title'),
                text: QUILocale.get(lg, 'dialog.ti.delete.text'),
                information: QUILocale.get(lg, 'dialog.ti.delete.information', {
                    id: this.getAttribute('invoiceId')
                }),
                icon: 'fa fa-trash',
                texticon: 'fa fa-trash',
                maxHeight: 400,
                maxWidth: 600,
                autoclose: false,
                ok_button: {
                    text: QUILocale.get('quiqqer/system', 'delete'),
                    textimage: 'fa fa-trash'
                },
                events: {
                    onSubmit: function (Win) {
                        Win.Loader.show();

                        Invoices.deleteInvoice(self.getAttribute('invoiceId')).then(function () {
                            Win.close();
                        }).then(function () {
                            Win.Loader.show();
                        });
                    }
                }
            }).open();
        },

        /**
         * event : on invoice deletion
         */
        $onDeleteInvoice: function () {
            this.destroy();
        }
    });
});