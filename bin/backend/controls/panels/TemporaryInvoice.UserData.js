/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.UserData
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @require controls/users/address/Display
 * @require controls/users/search/Window
 * @require Users
 * @require Locale
 * @require Ajax
 * @require Mustache
 * @require text!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.UserData.html
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.UserData', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/windows/Popup',
    'controls/users/address/Display',
    'controls/users/search/Window',
    'Users',
    'Locale',
    'Ajax',
    'Mustache',

    'text!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.UserData.html'

], function (QUI, QUIControl, QUIButton, QUIPopup,
             AddressDisplay, UserSearch, Users, QUILocale, QUIAjax,
             Mustache, template) {
    "use strict";

    var lg  = 'quiqqer/invoice';
    var pkg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.UserData',

        Binds: [
            'toggleExtras',
            '$onImport'
        ],

        options: {
            userId   : false,
            addressId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onImport
            });

            this.$CustomerSelect = null;

            this.$Extras        = null;
            this.$Company       = null;
            this.$Street        = null;
            this.$Zip           = null;
            this.$City          = null;
            this.$Table         = null;
            this.$AddressRow    = null;
            this.$AddressSelect = null;

            this.$rows          = [];
            this.$extrasAreOpen = false;
            this.$oldUserId     = false;

        },

        /**
         * Create the DOMNoe Element
         * @returns {*}
         */
        create: function () {
            var self = this;

            this.$Elm = new Element('div', {
                html: Mustache.render(template, {
                    textTitle   : QUILocale.get(lg, 'cutomerData'),
                    textCustomer: QUILocale.get(lg, 'customer'),
                    textAddress : QUILocale.get(lg, 'address'),
                    textCompany : QUILocale.get(lg, 'company'),
                    textStreet  : QUILocale.get(lg, 'street'),
                    textZip     : QUILocale.get(lg, 'zip'),
                    textCity    : QUILocale.get(lg, 'city'),
                    textExtra   : QUILocale.get(lg, 'invoice.temporary.extend.view.open')
                })
            });

            this.$Extras = this.$Elm.getElement('.quiqqer-invoice-backend-temporaryInvoice-data-address-opener');
            this.$Extras.addEvent('click', this.toggleExtras);

            this.$Company = this.$Elm.getElement('[name="company"]');
            this.$Street  = this.$Elm.getElement('[name="street"]');
            this.$Zip     = this.$Elm.getElement('[name="zip"]');
            this.$City    = this.$Elm.getElement('[name="city"]');

            this.$Table         = this.$Elm.getElement('.invoice-data-customer');
            this.$rows          = this.$Table.getElements('.closable');
            this.$AddressRow    = this.$Table.getElement('.address-row');
            this.$AddressSelect = this.$Table.getElement('[name="address"]');

            this.$AddressSelect.addEvent('change', function () {
                self.setAddressId(this.value);
            });

            return this.$Elm;
        },

        /**
         * Return the data value
         *
         * @returns {{userId: *, addressId: *}}
         */
        getValue: function () {
            return {
                userId   : this.getAttribute('userId'),
                addressId: this.getAttribute('addressId')
            };
        },

        /**
         * Set the complete data values
         *
         * @param {String|Number} userId
         * @param {String|Number} addressId
         */
        setValue: function (userId, addressId) {
            this.setAttribute('userId', userId);
            this.setAttribute('addressId', addressId);

            return this.refresh();
        },

        /**
         * Refresh the display
         *
         * @return {Promise}
         */
        refresh: function () {
            if (!this.$Elm || !this.$AddressSelect) {
                return Promise.resolve();
            }

            var self   = this,
                userId = this.getAttribute('userId');

            if (!userId || userId === '') {
                this.$Company.set('value', '');
                this.$Street.set('value', '');
                this.$Zip.set('value', '');
                this.$City.set('value', '');

                this.$AddressRow.setStyle('display', 'none');

                return Promise.resolve();
            }

            return this.$getUser().then(function (User) {
                if (!User) {
                    return [];
                }

                return self.getAddressList(User);
            }).then(function (addresses) {
                console.info(addresses);

                self.$AddressSelect.set('html', '');

                if (!addresses.length) {
                    self.$AddressRow.setStyle('display', 'none');
                    return;
                }

                // @todo rechnungsadresse auswählen wenn keine value gesetzt ist

                self.$AddressRow.setStyle('display', null);

                for (var i = 0, len = addresses.length; i < len; i++) {
                    new Element('option', {
                        value: addresses[i].id,
                        html : addresses[i].text
                    }).inject(self.$AddressSelect);
                }

                var addressId = self.getAttribute('addressId');

                self.$AddressSelect.value = addressId || addresses[0].id;
            });
        },

        /**
         * Set the user id
         *
         * @param userId
         * @return {Promise}
         */
        setUserId: function (userId) {
            var self = this;

            this.$oldUserId = this.getAttribute('userId');

            this.setAttribute('userId', userId);

            if (!this.$Elm) {
                return Promise.resolve();
            }

            return this.refresh().then(function () {
                self.fireEvent('change', [self]);
                self.$AddressSelect.fireEvent('change');
            }, function () {
                self.setAttribute('userId', self.$oldUserId);
                self.$CustomerSelect.addItem(self.$oldUserId);
            });
        },

        /**
         * Set a address to the user data
         *
         * @param {String|Number} addressId
         * @return {Promise}
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
                    self.fireEvent('change', [self]);

                    resolve(address);
                }, {
                    uid: self.getAttribute('userId'),
                    aid: addressId
                });
            });
        },

        /**
         * Return the loaded user object
         *
         * @returns {Promise}
         */
        $getUser: function () {
            var userId = this.getAttribute('userId');

            if (!userId || userId === '') {
                return Promise.reject();
            }

            var User = Users.get(userId);

            if (!User.isLoaded()) {
                return Promise.resolve(User);
            }

            return User.load();
        },

        /**
         *
         * @param User
         * @return {Promise}
         */
        getAddressList: function (User) {
            var self = this;

            return new Promise(function (resolve, reject) {
                return User.getAddressList().then(function (result) {
                    if (result.length) {
                        return resolve(result);
                    }

                    // create new address
                    return self.openCreateAddressDialog(User).then(function () {
                        return User.getAddressList().then(resolve);
                    }).catch(reject);
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
                    this.$CustomerSelect.addItem(this.getAttribute('userId'));
                }
            }.bind(this));
        },

        /**
         * Address creation
         */

        /**
         *
         * @param User
         * @return {Promise}
         */
        openCreateAddressDialog: function (User) {
            return new Promise(function (resolve, reject) {
                require([
                    'package/quiqqer/invoice/bin/backend/controls/address/Window'
                ], function (Win) {
                    new Win({
                        userId: User.getId(),
                        events: {
                            onSubmit: resolve,
                            onCancel: function () {
                                reject('No User selected');
                            }
                        }
                    }).open();
                });
            });
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
                    height  : 0,
                    opacity : 0,
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
                            display : null,
                            height  : null,
                            overflow: null,
                            position: null
                        });

                        moofx(self.$rows).animate({
                            opacity: 1
                        }, {
                            duration: 250,
                            callback: function () {
                                self.$Extras.set({
                                    html: '<span class="fa fa-chevron-up"></span> ' +
                                    QUILocale.get(lg, 'invoice.temporary.extend.view.close')
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
                            html: '<span class="fa fa-chevron-right"></span> ' +
                            QUILocale.get(lg, 'invoice.temporary.extend.view.open')
                        });

                        resolve();
                    }
                });
            });
        }
    });
});
