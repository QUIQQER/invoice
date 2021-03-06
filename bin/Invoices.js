/**
 * @module package/quiqqer/invoice/bin/Invoices
 *
 * Main instance of the invoice handler
 */
define('package/quiqqer/invoice/bin/Invoices', [
    'package/quiqqer/invoice/bin/backend/classes/Invoices'
], function (Invoices) {
    "use strict";
    return new Invoices();
});
