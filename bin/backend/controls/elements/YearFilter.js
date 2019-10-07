/**
 * @module package/quiqqer/invoice/bin/backend/controls/elements/YearFilter
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/invoice/bin/backend/controls/elements/YearFilter', [

    'qui/QUI',
    'qui/controls/Control',

    'css!package/quiqqer/invoice/bin/backend/controls/elements/YearFilter.css'

], function (QUI, QUIControl) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/elements/TimeFilter',

        Binds: [
            'close',
            'prev',
            'next',
            '$onSelect'
        ],

        options: {
            year  : false,
            amount: 12
        },

        /**
         * constructor
         *
         * @param options
         */
        initialize: function (options) {
            this.parent(options);

            this.$Container = null;
        },

        /**
         * Create the DOMNode
         *
         * @return {Promise}
         */
        create: function () {
            this.$Elm = this.parent();
            this.$Elm.addClass('year-select');

            this.$Elm.set('html', '' +
                '<div class="year-header">' +
                '   <span class="year-header-prev">' +
                '       <span class="fa fa-chevron-left"></span>' +
                '   </span>' +
                '   <span class="year-header-title"></span>' +
                '   <span class="year-header-next">' +
                '       <span class="fa fa-chevron-right"></span>' +
                '   </span>' +
                '</div>' +
                '<div class="year-years"></div>' +
                '<div class="year-cancel">' +
                '   <button class="qui-button--no-icon qui-button">Abbrechen</button>' +
                '</div>'
            );

            this.$Container = this.$Elm.getElement('.year-years');

            if (this.getAttribute('styles')) {
                this.$Elm.setStyles(this.getAttribute('styles'));
            }

            this.$Elm.getElement('.year-header-title').set('html', this.getAttribute('year'));
            this.$Elm.getElement('.year-header-prev').addEvent('click', this.prev);
            this.$Elm.getElement('.year-header-next').addEvent('click', this.next);
            this.$Elm.getElement('button').addEvent('click', this.close);

            this.renderCurrent();

            return this.$Elm;
        },

        /**
         * Fires close event
         */
        close: function () {
            this.fireEvent('close', [this]);
        },

        /**
         * Next year batch
         */
        next: function () {
            var current = parseInt(this.getAttribute('year'));
            var amount  = parseInt(this.getAttribute('amount'));

            this.setAttribute('year', current + amount);
            this.renderCurrent();
        },

        /**
         * Prev year batch
         */
        prev: function () {
            var current = parseInt(this.getAttribute('year'));
            var amount  = parseInt(this.getAttribute('amount'));

            this.setAttribute('year', current - amount);
            this.renderCurrent();
        },

        /**
         * Render current year
         */
        renderCurrent: function () {
            var current = parseInt(this.getAttribute('year'));

            this.$Container.set('html', '');

            for (var len = current + this.getAttribute('amount'); current < len; current++) {
                new Element('div', {
                    html   : current,
                    'class': 'year-years-entry',
                    events : {
                        click: this.$onSelect
                    }
                }).inject(this.$Container);
            }
        },

        /**
         * event: on select
         */
        $onSelect: function (event) {
            var Target = event.target;

            if (!Target.hasClass('year-years-entry')) {
                Target = Target.getParent('.year-years-entry');
            }

            this.fireEvent('select', [this, parseInt(Target.innerText.trim())]);
            this.close();
        }
    });
});