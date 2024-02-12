define('package/quiqqer/invoice/bin/backend/controls/settings/InvoiceAddressRequirement', [

    'qui/QUI',
    'qui/controls/Control'

], function(QUI, QUIControl) {
    'use strict';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/invoice/bin/backend/controls/settings/InvoiceAddressRequirement',

        Binds: [
            '$onImport',
            '$onChange'
        ],

        initialize: function(options) {
            this.parent(options);

            this.$Threshold = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        $onImport: function() {
            this.$Threshold = this.getElm().getParent('table').getElement(
                '[name="invoice.invoiceAddressRequirementThreshold"]'
            );

            this.getElm().addEvent('change', this.$onChange);
            this.$onChange();
        },

        $onChange: function() {
            if (this.getElm().checked) {
                this.$Threshold.disabled = true;
                this.$Threshold.value = '';
            } else {
                this.$Threshold.disabled = false;
                this.$Threshold.value = '200.00';
                this.$Threshold.focus();
            }
        }
    });
});