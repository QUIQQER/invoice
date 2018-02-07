/**
 * @module package/quiqqer/invoice/bin/frontend/controls/order/Address
 */
define('package/quiqqer/invoice/bin/frontend/controls/order/Address', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/loader/Loader',
    'Locale',
    'Ajax'

], function (QUI, QUIControl, QUILoader, QUILocale, QUIAjax) {
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
            '$closeContainer',
            '$clickCreateSubmit',
            '$clickEditSave'
        ],

        initialize: function (options) {
            this.parent(options);

            this.Loader = new QUILoader();

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

            this.Loader.inject(this.getElm());
        },

        /**
         * Refresh the display
         */
        refresh: function () {
            var self = this;

            this.Loader.show();

            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_frontend_address_get', function (result) {
                    var Ghost = new Element('div', {
                        html: result
                    });

                    self.getElm().set(
                        'html',
                        Ghost.getElement('.quiqqer-order-step-address').get('html')
                    );

                    self.$onImport();
                    self.Loader.hide();
                    resolve();
                }, {
                    'package': 'quiqqer/invoice',
                    onError  : reject,
                    orderId  : self.getElm().get('data-orderid')
                });
            });
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

        /**
         * event click - create address
         *
         * @param event
         */
        $addClick: function (event) {
            event.stop();

            var self = this;

            // open delete dialog
            this.$openContainer(this.getElm()).then(function (Container) {
                return self.getCreateTemplate().then(function (result) {
                    var Content = Container.getElement('.quiqqer-order-step-address-container-content');

                    new Element('form', {
                        'class': 'quiqqer-order-step-address-container-create',
                        html   : result,
                        events : {
                            submit: function (event) {
                                event.stop();
                            }
                        }
                    }).inject(Content);

                    Content.getElement('[type="submit"]').addEvent('click', self.$clickCreateSubmit);
                });
            });
        },

        /**
         * click event - address creation
         *
         * @param {DOMEvent} event
         */
        $clickCreateSubmit: function (event) {
            event.stop();

            var self      = this,
                Target    = event.target,
                Container = Target.getParent('.quiqqer-order-step-address-container'),
                Form      = Container.getElement('form');

            this.Loader.show();

            require(['qui/utils/Form'], function (FormUtils) {
                var formData = FormUtils.getFormData(Form);

                QUIAjax.post('package_quiqqer_invoice_ajax_frontend_address_create', function () {
                    self.$closeContainer(Container);
                    self.refresh();
                }, {
                    'package': 'quiqqer/invoice',
                    data     : JSON.encode(formData)
                });
            });
        },

        /**
         * Return the address create template
         *
         * @return {Promise}
         */
        getCreateTemplate: function () {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_frontend_address_getCreate', resolve, {
                    'package': 'quiqqer/invoice',
                    onError  : reject
                });
            });
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

            var Address = Target;

            // open delete dialog
            this.$openContainer(Target).then(function (Container) {
                Container.addClass(
                    'quiqqer-order-step-address-container-delete'
                );

                var Content = Container.getElement('.quiqqer-order-step-address-container-content');

                new Element('div', {
                    'class': 'quiqqer-order-step-address-container-delete-message',
                    html   : QUILocale.get(lg, 'dialog.order.delete.invoiceAddress')
                }).inject(Content);

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

                            self.Loader.show();

                            self.deleteAddress(
                                Target.getParent('.quiqqer-order-step-address-list-entry')
                                      .getElement('[name="address_invoice"]').value
                            ).then(function () {
                                return self.$closeContainer(Container);
                            }).then(function () {
                                Address.setStyles({
                                    overflow: 'hidden',
                                    height  : Address.getSize().y
                                });

                                moofx(Address).animate({
                                    height : 0,
                                    opacity: 0
                                }, {
                                    duration: 250,
                                    callback: function () {
                                        self.refresh();
                                    }
                                });
                            }).catch(function () {
                                self.$closeContainer(Container);
                                self.Loader.hide();
                            });
                        }
                    }
                }).inject(Content);
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

            var self   = this,
                Target = event.target;

            if (Target.nodeName !== 'button') {
                Target = Target.getParent('button');
            }

            var addressId = Target.getParent('.quiqqer-order-step-address-list-entry')
                                  .getElement('[name="address_invoice"]').value;

            this.$openContainer(this.getElm()).then(function (Container) {
                return self.getEditTemplate(addressId).then(function (result) {
                    Container.addClass(
                        'quiqqer-order-step-address-container-edit'
                    );

                    var Content = Container.getElement(
                        '.quiqqer-order-step-address-container-content'
                    );

                    new Element('form', {
                        'class': 'quiqqer-order-step-address-container-edit-message',
                        html   : result
                    }).inject(Content);

                    Content.getElement('[name="editSave"]').addEvent('click', self.$clickEditSave);
                });
            });
        },

        /**
         * event : click -> save the address edit
         *
         * @param {DOMEvent} event
         */
        $clickEditSave: function (event) {
            event.stop();

            var self      = this,
                Target    = event.target,
                Container = Target.getParent('.quiqqer-order-step-address-container'),
                Form      = Container.getElement('form');

            this.Loader.show();

            require(['qui/utils/Form'], function (FormUtils) {
                var formData = FormUtils.getFormData(Form);
console.warn(formData);
                QUIAjax.post('package_quiqqer_invoice_ajax_frontend_address_edit', function () {
                    self.$closeContainer(Container);
                    self.refresh();
                }, {
                    'package': 'quiqqer/invoice',
                    data     : JSON.encode(formData),
                    addressId: formData.addressId
                });
            });
        },

        /**
         * Return the address create template
         *
         * @return {Promise}
         */
        getEditTemplate: function (addressId) {
            return new Promise(function (resolve, reject) {
                QUIAjax.get('package_quiqqer_invoice_ajax_frontend_address_getEdit', resolve, {
                    'package': 'quiqqer/invoice',
                    onError  : reject,
                    addressId: addressId
                });
            });
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