/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/status/StatusWindow
 * @author www.pcsg.de (Henning Leutz)
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/status/StatusWindow', [

    'qui/QUI',
    'qui/controls/windows/Confirm',
    'package/quiqqer/invoice/bin/ProcessingStatus',
    'package/quiqqer/erp/bin/backend/utils/ERPEntities',
    'package/quiqqer/invoice/bin/Invoices',
    'Locale',
    'Ajax'

], function(QUI, QUIConfirm, ProcessingStatus, ERPEntities, Invoices, QUILocale, QUIAjax) {
    'use strict';

    const lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIConfirm,
        Type: 'package/quiqqer/invoice/bin/backend/controls/panels/status/StatusWindow',

        Binds: [
            '$onOpen',
            '$onSubmit'
        ],

        options: {
            hash: false,
            maxWidth: 550,
            maxHeight: 300,
            autoclose: false
        },

        initialize: function(options) {
            this.parent(options);

            this.setAttributes({
                icon: 'fa fa-check',
                title: QUILocale.get(lg, 'window.status.title', {
                    invoiceId: this.getAttribute('hash')
                })
            });

            this.addEvents({
                onOpen: this.$onOpen,
                onSubmit: this.$onSubmit
            });
        },

        /**
         * event: on import
         */
        $onOpen: function() {
            this.Loader.show();
            this.getContent().set('html', '');

            let Select, invoiceData;

            return ERPEntities.getEntity(this.getAttribute('hash'), 'quiqqer/invoice').then((data) => {
                invoiceData = data;

                this.setAttributes({
                    icon: 'fa fa-check',
                    title: QUILocale.get(lg, 'window.status.title', {
                        invoiceId: data.prefixedNumber
                    })
                });

                this.refresh();

                new Element('p', {
                    html: QUILocale.get(lg, 'window.status.text', {
                        invoiceId: data.prefixedNumber
                    })
                }).inject(this.getContent());

                Select = new Element('select', {
                    styles: {
                        display: 'block',
                        margin: '20px auto 0',
                        width: '80%'
                    }
                }).inject(this.getContent());

            }).then(() => {
                return ProcessingStatus.getList();
            }).then((statusList) => {
                statusList = statusList.data;

                new Element('option', {
                    html: '',
                    value: '',
                    'data-color': ''
                }).inject(Select);

                for (let i = 0, len = statusList.length; i < len; i++) {
                    new Element('option', {
                        html: statusList[i].title,
                        value: statusList[i].id,
                        'data-color': statusList[i].color
                    }).inject(Select);
                }

                Select.value = invoiceData.status;

                this.Loader.hide();
            });
        },

        /**
         * event: on submit
         */
        $onSubmit: function() {
            this.Loader.show();

            QUIAjax.post('package_quiqqer_invoice_ajax_invoices_setStatus', () => {
                this.fireEvent('statusChanged', [this]);
                this.close();
            }, {
                'package': 'quiqqer/invoice',
                invoiceId: this.getAttribute('hash'),
                status: this.getContent().getElement('select').value,
                onError: () => {
                    this.Loader.hide();
                }
            });
        }
    });
});