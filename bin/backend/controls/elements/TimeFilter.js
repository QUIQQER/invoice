/**
 * @module package/quiqqer/invoice/bin/backend/controls/elements/TimeFilter
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require qui/controls/buttons/Button
 * @require qui/controls/buttons/Select
 * @require Locale
 * @require css!package/quiqqer/invoice/bin/backend/controls/elements/TimeFilter.css
 *
 * @event onChange [self, {Date} from, {Date} to]
 */
define('package/quiqqer/invoice/bin/backend/controls/elements/TimeFilter', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/buttons/Select',
    'Locale',

    'css!package/quiqqer/invoice/bin/backend/controls/elements/TimeFilter.css'

], function (QUI, QUIControl, QUIButton, QUISelect, QUILocale) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/invoice/bin/backend/controls/elements/TimeFilter',

        Binds: [
            'previous',
            'next',
            '$onChange'
        ],

        initialize: function (options) {
            this.$Current = new Date();
            this.$To      = null;

            this.$type = 'month';

            this.parent(options);
        },

        /**
         * Create the DomNode
         *
         * @return {Element}
         */
        create: function () {
            this.$Elm = this.parent();

            if (this.getAttribute('styles')) {
                this.$Elm.setStyles(this.getAttribute('styles'));
            }

            this.$Prev = new QUIButton({
                icon  : 'fa fa-chevron-left',
                events: {
                    onClick: this.previous
                }
            });

            this.$Next = new QUIButton({
                icon  : 'fa fa-chevron-right',
                events: {
                    onClick: this.next
                }
            });

            this.$Select = new QUISelect({
                name                 : 'timeFilter',
                showIcons            : false,
                placeholderText      : '',
                placeholderSelectable: false,
                styles               : {
                    marginLeft : 0,
                    marginRight: 0
                },
                events               : {
                    onChange: this.$onChange
                }
            });

            this.$Select.appendChild(QUILocale.get(lg, 'journal.timeFilter.month'), 'month');
            this.$Select.appendChild(QUILocale.get(lg, 'journal.timeFilter.quarter'), 'quarter');
            this.$Select.appendChild(QUILocale.get(lg, 'journal.timeFilter.halfYear'), 'half-year');
            this.$Select.appendChild(QUILocale.get(lg, 'journal.timeFilter.year'), 'year');
            this.$Select.appendChild(QUILocale.get(lg, 'journal.timeFilter.period'), 'period');

            this.$Prev.inject(this.$Elm);
            this.$Select.inject(this.$Elm);
            this.$Next.inject(this.$Elm);

            this.$triggerChange();

            return this.$Elm;
        },

        /**
         * Refresh the display
         */
        refresh: function () {
            var text = '';

            this.$Prev.enable();
            this.$Next.enable();

            switch (this.$Select.getValue()) {
                default:
                case 'month':
                    this.$type = 'month';

                    var month = ("0" + (this.$Current.getMonth() + 1)).slice(-2);

                    text = QUILocale.get('quiqqer/quiqqer', 'month.' + month);
                    text = text + ' (' + this.$Current.getFullYear() + ')';
                    break;

                case 'quarter':
                    this.$type = 'quarter';

                    var quarter = Math.ceil(this.$Current.getMonth() / 3);

                    text = QUILocale.get(lg, 'quarter.' + quarter);
                    text = text + ' ' + this.$Current.getFullYear();
                    break;

                case 'half-year':
                    this.$type = 'half-year';

                    var halfYear = 2;

                    if (this.$Current.getMonth() < 6) {
                        halfYear = 1;
                    }

                    text = QUILocale.get(lg, 'halfYear.' + halfYear);
                    text = text + ' ' + this.$Current.getFullYear();
                    break;

                case 'year':
                    this.$type = 'year';

                    text = QUILocale.get('quiqqer/quiqqer', 'year');
                    text = text + ' ' + this.$Current.getFullYear();
                    break;

                case 'period':
                    this.$Prev.disable();
                    this.$Next.disable();
                    this.$type = 'period';

                    var Formatter = this.$getDateFormatter();

                    text = QUILocale.get(lg, 'journal.timeFilter.period.select', {
                        from: Formatter.format(this.$Current),
                        to  : Formatter.format(this.$To)
                    });
                    break;
            }

            this.$Select.setPlaceholder(text);
            this.$Select.selectPlaceholder();
        },

        /**
         * Return the selected period / time
         *
         * @return {{from: number, to: number}}
         */
        getValue: function () {
            if (!this.$To) {
                this.$To = new Date();
            }

            return {
                from: Math.floor(this.$Current.getTime() / 1000),
                to  : Math.floor(this.$To.getTime() / 1000)
            };
        },

        /**
         * Set a period
         *
         * @param From
         * @param To
         */
        setPeriod: function (From, To) {
            this.$Current = From;
            this.$To      = To;

            this.$triggerChange();
        },

        /**
         * event : on change
         */
        $onChange: function () {
            if (this.$Select.getValue() === 'period') {
                this.showPeriodSelect();
                return;
            }

            this.$Current = new Date();
            this.$To      = null;

            this.$triggerChange();
        },

        /**
         * trigger the change event
         */
        $triggerChange: function () {
            this.refresh();

            var From = new Date(this.$Current),
                To   = new Date(this.$To);

            if (!To) {
                To = new Date(this.$Current);
            }

            var year  = this.$Current.getFullYear(),
                month = this.$Current.getMonth();

            var getDaysInMonth = function (m, y) {
                m = m + 1;
                return m === 2 ? y & 3 || !(y % 25) && y & 15 ? 28 : 29 : 30 + (m + (m >> 3) & 1);
            };

            switch (this.$type) {
                case 'month':
                    From.setFullYear(year, month, 1);
                    To.setFullYear(
                        year,
                        month,
                        getDaysInMonth(month, year)
                    );
                    break;

                case 'quarter':
                    var quarter = Math.ceil(this.$Current.getMonth() / 3);

                    month = quarter * 3 - 3;

                    From.setFullYear(year, month, 1);

                    To.setFullYear(
                        year,
                        month + 2,
                        getDaysInMonth(month + 2, year)
                    );
                    break;

                case 'half-year':
                    if (this.$Current.getMonth() < 6) {
                        From.setFullYear(year, 0, 1);

                        To.setFullYear(
                            year,
                            5,
                            getDaysInMonth(5, year)
                        );
                    } else {
                        From.setFullYear(year, 6, 1);

                        To.setFullYear(
                            year,
                            11,
                            getDaysInMonth(11, year)
                        );
                    }
                    break;

                case 'year':
                    From.setFullYear(year, 0, 1);
                    To.setFullYear(
                        year,
                        11,
                        getDaysInMonth(11, year)
                    );
                    break;
            }

            To.setHours(23);

            this.$Current = From;
            this.$To      = To;

            this.fireEvent('change', [this, From, To]);
        },

        /**
         * Next step
         */
        next: function () {
            switch (this.$type) {
                case 'month':
                    return this.nextMonth();

                case 'quarter':
                    return this.nextQuarter();

                case 'half-year':
                    return this.nextHalfYear();

                case 'year':
                    return this.nextYear();

                default:
                    return;
            }
        },

        /**
         * Previous step
         */
        previous: function () {
            switch (this.$type) {
                case 'month':
                    return this.previousMonth();

                case 'quarter':
                    return this.previousQuarter();

                case 'half-year':
                    return this.previousHalfYear();

                case 'year':
                    return this.previousYear();

                default:
                    return;
            }
        },

        /**
         * Show next month
         */
        nextMonth: function () {
            this.$Current.setMonth(this.$Current.getMonth() + 1);
            this.$triggerChange();
        },

        /**
         * Show previous month
         */
        previousMonth: function () {
            this.$Current.setMonth(this.$Current.getMonth() - 1);
            this.$triggerChange();
        },

        /**
         * Show next quarter
         */
        nextQuarter: function () {
            this.$Current.setMonth(this.$Current.getMonth() + 4); // date month is so curios
            this.$triggerChange();
        },

        /**
         * Show previous quarter
         */
        previousQuarter: function () {
            this.$Current.setMonth(this.$Current.getMonth() - 2);
            this.$triggerChange();
        },

        /**
         * Show next half year
         */
        nextHalfYear: function () {
            console.info(this.$Current.getMonth());
            if (this.$Current.getMonth() < 6) {
                this.$Current.setMonth(11);
            } else {
                this.$Current.setMonth(0);
                this.$Current.setFullYear(this.$Current.getFullYear() + 1);
            }

            this.$triggerChange();
        },

        /**
         * Show previous half year
         */
        previousHalfYear: function () {
            this.$Current.setMonth(this.$Current.getMonth() - 6);
            this.$triggerChange();
        },

        /**
         * Show next year
         */
        nextYear: function () {
            this.$Current.setFullYear(this.$Current.getFullYear() + 1);
            this.$triggerChange();
        },

        /**
         * Show previous year
         */
        previousYear: function () {
            this.$Current.setFullYear(this.$Current.getFullYear() - 1);
            this.$triggerChange();
        },

        /**
         * Show the period selector
         */
        showPeriodSelect: function () {
            var self        = this,
                elmPosition = this.getElm().getPosition(),
                elmSize     = this.getElm().getSize(),
                left        = elmPosition.x + 32,
                width       = 440;

            var size = document.body.getSize();

            if (size.y <= left + width) {
                left = elmSize.x + elmPosition.x - width - 32;
            }

            this.$Current = new Date();
            this.$To      = new Date();

            var Container = new Element('div', {
                tabindex: -1,
                'class' : 'timefilter-period-select',
                html    : '<span class="fa fa-spinner fa-spin" style="margin: 20px"></span>',
                styles  : {
                    left : left,
                    top  : elmPosition.y + 40,
                    width: width
                },
                events  : {
                    blur: function (event) {
                        require([
                            'package/quiqqer/calendar-controls/bin/Scheduler'
                        ], function (Scheduler) {
                            Scheduler.getScheduler().destroyCalendar();

                            event.target.setStyle('display', 'none');
                            event.target.destroy();
                        });
                    }
                }
            }).inject(document.body);

            require([
                'package/quiqqer/calendar-controls/bin/Scheduler'
            ], function (Scheduler) {

                Scheduler.loadExtension('minical').then(function () {
                    var Handler = Scheduler.getScheduler();

                    Container.set('html', '');

                    var Left = new Element('div', {
                        'class': 'timefilter-period-select-calendarContainer'
                    }).inject(Container);

                    var Right = new Element('div', {
                        'class': 'timefilter-period-select-calendarContainer'
                    }).inject(Container);

                    var Ghost = new Element('div', {
                        html: '<div class="dhx_cal_navline">' +
                        '<div class="dhx_cal_date"></div>' +
                        '<div class="dhx_cal_tab" name="day_tab" style="right:76px;"></div>' +
                        '</div>' +
                        '<div class="dhx_cal_header"></div>' +
                        '<div class="dhx_cal_data"></div>'
                    });

                    Handler.config.xml_date = "%Y-%m-%d";
                    Handler.init(Ghost, new Date(), "day");

                    Handler.renderCalendar({
                        container : Left,
                        date      : new Date(),
                        navigation: true,
                        handler   : function (date) {
                            self.$Current = date;
                        }
                    });

                    Handler.renderCalendar({
                        container : Right,
                        date      : new Date(),
                        navigation: true,
                        handler   : function (date) {
                            self.$To = date;
                        }
                    });

                    var Accept = new QUIButton({
                        text  : QUILocale.get('quiqqer/system', 'accept'),
                        styles: {
                            'float': 'right',
                            margin : '0 10px 0 0'
                        }
                    }).inject(Container);

                    Accept.getElm().addEvent('mousedown', function () {
                        self.$triggerChange();
                    });

                    Container.focus();
                });
            });
        },


        /**
         * Return the date formatter
         *
         * @return {window.Intl.DateTimeFormat}
         */
        $getDateFormatter: function () {
            var locale = QUILocale.getCurrent();

            var options = {
                year : '2-digit',
                month: '2-digit',
                day  : '2-digit'
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