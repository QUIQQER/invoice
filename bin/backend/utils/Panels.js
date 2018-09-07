/**
 * @module package/quiqqer/invoice/bin/backend/utils/Dialogs
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/invoice/bin/backend/utils/Panels', function () {
    "use strict";

    return {
        /**
         * Opens a temporary invoice
         *
         * @param {String|Number} invoiceId
         * @return {Promise}
         */
        openTemporaryInvoice: function (invoiceId) {
            return new Promise(function (resolve) {
                require([
                    'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice',
                    'utils/Panels'
                ], function (TemporaryInvoice, PanelUtils) {
                    var Panel = new TemporaryInvoice({
                        invoiceId: invoiceId,
                        '#id'    : invoiceId
                    });

                    PanelUtils.openPanelInTasks(Panel);
                    resolve(Panel);
                });
            });
        },

        /**
         * Open the invoice
         *
         * @param {Number} invoiceId - can be a temporary invoice or an invoice
         * @return {Promise}
         */
        openInvoice: function (invoiceId) {
            return new Promise(function (resolve) {
                require([
                    'package/quiqqer/invoice/bin/Invoices',
                    'package/quiqqer/invoice/bin/backend/controls/panels/Invoice',
                    'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice',
                    'utils/Panels'
                ], function (Invoices, InvoicePanel, TemporaryInvoicePanel, PanelUtils) {
                    Invoices.get(invoiceId).then(function (invoiceData) {
                        var Panel = TemporaryInvoicePanel;

                        if (invoiceData.getType === 'QUI\\ERP\\Accounting\\Invoice\\Invoice') {
                            Panel = InvoicePanel;
                        }

                        var P = new Panel({
                            invoiceId: invoiceId,
                            '#id'    : invoiceId
                        });

                        PanelUtils.openPanelInTasks(P);
                        resolve(P);
                    });
                });
            });
        }
    };
});
