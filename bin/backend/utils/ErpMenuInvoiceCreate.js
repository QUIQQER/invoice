define('package/quiqqer/invoice/bin/backend/utils/ErpMenuInvoiceCreate', function () {
    "use strict";

    return function () {
        return new Promise(function (resolve) {
            require([
                'package/quiqqer/invoice/bin/Invoices',
                'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice',
                'utils/Panels'
            ], function (Invoices, TemporaryInvoice, PanelUtils) {

                return Invoices.createInvoice().then(function (invoiceId) {
                    PanelUtils.openPanelInTasks(
                        new TemporaryInvoice({
                            invoiceId: invoiceId,
                            '#id'    : invoiceId
                        })
                    );

                    resolve();
                });
            });
        });
    };
});
