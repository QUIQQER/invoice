/**
 * @module package/quiqqer/invoice/bin/frontend/controls/order/Address
 */
define('package/quiqqer/invoice/bin/frontend/controls/order/Address', [

    'qui/QUI',
    'qui/controls/Control',
    'Locale',
    'Ajax'

], function (QUI, QUIControl, QUILocale, QUIAjax) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/frontend/controls/order/Address',

        Binds: [
            '$onImport',
            '$addressClick',
            '$editClick',
            '$deleteClick',
            '$addClick',
            '$openContainer',
            '$closeContainer'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var entries = this.getElm().getElements('.quiqqer-order-step-address-list-entry');

            entries.addEvent('click', this.$addressClick);
            entries.setStyles({
                'position': 'relative'
            });

            this.getElm().getElements('[name="create"]').addEvent('click', this.$addClick);
            this.getElm().getElements('[name="delete"]').addEvent('click', this.$deleteClick);
            this.getElm().getElements('[name="edit"]').addEvent('click', this.$editClick);
        },

        /**
         * Refresh the display
         */
        refresh: function () {

        },

        /**
         * Select a address
         *
         * @param event
         */
        $addressClick: function (event) {
            event.stop();

            var Target = event.event.target;

            if (!Target.hasClass('quiqqer-order-step-address-list-entry')) {
                Target = Target.getParent('.quiqqer-order-step-address-list-entry');
            }

            Target.getElement('[name="address_invoice"]').checked = true;
        },

        //region add

        $addClick: function (event) {
            event.stop();

        },

        //region

        //region delete

        /**
         *
         * @param event
         */
        $deleteClick: function (event) {
            event.stop();

            var self   = this,
                Target = event.event.target;

            if (!Target.hasClass('quiqqer-order-step-address-list-entry')) {
                Target = Target.getParent('.quiqqer-order-step-address-list-entry');
            }

            // open delete dialog
            this.$openContainer(Target).then(function (Container) {
                Container.addClass(
                    'quiqqer-order-step-address-container-delete'
                );

                new Element('div', {
                    'class': 'quiqqer-order-step-address-container-delete-message',
                    html   : QUILocale.get(lg, 'dialog.order.delete.invoiceAddress')
                }).inject(Container);

                new Element('button', {
                    'class': 'quiqqer-order-step-address-container-delete-button',
                    html   : QUILocale.get('quiqqer/system', 'delete'),
                    events : {
                        click: function (event) {
                            var Target = event.target;

                            if (Target.nodeName !== 'BUTTON') {
                                Target = Target.getParent('button');
                            }

                            Target.disabled = true;
                            Target.setStyle('width', Target.getSize().x);
                            Target.set('html', '<span class="fa fa-spinner fa-spin"></span>');

                            self.deleteAddress(
                                Target.getParent('.quiqqer-order-step-address-list-entry')
                                      .getElement('[name="address_invoice"]').value
                            ).then(function () {
                                return self.$closeContainer(Container);
                            }).then(function () {
                                self.refresh();
                            }).catch(function () {
                                self.$closeContainer(Container);
                            });
                        }
                    }
                }).inject(Container);
            });
        },

        /**
         * Delete an address
         *
         * @param {Integer} addressId
         */
        deleteAddress: function (addressId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_frontend_address_delete', resolve, {
                    'package': 'quiqqer/invoice',
                    addressId: addressId,
                    onError  : reject
                });
            });

        },

        //endregion

        //region edit

        /**
         *
         * @param event
         */
        $editClick: function (event) {
            event.stop();

            var Target = event.event.target;

            if (Target.nodeName !== 'button') {
                Target = Target.getParent('button');
            }

            console.warn(Target);
        },

        //endregion

        /**
         * Open a div container with effect
         *
         * @return {Promise}
         */
        $openContainer: function (Parent) {
            var self = this;

            var Container = new Element('div', {
                'class': 'quiqqer-order-step-address-container',
                html   : '<div class="quiqqer-order-step-address-container-content"></div>'
            }).inject(Parent);

            new Element('span', {
                'class': 'fa fa-close quiqqer-order-step-address-container-close',
                events : {
                    click: function () {
                        self.$closeContainer(Container);
                    }
                }
            }).inject(Container, 'top');

            return new Promise(function (resolve) {
                moofx(Container).animate({
                    left   : 0,
                    opacity: 1
                }, {
                    duration: 250,
                    callback: function () {
                        resolve(Container);
                    }
                });
            });
        },

        /**
         * Open a div container with effect
         *
         * @param {HTMLDivElement} Container
         * @return {Promise}
         */
        $closeContainer: function (Container) {
            return new Promise(function (resolve) {
                moofx(Container).animate({
                    left   : -50,
                    opacity: 0
                }, {
                    duration: 250,
                    callback: function () {
                        Container.destroy();
                        resolve();
                    }
                });
            });
        }
    });
});