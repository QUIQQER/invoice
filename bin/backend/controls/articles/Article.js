/**
 * @module package/quiqqer/invoice/bin/backend/controls/articles/Article
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/invoice/bin/backend/controls/articles/Article', [
    'package/quiqqer/erp/bin/backend/controls/articles/Article'
], function (Article) {
    "use strict";

    // quiqqer/invoice#66
    // is required because invoice per php defines the JS controls and is backward compatible

    return new Class({
        Extends: Article,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/articles/Article'
    });
});
