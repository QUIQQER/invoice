/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.UserSelect
 *
 * User + Adressen Auswahl für den Rechnungs Wizard
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @require controls/users/address/Display
 * @require controls/users/search/Window
 * @require Users
 * @require Locale
 * @require css!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.UserSelect.css
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.UserSelect', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'controls/users/address/Display',
    'controls/users/search/Window',
    'Users',
    'Locale',

    'css!package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.UserSelect.css'

], function (QUI, QUIControl, QUIButton, AddressDisplay, UserSearch, Users, QUILocale) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice.UserSelect',

        Binds: [
            'openUserSearch'
        ],

        options: {
            userId   : false,
            addressId: false
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onInject: this.refresh
            });

            this.$Container = null;
        },

        /**
         * Refresh the display
         *
         * @return {Promise}
         */
        refresh: function () {
            var self = this;

            if (!this.getAttribute('userId')) {
                return this.$displayUserSelect();
            }

            if (!this.getAttribute('addressId')) {
                return this.$displayAddressSelect().catch(function () {
                    console.error(arguments);

                    self.getAttribute('userId', false);
                    self.getAttribute('addressId', false);

                    return self.refresh();
                });
            }

            return this.$displayAddress().catch(function () {
                console.error(arguments);

                self.getAttribute('userId', false);
                self.getAttribute('addressId', false);

                return self.refresh();
            });
        },

        /**
         * Create the DOMNode Element
         * @returns {Promise}
         */
        create: function () {
            this.$Elm = this.parent();
            this.$Elm.addClass('quiqqer-invoice-ti-userSelect');

            this.$Container = new Element('div', {
                'class': 'quiqqer-invoice-ti-userSelect-container'
            }).inject(this.getElm());

            return this.$Elm;
        },

        /**
         * Display User Select
         *
         * @returns {Promise}
         */
        $displayUserSelect: function () {
            var self = this;

            return this.$hideContainer().then(function () {

                self.$Container.set({
                    html: '<div class="fa fa-user"></div>' +
                    '<div>Kunden auswählen</div>'
                });

                var click = function () {
                    self.$hideContainer().then(function () {
                        self.openUserSearch();
                        self.$Container.removeEvents('click', click);

                        self.$Container.removeClass('quiqqer-invoice-ti-userSelect-user');
                    });
                };

                self.$Container.addClass('quiqqer-invoice-ti-userSelect-user');
                self.$Container.addEvent('click', click);

                return self.$showContainer();
            });
        },

        /**
         * Displays user address selection
         *
         * @returns {Promise}
         */
        $displayAddressSelect: function () {
            var self = this;

            return this.$hideContainer().then(function () {
                return self.getUser();

            }).then(function (User) {
                return User.getAddressList();

            }).then(function (address) {
                if (!address.length) {
                    return Promise.reject('User has no address');
                }

                if (address.length === 1) {
                    self.setAttribute('addressId', address[0].id);

                    return self.$displayAddress();
                }

                self.$Container.addClass('quiqqer-invoice-ti-userSelect-addresses');

                var Select = new Element('select').inject(self.$Container);

                for (var i = 0, len = address.length; i < len; i++) {
                    new Element('option', {
                        html : address[i].text,
                        value: address[i].id
                    }).inject(Select);
                }

                new QUIButton({
                    text  : QUILocale.get('quiqqer/system', 'accept'),
                    styles: {
                        'float': 'none'
                    },
                    events: {
                        onClick: function () {
                            self.setAttribute('addressId', Select.value);
                            self.$hideContainer().then(function () {
                                self.refresh();
                            });
                        }
                    }
                }).inject(self.$Container);

                return self.$showContainer();
            });
        },

        /**
         * Display the user address
         *
         * @returns {Promise}
         */
        $displayAddress: function () {
            var self = this;

            return this.$hideContainer().then(function () {
                return new Promise(function (resolve, reject) {
                    require(['controls/users/address/Display'], function (Display) {

                        var Address = new Display({
                            addressId: self.getAttribute('addressId'),
                            userId   : self.getAttribute('userId'),
                            events   : {
                                onLoadError: reject,
                                onLoad     : function () {
                                    var scrollSize = self.$Container.getScrollSize();

                                    moofx(self.getElm()).animate({
                                        height: scrollSize.y
                                    }, {
                                        duration: 200,
                                        callback: function () {
                                            self.$showContainer().then(resolve);
                                        }
                                    });
                                },
                                onClick    : function () {
                                    self.getElm().setStyle('height', null);
                                    self.openUserSearch();
                                }
                            }
                        }).inject(self.$Container);

                        Address.getElm().setStyle('cursor', 'pointer');

                    }, reject);
                });
            });
        },

        /**
         * Return the current values
         *
         * @return {Object}
         */
        getValue: function () {
            return {
                userId   : this.getAttribute('userId'),
                addressId: this.getAttribute('addressId')
            };
        },

        /**
         * Return the selected user
         *
         * @return {Promise}
         */
        getUser: function () {
            if (!this.getAttribute('userId')) {
                return Promise.reject('No User-ID');
            }

            var User = Users.get(this.getAttribute('userId'));

            if (User.isLoaded()) {
                return Promise.resolve(User);
            }

            return User.load();
        },

        /**
         * Open the user search and set the user to the select
         */
        openUserSearch: function () {
            var self = this;

            new UserSearch({
                events: {
                    onSubmit: function (Win, userIds) {
                        self.setAttribute('userId', parseInt(userIds[0].id));
                        self.setAttribute('addressId', false);
                        self.refresh();
                    },
                    onCancel: function () {
                        self.refresh();
                    }
                }
            }).open();
        },

        /**
         * Hide the container and clears it
         *
         * @return {Promise}
         */
        $hideContainer: function () {
            var Container = this.$Container;

            return new Promise(function (resolve) {
                moofx(Container).animate({
                    opacity: 0,
                    top    : -50
                }, {
                    duration: 200,
                    callback: function () {
                        Container.set('html', '');
                        Container.removeClass('quiqqer-invoice-ti-userSelect-addresses');
                        Container.removeClass('quiqqer-invoice-ti-userSelect-user');
                        resolve();
                    }
                });
            });
        },

        /**
         * Shows the container
         *
         * @return {Promise}
         */
        $showContainer: function () {
            var Container = this.$Container;

            return new Promise(function (resolve) {
                moofx(Container).animate({
                    opacity: 1,
                    top    : 0
                }, {
                    duration: 200,
                    callback: resolve
                });
            });
        }
    });
});