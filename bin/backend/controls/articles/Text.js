/**
 * @module package/quiqqer/invoice/bin/backend/controls/articles/Text
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/invoice/bin/backend/controls/articles/Text', [

    'package/quiqqer/invoice/bin/backend/controls/articles/Text'

], function (Text) {
    "use strict";

    // quiqqer/invoice#66
    // is required because invoice per php defines the JS controls and is backward compatible

    return new Class({
        Extends: Text,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/articles/Text'
    });
});
