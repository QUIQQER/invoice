/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/DeliveryAddress
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onLoaded [this] - Fires if Control finished loading
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/DeliveryAddress', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/windows/Confirm',
    'package/quiqqer/countries/bin/Countries',
    'Users',
    'Locale'

], function (QUI, QUIControl, QUIConfirm, Countries, Users, QUILocale) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/panels/DeliveryAddress',

        Binds: [
            '$onImport',
            '$checkBoxChange',
            '$onSelectChange',
            'isLoaded'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Addresses = null;
            this.$Company   = null;
            this.$Street    = null;
            this.$ZIP       = null;
            this.$City      = null;
            this.$Country   = null;

            this.checked = false;
            this.$loaded = false;
            this.$userId = this.getAttribute('userId');

            this.addEvents({
                onImport      : this.$onImport,
                onSetAttribute: this.$onSetAttribute
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            var self = this,
                Elm  = this.getElm();

            // changeable
            this.$Checked = Elm.getElement('[name="differentDeliveryAddress"]');
            this.$Checked.addEvent('change', this.$checkBoxChange);

            // address stuff
            this.$addressId = false;
            this.$Company   = Elm.getElement('[name="delivery-company"]');
            this.$Street    = Elm.getElement('[name="delivery-street_no"]');
            this.$ZIP       = Elm.getElement('[name="delivery-zip"]');
            this.$City      = Elm.getElement('[name="delivery-city"]');
            this.$Country   = Elm.getElement('[name="delivery-country"]');

            this.$Addresses = Elm.getElement('[name="delivery-addresses"]');
            this.$Addresses.addEvent('change', this.$onSelectChange);

            this.$Company.disabled = false;
            this.$Street.disabled  = false;
            this.$ZIP.disabled     = false;
            this.$City.disabled    = false;

            var Panel = QUI.Controls.getById(
                this.getElm().getParent('.qui-panel').get('data-quiid')
            );

            this.$Customer = QUI.Controls.getById(
                Panel.getElm().getElement('[name="customer"]').get('data-quiid')
            );

            if (this.$Customer) {
                this.$userId = this.$Customer.getValue();
            }

            Countries.getCountries().then(function (result) {
                new Element('option', {
                    value: '',
                    html : ''
                }).inject(self.$Country);

                for (var code in result) {
                    if (!result.hasOwnProperty(code)) {
                        continue;
                    }

                    new Element('option', {
                        value: code,
                        html : result[code]
                    }).inject(self.$Country);
                }

                if (self.getAttribute('country')) {
                    self.$Country.value = self.getAttribute('country');
                }

                self.$Country.disabled = false;
                self.$loaded           = true;

                self.fireEvent('loaded', [self]);
            });
        },

        /**
         * Return the current value
         *
         * @return {{company: *, street: (*|Document.street_no|Document.address.street_no), zip: *, city: (string|string|*)}}
         */
        getValue: function () {
            return {
                uid      : this.$userId,
                id       : this.$addressId,
                company  : this.$Company.value,
                street_no: this.$Street.value,
                zip      : this.$ZIP.value,
                city     : this.$City.value,
                country  : this.$Country.value
            };
        },

        /**
         * Set values
         *
         * @param {Object} value
         */
        setValue: function (value) {
            if (typeOf(value) !== 'object') {
                return;
            }

            if ("id" in value) {
                this.$addressId = value.id;
            }

            if ("company" in value) {
                this.$Company.value = value.company;
            }

            if ("street_no" in value) {
                this.$Street.value = value.street_no;
            }

            if ("zip" in value) {
                this.$ZIP.value = value.zip;
            }

            if ("city" in value) {
                this.$City.value = value.city;
            }

            if ("country" in value) {
                if (this.$loaded) {
                    this.$Country.value = value.country;
                } else {
                    this.setAttribute('country', value.country);
                }
            }

            if ("uid" in value) {
                this.$userId = value.uid;
                //this.loadAddresses().catch(function () {
                //    this.$Addresses.disabled = true;
                //}.bind(this));
            }

            this.$checkBoxChange();
        },

        /**
         * Return the selected user
         *
         * @return {Promise}
         */
        getUser: function () {
            if (!this.$userId) {
                return Promise.reject('No User-ID');
            }

            var User = Users.get(this.$userId);

            if (User.isLoaded()) {
                return Promise.resolve(User);
            }

            return User.load();
        },

        /**
         * Refresh the address list
         *
         * @return {Promise}
         */
        refresh: function () {
            var self = this;

            this.$Addresses.set('html', '');

            if (!this.$userId) {
                return Promise.reject();
            }

            return this.loadAddresses().then(function () {
                self.$onSelectChange();
            }).catch(function () {
                self.$Addresses.disabled = true;
            });
        },

        /**
         * Load the addresses
         */
        loadAddresses: function () {
            var self = this;

            this.$Addresses.set('html', '');
            this.$Addresses.disabled = true;

            if (!this.$userId) {
                return Promise.reject();
            }

            return this.getUser().then(function (User) {
                return User.getAddressList();
            }).then(function (addresses) {
                self.$Addresses.set('html', '');

                new Element('option', {
                    value       : '',
                    html        : '',
                    'data-value': ''
                }).inject(self.$Addresses);

                for (var i = 0, len = addresses.length; i < len; i++) {
                    new Element('option', {
                        value       : addresses[i].id,
                        html        : addresses[i].text,
                        'data-value': JSON.encode(addresses[i])
                    }).inject(self.$Addresses);
                }

                if (self.$addressId) {
                    self.$Addresses.value = self.$addressId;
                }

                self.$Addresses.disabled = false;
            }).catch(function (err) {
                console.error(err);
            });
        },

        /**
         * event : on select change
         */
        $onSelectChange: function () {
            var Select = this.$Addresses;

            var options = Select.getElements('option').filter(function (Option) {
                return Option.value === Select.value;
            });

            if (!options.length || options[0].get('data-value') === '') {
                this.$addressId = false;
                return;
            }

            var data = JSON.decode(options[0].get('data-value'));

            this.$addressId     = data.id;
            this.$Company.value = data.company;
            this.$Street.value  = data.street_no;
            this.$ZIP.value     = data.zip;
            this.$City.value    = data.city;
            this.$Country.value = data.country;
        },

        /**
         * event: on attribute change
         *
         * @param {String} key
         * @param {String} value
         */
        $onSetAttribute: function (key, value) {
            var self = this;

            if (key === 'userId') {
                this.$userId = value;

                self.refresh().then(function () {
                    self.$Addresses.disabled = false;
                }).catch(function () {
                    self.$Addresses.disabled = true;
                });
            }
        },

        /**
         * event if the check box changes
         *
         * @param event
         */
        $checkBoxChange: function (event) {
            var self      = this,
                Checkbox  = this.getElm().getElement('[name="differentDeliveryAddress"]'),
                closables = this.getElm().getElements('.closable');

            if (event) {
                event.stop();
            }

            if (!Checkbox) {
                return;
            }

            if (!this.$userId) {
                var Panel = QUI.Controls.getById(
                    this.getElm().getParent('.qui-panel').get('data-quiid')
                );

                this.$Customer = QUI.Controls.getById(
                    Panel.getElm().getElement('[name="customer"]').get('data-quiid')
                );

                if (this.$Customer) {
                    this.$userId = this.$Customer.getValue();
                }
            }

            if (!this.$userId) {
                Checkbox.checked = false;

                QUI.getMessageHandler().then(function (MH) {
                    MH.addInformation(
                        QUILocale.get('quiqqer/invoice', 'message.select.customer'),
                        self.$Customer.getElm()
                    );
                });

                return;
            }

            if (Checkbox.checked) {
                closables.setStyle('display', null);
                this.loadAddresses();
                return;
            }

            closables.setStyle('display', 'none');
        },

        /**
         * Check if control has finished loading
         *
         * @return {boolean}
         */
        isLoaded: function() {
            return this.$loaded;
        }
    });
});
