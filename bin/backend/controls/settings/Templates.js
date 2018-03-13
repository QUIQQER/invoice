/**
 * @module package/quiqqer/invoice/bin/backend/controls/settings/Templates
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/invoice/bin/backend/controls/settings/Templates', [

    'qui/QUI',
    'qui/controls/Control',
    'package/quiqqer/invoice/bin/Invoices'

], function (QUI, QUIControl, Invoices) {
    "use strict";

    return new Class({
        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/settings/Templates',

        Binds: [
            '$onChange',
            '$onImport'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Elm    = null;
            this.$Select = null;
            this.$Input  = null;

            this.addEvents({
                onImport: this.$onImport,
                onChange: this.$onChange
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self = this;

            this.$Input = this.getElm();

            this.$Select = new Element('select', {
                'class': 'field-container-field',
                events : {
                    onChange: this.$onChange
                }
            }).inject(this.$Input, 'after');

            Invoices.getTemplates().then(function (templates) {
                for (var i = 0, len = templates.length; i < len; i++) {
                    new Element('option', {
                        value: templates[i].name,
                        html : templates[i].title
                    }).inject(self.$Select);
                }

                self.$onChange();
            });
        },

        /**
         * event : on select change
         */
        $onChange: function () {
            this.$Input.value = this.$Select.value;
        }
    });
});