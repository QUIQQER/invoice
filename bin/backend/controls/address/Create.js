/**
 * @module package/quiqqer/invoice/bin/backend/controls/address/Create
 * @author www.pcsg.de (Henning Leutz)
 *
 * @event onLoad [self]
 */
define('package/quiqqer/invoice/bin/backend/controls/address/Create', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/utils/Form',
    'Mustache',
    'Locale',
    'Ajax',
    'Users',

    'text!package/quiqqer/invoice/bin/backend/controls/address/Create.html',

    'css!package/quiqqer/invoice/bin/backend/controls/address/Create.css'

], function (QUI, QUIControl, QUIFormUtils, Mustache, QUILocale, QUIAjax, Users, template) {
    "use strict";

    var lg  = 'quiqqer/invoice';
    var pkg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/address/Create',

        Binds: [
            '$onInject'
        ],

        options: {
            userId: false
        },

        /**
         * @param options
         */
        initialize: function (options) {
            this.parent(options);

            this.$Elm  = null;
            this.$Form = null;

            this.addEvents({
                onInject: this.$onInject
            });
        },

        /**
         * Create the DOMNode Element
         *
         * @return {Element}
         */
        create: function () {
            this.parent();

            this.$Elm = new Element('div', {
                html: Mustache.render(template, {
                    message       : QUILocale.get(lg, 'invoice.create.address.message'),
                    textData      : QUILocale.get('quiqqer/quiqqer', 'data'),
                    textCompany   : QUILocale.get('quiqqer/system', 'company'),
                    textSalutation: QUILocale.get('quiqqer/system', 'salutation'),
                    textFirstName : QUILocale.get('quiqqer/system', 'firstname'),
                    textLastName  : QUILocale.get('quiqqer/system', 'lastname'),
                    textAddress   : QUILocale.get('quiqqer/system', 'address'),
                    textStreet    : QUILocale.get('quiqqer/system', 'street'),
                    textZIP       : QUILocale.get('quiqqer/system', 'zip'),
                    textCity      : QUILocale.get('quiqqer/system', 'city'),
                    textCountry   : QUILocale.get('quiqqer/system', 'country')

                })
            });

            this.$Elm.getElement('form').addEvent('submit', function (event) {
                event.stop();
            });

            this.$Form = this.$Elm.getElement('form');

            return this.$Elm;
        },

        /**
         * event : on inject
         */
        $onInject: function () {
            var self = this;
            var User = Users.get(this.getAttribute('userId'));

            var UserData = Promise.resolve(User);

            if (!User.isLoaded()) {
                UserData = User.load();
            }

            // load countries and user data
            QUIAjax.get('package_quiqqer_countries_ajax_getCountries', function (countries) {
                var Countries = self.$Form.elements['country'];

                for (var country in countries) {
                    if (!countries.hasOwnProperty(country)) {
                        continue;
                    }

                    new Element('option', {
                        html : countries[country],
                        value: country
                    }).inject(Countries);
                }

                UserData.then(function (User) {
                    var attributes = User.getAttributes();

                    self.$Form.elements.firstname.value = attributes.firstname;
                    self.$Form.elements.lastname.value  = attributes.lastname;
                });

                self.fireEvent('load', [self]);
            }, {
                'package': 'quiqqer/countries',
                lang     : QUILocale.getCurrent()
            });
        },

        /**
         * Create the new invoice address
         *
         * @return {Promise}
         */
        submit: function () {
            var self = this,
                Form = this.$Elm.getElement('form'),
                data = QUIFormUtils.getFormData(Form);

            return new Promise(function (resolve, reject) {
                QUIAjax.post('package_quiqqer_invoice_ajax_address_create', resolve, {
                    package: pkg,
                    userId : self.getAttribute('userId'),
                    data   : JSON.encode(data),
                    onError: reject
                });
            });
        }
    });
});