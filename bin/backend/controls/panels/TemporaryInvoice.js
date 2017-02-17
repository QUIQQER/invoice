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
    'package/quiqqer/invoice/bin/Invoices'

], function (QUI, QUIPanel, Invoices) {
    "use strict";

    return new Class({

        Extends: QUIPanel,
        Type: 'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice',

        Binds: [
            '$onCreate',
            '$onInject'
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
                onInject: this.$onInject
            });
        },

        /**
         * event: on create
         */
        $onCreate: function () {
            this.addCategory({
                text: 'Rechnungsdaten',
                events: {
                    onClick: function () {
                    }
                }
            });

            this.addCategory({
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

        addArticle: function () {

        }
    });
});