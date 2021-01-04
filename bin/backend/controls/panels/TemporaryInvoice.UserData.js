/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.UserData
 * @author www.pcsg.de (Henning Leutz)
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

    var lg     = 'quiqqer/invoice';
    var fields = ['company', 'street_no', 'zip', 'city'];

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.UserData',

        Binds: [
            'toggleExtras',
            'editCustomer',
            '$onInject'
        ],

        options: {
            userId   : false,
            addressId: false,

            company  : false,
            street_no: false,
            zip      : false,
            city     : false
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.$onInject
            });

            this.$CustomerSelect = null;

            this.$Extras       = null;
            this.$Company      = null;
            this.$Street       = null;
            this.$Zip          = null;
            this.$City         = null;
            this.$Table        = null;
            this.$AddressRow   = null;
            this.$AddressField = null;

            this.$rows          = [];
            this.$extrasAreOpen = false;
            this.$oldUserId     = false;

            this.$loading   = true;
            this.$setValues = false;

            this.$Panel = null;
        },

        /**
         * Create the DOMNoe Element
         * @returns {*}
         */
        create: function () {
            this.$Elm = new Element('div', {
                html: Mustache.render(template, {
                    textTitle   : QUILocale.get(lg, 'cutomerData'),
                    textCustomer: QUILocale.get(lg, 'customer'),
                    textAddress : QUILocale.get(lg, 'address'),
                    textCompany : QUILocale.get(lg, 'company'),
                    textStreet  : QUILocale.get(lg, 'street'),
                    textZip     : QUILocale.get(lg, 'zip'),
                    textCity    : QUILocale.get(lg, 'city'),
                    textExtra   : QUILocale.get(lg, 'invoice.temporary.extend.view.open'),
                    textUserEdit: QUILocale.get(lg, 'invoice.temporary.extend.userEdit')
                })
            });

            this.$Extras = this.$Elm.getElement('.quiqqer-invoice-backend-temporaryInvoice-data-address-opener');
            this.$Extras.addEvent('click', this.toggleExtras);

            this.$CustomerEdit = this.$Elm.getElement('.quiqqer-invoice-backend-temporaryInvoice-data-address-userEdit');
            this.$CustomerEdit.addEvent('click', this.editCustomer);

            this.$Company = this.$Elm.getElement('[name="company"]');
            this.$Street  = this.$Elm.getElement('[name="street_no"]');
            this.$Zip     = this.$Elm.getElement('[name="zip"]');
            this.$City    = this.$Elm.getElement('[name="city"]');

            this.$Table          = this.$Elm.getElement('.invoice-data-customer');
            this.$rows           = this.$Table.getElements('.closable');
            this.$AddressRow     = this.$Table.getElement('.address-row');
            this.$AddressField   = this.$Table.getElement('[name="address"]');
            this.$AddressDisplay = null;
            this.$triggerChange  = null;


            this.$AddressField.type = 'hidden';

            this.$AddressDisplay = new Element('input', {
                'class' : 'field-container-field',
                disabled: true
            }).inject(this.$AddressField, 'after');

            return this.$Elm;
        },

        /**
         * Return the data value
         *
         * @returns {{userId: *, addressId: *}}
         */
        getValue: function () {
            var result = {
                userId   : this.getAttribute('userId'),
                addressId: this.getAttribute('addressId')
            };

            fields.forEach(function (field) {
                if (this.getAttribute(field)) {
                    result[field] = this.getAttribute(field);
                }
            }.bind(this));

            return result;
        },

        /**
         * Set the complete data values
         *
         * @param {Object} data
         */
        setValue: function (data) {
            var self = this;
            
            this.setAttribute('userId', data.userId);
            this.setAttribute('addressId', data.addressId);

            fields.forEach(function (field) {
                if (typeof data[field] !== 'undefined') {
                    self.setAttribute(field, data[field]);
                }
            });

            this.refreshValues();

            if (this.$CustomerSelect &&
                this.$CustomerSelect.getValue() === '' &&
                this.getAttribute('userId')) {

                this.$setValues = true;
                this.$CustomerSelect.addItem(this.getAttribute('userId'));
                this.$AddressRow.setStyle('display', null);
            }
        },

        /**
         * refresh the values
         */
        refreshValues: function () {
            var checkVal = function (val) {
                return !(!val || val === '' || val === 'false');
            };

            var str = [];

            if (checkVal(this.getAttribute('company'))) {
                this.$Company.value = this.getAttribute('company');

                str.push(this.getAttribute('company'));
            }

            if (checkVal(this.getAttribute('street_no'))) {
                this.$Street.value = this.getAttribute('street_no');

                str.push(this.getAttribute('street_no'));
            }

            if (checkVal(this.getAttribute('zip'))) {
                this.$Zip.value = this.getAttribute('zip');

                str.push(this.getAttribute('zip'));
            }

            if (checkVal(this.getAttribute('city'))) {
                this.$City.value = this.getAttribute('city');

                str.push(this.getAttribute('city'));
            }

            str = str.join(', ');

            if (str === '') {
                return;
            }

            this.$AddressDisplay.value = str;
        },

        /**
         * Refresh the display
         *
         * @return {Promise}
         */
        refresh: function () {
            if (!this.$Elm || !this.$AddressField) {
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

            if (this.$CustomerSelect &&
                this.$CustomerSelect.getValue() === '' &&
                this.getAttribute('userId')) {
                this.$CustomerSelect.addItem(this.getAttribute('userId'));
            }

            var TemporaryUser;

            return this.$getUser().then(function (User) {
                TemporaryUser = User;

                if (!User) {
                    return [];
                }

                return self.getAddressList(User);
            }).then(function (addresses) {
                if (!addresses.length) {
                    self.$AddressRow.setStyle('display', 'none');
                    return;
                }

                self.$AddressRow.setStyle('display', null);

                var address   = null;
                var addressId = self.getAttribute('addressId');

                // reset fields
                fields.forEach(function (field) {
                    self.setAttribute(field, '');
                });

                if (addressId || addressId === 0) {
                    var filter = addresses.filter(function (address) {
                        return address.id === addressId;
                    });

                    if (filter.length) {
                        address = filter[0];
                    }
                }

                if (address === null) {
                    address = addresses[0];
                }

                // set fields
                self.setAttributes(address);
                self.$AddressField.value = address.id;

                self.setAttribute('addressId', address.id);
                self.refreshValues();
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

            if (this.$CustomerEdit) {
                this.$CustomerEdit.setStyle('display', 'inline');
            }

            if (!this.$Elm) {
                return Promise.resolve();
            }

            return this.refresh().then(function () {
                self.$fireChange();
                self.$AddressField.fireEvent('change');
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
                if (self.getAttribute('userId') === '') {
                    return Promise.resolve([]);
                }

                QUIAjax.get('ajax_users_address_get', function (address) {
                    self.setAttributes(address);
                    self.refreshValues();

                    self.setAttribute('addressId', addressId);

                    self.$fireChange();

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
                }).catch(function () {
                    resolve([]);
                });
            });
        },

        /**
         * Open the address window
         *
         * @return {Promise}
         */
        openAddressWindow: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                require(['qui/controls/windows/Confirm'], function (QUIWindow) {
                    new QUIWindow({
                        icon     : 'fa fa-address-book-o',
                        title    : QUILocale.get(lg, 'window.customer.address.select.title'),
                        maxHeight: 300,
                        maxWidth : 500,
                        autoclose: false,
                        events   : {
                            onOpen: function (Win) {
                                Win.Loader.show();

                                Win.getContent()
                                   .set('html', QUILocale.get(lg, 'window.customer.address.select.information'));

                                var Select = new Element('select', {
                                    styles: {
                                        display: 'block',
                                        clear  : 'both',
                                        margin : '1rem auto 0',
                                        width  : 300
                                    }
                                }).inject(Win.getContent());

                                self.$getUser().then(function (User) {
                                    return self.getAddressList(User);
                                }).then(function (addresses) {
                                    for (var i = 0, len = addresses.length; i < len; i++) {
                                        new Element('option', {
                                            value: addresses[i].id,
                                            html : addresses[i].text
                                        }).inject(Select);
                                    }

                                    Win.Loader.hide();
                                });
                            },

                            onSubmit: function (Win) {
                                var Select    = Win.getContent().getElement('select');
                                var addressId = parseInt(Select.value);

                                resolve(addressId);
                                Win.close();
                            },

                            onCancel: reject
                        }
                    }).open();
                });
            });
        },

        /**
         * Events
         */

        /**
         * event on import
         */
        $onInject: function () {
            var self           = this;
            var CustomerSelect = this.$Elm.getElement('[name="customer"]');

            this.$Elm.getElement('button').addEvent('click', function () {
                self.openAddressWindow().then(function (addressId) {
                    return self.setAddressId(addressId);
                }).catch(function () {
                    // nothing
                });
            });

            QUI.parse(this.$Elm).then(function () {
                var self = this;

                this.$CustomerSelect = QUI.Controls.getById(
                    CustomerSelect.get('data-quiid')
                );

                this.$CustomerSelect.addEvents({
                    change      : function (Control) {
                        if (self.$setValues) {
                            self.$setValues = false;
                            return;
                        }

                        if (self.$loading === false) {
                            self.setUserId(Control.getValue());
                        }
                    },
                    onRemoveItem: function () {
                        if (self.$CustomerEdit) {
                            self.$CustomerEdit.setStyle('display', 'none');
                        }
                    }
                });

                if (this.getAttribute('userId')) {
                    this.$CustomerEdit.addEvent('onAddItem', function () {
                        self.$loading = false;
                    });

                    this.$CustomerSelect.addItem(this.getAttribute('userId'));
                } else {
                    self.$loading = false;
                }

                this.refreshValues();

                if (this.getElm().getParent('.qui-panel')) {
                    this.$Panel = QUI.Controls.getById(
                        this.getElm().getParent('.qui-panel').get('data-quiid')
                    );
                }
            }.bind(this));
        },

        /**
         * fire the change event
         */
        $fireChange: function () {
            if (this.$triggerChange) {
                clearTimeout(this.$triggerChange);
            }

            this.$triggerChange = (function () {
                this.fireEvent('change', [this]);
            }).delay(100, this);
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
        },

        /**
         * open the user edit panel for the customer
         */
        editCustomer: function () {
            var self = this;

            if (this.$Panel) {
                this.$Panel.Loader.show();
            }

            require(['package/quiqqer/customer/bin/backend/Handler'], function (CustomerHandler) {
                CustomerHandler.openCustomer(self.getAttribute('userId')).then(function () {
                    self.$Panel.Loader.hide();
                });
            });
        }
    });
});
