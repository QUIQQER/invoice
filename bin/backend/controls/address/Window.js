/**
 * @module package/quiqqer/invoice/bin/backend/controls/address/Window
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/invoice/bin/backend/controls/address/Window', [

    'qui/QUI',
    'qui/controls/windows/Popup',
    'qui/controls/buttons/Button',
    'package/quiqqer/invoice/bin/backend/controls/address/Create',
    'Locale'

], function (QUI, QUIPopup, QUIButton, Create, QUILocale) {
    "use strict";

    var lg  = 'quiqqer/invoice';
    var pkg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIPopup,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/address/Window',

        Binds: [
            'submit',
            '$onOpen'
        ],

        options: {
            userId: null
        },

        initialize: function (options) {
            this.setAttributes({
                icon       : 'fa fa-address-card-o',
                title      : QUILocale.get(lg, 'dialog.create.address.title'),
                maxHeight  : 600,
                maxWidth   : 800,
                closeButton: false
            });

            this.parent(options);

            this.$Create = null;

            this.addEvents({
                onOpen: this.$onOpen
            });
        },

        /**
         * event: on open
         */
        $onOpen: function () {
            this.getContent().set('html', '');

            this.addButton(
                new QUIButton({
                    textimage: 'fa fa-plus',
                    text     : 'Addresse anlegen', // #locale
                    events   : {
                        onClick: this.submit
                    }
                })
            );

            this.$Create = new Create({
                userId: this.getAttribute('userId')
            }).inject(this.getContent());
        },

        /**
         * Submit the address and create it
         *
         * @return {Promise}
         */
        submit: function () {
            var self = this;

            return new Promise(function (resolve, reject) {
                self.Loader.show();
                self.$Create.submit().then(function (addressId, addressData) {
                    self.fireEvent('submit', [self, addressId, addressData]);
                    self.close();
                    resolve(addressId, addressData);
                }).catch(reject);
            });
        }
    });
});
