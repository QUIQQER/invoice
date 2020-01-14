/**
 * @module package/quiqqer/invoice/bin/backend/controls/settings/ProcessingSelect
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/invoice/bin/backend/controls/settings/ProcessingSelect', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Select',
    'package/quiqqer/invoice/bin/backend/classes/ProcessingStatus',

    'css!package/quiqqer/invoice/bin/backend/controls/settings/ProcessingSelect.css'

], function (QUI, QUIControl, QUISelect, ProcessingStatus) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/settings/ProcessingSelect',

        Binds: [
            '$onImport',
            '$onInject'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Color  = null;
            this.$Select = null;
            this.$Input  = null;
            this.$Elm    = null;

            this.addEvents({
                onInject: this.$onInject,
                onImport: this.$onImport
            });
        },

        /**
         * create the domnode element
         */
        create: function () {
            this.$Elm = new Element('div', {
                'class': 'invoice-status-select'
            });

            return this.$Elm;
        },

        /**
         * event: on inject
         */
        $onInject: function () {
            var self = this;

            this.$Color = new Element('div', {
                'class': 'invoice-status-select-color'
            }).inject(this.$Elm);

            this.$Select = new QUISelect({
                'class'  : 'invoice-status-select-qui',
                showIcons: false,
                events   : {
                    onChange: function (value) {
                        var data  = self.getAttribute('data');
                        var entry = data.filter(function (entry) {
                            return entry.id === value;
                        });

                        if (!entry.length) {
                            self.$Color.setStyle('background-color', null);
                            self.$Elm.value = '';
                            return;
                        }

                        entry = entry[0];

                        self.$Color.setStyle('background-color', entry.color);
                        self.$Elm.value = entry.id;
                    }
                }
            }).inject(this.$Elm);

            new ProcessingStatus().getList().then(function (result) {
                var data = result.data;

                self.setAttribute('data', data);

                for (var i = 0, len = data.length; i < len; i++) {
                    self.$Select.appendChild(
                        data[i].title,
                        data[i].id
                    );
                }
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            if (this.$Elm.nodeName === 'INPUT') {
                this.$Input      = this.$Elm;
                this.$Input.type = 'hidden';
            }

            if (this.$Elm.nodeName === 'SELECT') {
                this.$Input = this.$Elm;
                this.$Input.setStyle('display', 'none');
            }

            this.create().wraps(this.$Input);
            this.$onInject();
        }
    });
});
