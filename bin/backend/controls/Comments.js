/**
 * @module package/quiqqer/invoice/bin/backend/controls/Comments
 *
 * Comments / History Display
 */
define('package/quiqqer/invoice/bin/backend/controls/Comments', [

    'qui/QUI',
    'qui/controls/Control',
    'Mustache',
    'Locale',

    'text!package/quiqqer/invoice/bin/backend/controls/Comments.html',
    'css!package/quiqqer/invoice/bin/backend/controls/Comments.css'

], function (QUI, QUIControl, Mustache, QUILocale, template) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/Comments',

        Binds: [
            '$onCreate'
        ],

        options: {
            comments: false
        },

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onCreate: this.$onCreate
            });
        },

        /**
         * Create the DomNode element
         *
         * @returns {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();

            this.$Elm.addClass('quiqqer-invoice-comments');
            this.unserialize(this.getAttribute('comments'));

            return this.$Elm;
        },

        /**
         *
         * @param {String|Object} comments
         */
        unserialize: function (comments) {
            if (typeOf(comments) === 'string') {
                try {
                    comments = JSON.decode(comments);
                } catch (e) {
                }
            }

            if (!comments) {
                return;
            }

            var Formatter = this.$getFormatter();

            comments.sort(function (a, b) {
                return a.time < b.time;
            });

            comments = comments.map(function (entry) {
                var date = new Date(entry.time * 1000);

                return {
                    time   : Formatter.format(date),
                    message: entry.message
                };
            });

            this.$Elm.set({
                html: Mustache.render(template, {
                    comments      : comments,
                    textNoComments: QUILocale.get(lg, 'comments.message.no.comments')
                })
            });
        },

        /**
         * Return the date formatter
         *
         * @return {window.Intl.DateTimeFormat}
         */
        $getFormatter: function () {
            var locale = QUILocale.getCurrent();

            var options = {
                year  : 'numeric',
                month : 'numeric',
                day   : 'numeric',
                hour  : 'numeric',
                minute: 'numeric',
                second: 'numeric'
            };

            if (!locale.match('_')) {
                locale = locale.toLowerCase() + '_' + locale.toUpperCase();
            }

            locale = locale.replace('_', '-');

            try {
                return window.Intl.DateTimeFormat(locale, options);
            } catch (e) {
                return window.Intl.DateTimeFormat('de-DE', options);
            }
        }
    });
});