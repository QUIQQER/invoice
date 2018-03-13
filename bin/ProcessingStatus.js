/**
 * @module package/quiqqer/invoice/bin/ProcessingStatus
 *
 * Main instance of the processing status handler
 */
define('package/quiqqer/invoice/bin/ProcessingStatus', [
    'package/quiqqer/invoice/bin/backend/classes/ProcessingStatus'
], function (ProcessingStatus) {
    "use strict";
    return new ProcessingStatus();
});
