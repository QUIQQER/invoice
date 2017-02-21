/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.UserData
 *
 *
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.UserData', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'controls/users/address/Display',
    'controls/users/search/Window',
    'Users',
    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.UserData.html'

], function (QUI, QUIControl, QUIButton, AddressDisplay, UserSearch, Users, QUILocale, QUIAjax,
             Mustache, template) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.UserData',

        Binds: [
            'toggleExtras',
            '$onImport'
        ],

        options: {
            userId: false,
            addressId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onImport
            });

            this.$CustomerSelect = null;

            this.$Extras = null;
            this.$Company = null;
            this.$Street = null;
            this.$Zip = null;
            this.$City = null;
            this.$Table = null;
            this.$AddressRow = null;
            this.$AddressSelect = null;

            this.$rows = [];
            this.$extrasAreOpen = false;

        },

        /**
         * Create the DOMNoe Element
         * @returns {*}
         */
        create: function () {
            var self = this;

            this.$Elm = new Element('div', {
                html: Mustache.render(template)
            });

            this.$Extras = this.$Elm.getElement('.quiqqer-invoice-backend-temporaryInvoice-data-address-opener');
            this.$Extras.addEvent('click', this.toggleExtras);

            this.$Company = this.$Elm.getElement('[name="company"]');
            this.$Street = this.$Elm.getElement('[name="street"]');
            this.$Zip = this.$Elm.getElement('[name="zip"]');
            this.$City = this.$Elm.getElement('[name="city"]');

            this.$Table = this.$Elm.getElement('.invoice-data-customer');
            this.$rows = this.$Table.getElements('.closable');
            this.$AddressRow = this.$Table.getElement('.address-row');
            this.$AddressSelect = this.$Table.getElement('[name="address"]');

            this.$AddressSelect.addEvent('change', function () {
                self.setAddressId(this.value);
            });

            return this.$Elm;
        },

        /**
         * Set the user id
         *
         * @param userId
         */
        setUserId: function (userId) {
            this.setAttribute('userId', userId);

            if (!this.$Elm) {
                return;
            }

            if (userId === '' || !userId) {
                this.$Company.set('value', '');
                this.$Street.set('value', '');
                this.$Zip.set('value', '');
                this.$City.set('value', '');
                return;
            }

            var User = Users.get(userId);

            User.load().then(function (User) {
                return User.getAddressList();
            }).then(function (addresses) {
                if (!addresses.length) {
                    this.$AddressRow.setStyle('display', 'none');
                    return;
                }

                // @todo rechnungsadresse auswählen
                this.$AddressRow.setStyle('display', null);

                for (var i = 0, len = addresses.length; i < len; i++) {
                    new Element('option', {
                        value: addresses[i].id,
                        html: addresses[i].text
                    }).inject(this.$AddressSelect);
                }

                this.$AddressSelect.value = addresses[0].id;
                this.$AddressSelect.fireEvent('change');
            }.bind(this));
        },

        /**
         * Set a address to the user data
         *
         * @param {String|Number} addressId
         */
        setAddressId: function (addressId) {
            var self = this;

            return new Promise(function (resolve) {
                QUIAjax.get('ajax_users_address_get', function (address) {
                    self.$Company.set('value', address.company);
                    self.$Street.set('value', address.street_no);
                    self.$Zip.set('value', address.zip);
                    self.$City.set('value', address.city);

                    self.setAttribute('addressId', addressId);

                    resolve(address);
                }, {
                    uid: self.getAttribute('userId'),
                    aid: addressId
                });
            });
        },

        /**
         * Events
         */

        /**
         * event on import
         */
        $onImport: function () {
            QUI.parse(this.$Elm).then(function () {
                var self = this;

                this.$CustomerSelect = QUI.Controls.getById(
                    this.$Elm.getElement('[name="customer"]').get('data-quiid')
                );

                this.$CustomerSelect.addEvents({
                    change: function (Control) {
                        self.setUserId(Control.getValue());
                    }
                });

                if (this.getAttribute('userId')) {
                    this.$CustomerSelect.setValue(this.getAttribute('userId'));
                }
            }.bind(this));
        },

        /**
         * Extras
         */

        /**
         * Toggle the extra view
         *
         * @returns {Promise}
         */
        toggleExtras: function () {
            if (this.$extrasAreOpen) {
                return this.closeExtras();
            }

            return this.openExtras();
        },

        /**
         * Open the extra data
         *
         * @return {Promise}
         */
        openExtras: function () {
            var self = this;

            return new Promise(function (resolve) {
                self.$rows.setStyles({
                    height: 0,
                    opacity: 0,
                    overflow: 'hidden',
                    position: 'relative'
                });

                self.$rows.setStyle('display', 'block');

                var height = self.$rows[0].getScrollSize().y;

                moofx(self.$rows).animate({
                    height: height
                }, {
                    duration: 250,
                    callback: function () {
                        self.$rows.setStyles({
                            display: null,
                            height: null,
                            overflow: null,
                            position: null
                        });

                        moofx(self.$rows).animate({
                            opacity: 1
                        }, {
                            duration: 250,
                            callback: function () {
                                self.$Extras.set({
                                    html: '<span class="fa fa-chevron-up"></span> Erweiterte Ansicht schließen'
                                });

                                self.$extrasAreOpen = true;
                                resolve();
                            }
                        });
                    }
                });
            });
        },

        /**
         * Close the extra data
         *
         * @return {Promise}
         */
        closeExtras: function () {
            var self = this;

            return new Promise(function (resolve) {
                moofx(self.$rows).animate({
                    opacity: 0
                }, {
                    duration: 250,
                    callback: function () {
                        self.$rows.setStyle('display', 'none');
                        self.$extrasAreOpen = false;

                        self.$Extras.set({
                            html: '<span class="fa fa-chevron-right"></span> Erweiterte Ansicht öffnen'
                        });

                        resolve();
                    }
                });
            });
        }
    });
});
