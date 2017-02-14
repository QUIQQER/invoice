/**
 * @module package/quiqqer/bill/bin/backend/controls/InvoicesPanel
 *
 * @require qui/QUI
 * @require qui/controls/desktop/Panel
 * @require controls/grid/Grid
 */
define('package/quiqqer/bill/bin/backend/controls/InvoicesPanel', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'controls/grid/Grid',
    'package/quiqqer/bill/bin/Invoices'

], function (QUI, QUIPanel, Grid, Invoices) {
    "use strict";

    return new Class({

        Extends: QUIPanel,
        Type: 'package/quiqqer/bill/bin/backend/controls/InvoicesPanel',

        initialize: function (options) {
            this.setAttributes({
                icon: 'fa fa-money'
            });

            this.parent(options);

            this.$Grid = null;

            this.addEvents({
                onCreate: this.$onCreate,
                onResize: this.$onResize
            });
        },

        refresh: function () {
            this.Loader.show();

            Invoices.getList().then(function (result) {
                this.$Grid.setData(result);
            }.bind(this));
        },

        /**
         * event : on create
         */
        $onCreate: function () {
            // Buttons

            // Grid
            var Container = new Element('div').inject(
                this.getContent()
            );

            this.$Grid = new Grid(Container, {
                columnModel: [{}]
            });
        },
        /**
         * event : on resize
         */
        $onResize: function () {
            if (!this.$Grid) {
                return;
            }

            var Body = this.getContent();

            if (!Body) {
                return;
            }

            var size = Body.getSize();

            this.$Grid.setHeight(size.y - 40);
            this.$Grid.setWidth(size.x - 40);
        }
    });
});