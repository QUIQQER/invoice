/**
 * @module package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoices
 * @author www.pcsg.de (Henning Leutz)
 *
 * Zeigt alle Rechnungsentwürfe an
 */
define('package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoices', [

    'qui/QUI',
    'qui/controls/desktop/Panel',
    'qui/controls/windows/Confirm',
    'qui/controls/buttons/Button',
    'qui/controls/contextmenu/Item',
    'controls/grid/Grid',
    'package/quiqqer/invoice/bin/Invoices',
    'package/quiqqer/invoice/bin/backend/utils/Dialogs',
    'Locale',
    'Ajax',

    'css!package/quiqqer/invoice/bin/backend/controls/panels/Journal.css',
    'css!package/quiqqer/erp/bin/backend/payment-status.css'

], function(QUI, QUIPanel, QUIConfirm, QUIButton, QUIContextMenuItem, Grid, Invoices, Dialogs, QUILocale, QUIAjax) {
    'use strict';

    const lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIPanel,
        Type: 'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoices',

        Binds: [
            'refresh',
            '$onCreate',
            '$onResize',
            '$onInject',
            '$onDestroy',
            '$onShow',
            '$clickPostInvoice',
            '$clickCreateInvoice',
            '$clickDeleteInvoice',
            '$clickCopyInvoice',
            '$clickPDF',
            '$onInvoicesChange',
            '$onClickInvoiceDetails',
            '$openXmlCategory'
        ],

        initialize: function(options) {
            this.setAttributes({
                icon: 'fa fa-money',
                title: QUILocale.get(lg, 'erp.panel.temporary.invoice.title')
            });

            this.parent(options);

            this.$Grid = null;
            this.$Currency = null;

            this.addEvents({
                onCreate: this.$onCreate,
                onResize: this.$onResize,
                onInject: this.$onInject,
                onDestroy: this.$onDestroy,
                onShow: this.$onShow
            });

            Invoices.addEvents({
                onDeleteInvoice: this.$onInvoicesChange,
                onSaveInvoice: this.$onInvoicesChange,
                onCreateInvoice: this.$onInvoicesChange,
                onCopyInvoice: this.$onInvoicesChange,
                onPostInvoice: this.$onInvoicesChange,
                createCreditNote: this.$onInvoicesChange
            });
        },

        /**
         * Refresh the grid
         */
        refresh: function() {
            if (!this.$Grid) {
                return;
            }

            this.Loader.show();

            return Invoices.getTemporaryInvoicesList({
                perPage: this.$Grid.options.perPage,
                page: this.$Grid.options.page,
                sortBy: this.$Grid.options.sortBy,
                sortOn: this.$Grid.options.sortOn
            }, {
                currency: this.$Currency.getAttribute('value')
            }).then((result) => {
                result.data = result.data.map(function(entry) {
                    const Icon = new Element('span');

                    switch (parseInt(entry.type)) {
                        // gutschrift
                        case 3:
                            Icon.addClass('fa fa-clipboard');
                            break;

                        // storno
                        case 4:
                            Icon.addClass('fa fa-ban');
                            break;

                        default:
                            Icon.addClass('fa fa-file-text-o');
                    }

                    entry.display_type = Icon;
                    entry.opener = '&nbsp;';

                    return entry;
                });

                this.$Grid.setData(result);

                const Actions = this.$Grid.getButtons().filter(function(Btn) {
                        return Btn.getAttribute('name') === 'actions';
                    })[0],

                    children = Actions.getChildren();


                const Copy = children.filter(function(Btn) {
                    return Btn.getAttribute('name') === 'copy';
                })[0];

                const Delete = children.filter(function(Btn) {
                    return Btn.getAttribute('name') === 'delete';
                })[0];

                const PDF = children.filter(function(Btn) {
                    return Btn.getAttribute('name') === 'pdf';
                })[0];

                const Post = children.filter(function(Btn) {
                    return Btn.getAttribute('name') === 'post';
                })[0];

                Copy.disable();
                Delete.disable();
                PDF.disable();
                Post.disable();

                this.Loader.hide();
            }).catch((err) => {
                console.error(err);
            });
        },

        /**
         * Opens a TemporaryInvoice Panel
         *
         * @param {String} invoiceId
         * @return {Promise}
         */
        openInvoice: function(invoiceId) {
            return new Promise(function(resolve) {
                require([
                    'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoice',
                    'utils/Panels'
                ], function(TemporaryInvoice, PanelUtils) {
                    const Panel = new TemporaryInvoice({
                        invoiceId: invoiceId,
                        '#id': invoiceId
                    });

                    PanelUtils.openPanelInTasks(Panel);
                    resolve(Panel);
                });
            });
        },

        /**
         * Download an invoice
         *
         * @param {Number|String} invoiceId
         */
        downloadPdf: function(invoiceId) {
            return new Promise(function(resolve) {
                const id = 'download-invoice-' + invoiceId;

                new Element('iframe', {
                    src: URL_OPT_DIR + 'quiqqer/invoice/bin/backend/downloadInvoice.php?' + Object.toQueryString({
                        invoiceId: invoiceId
                    }),
                    id: id,
                    styles: {
                        position: 'absolute',
                        top: -200,
                        left: -200,
                        width: 50,
                        height: 50
                    }
                }).inject(document.body);

                (function() {
                    // document.getElements('#' + id).destroy();
                    resolve();
                }).delay(1000, this);
            });
        },

        /**
         * Event Handling
         */

        /**
         * event : on panel create
         */
        $onCreate: function() {
            const self = this;

            // Buttons

            // currency
            this.$Currency = new QUIButton({
                name: 'currency',
                disabled: true,
                showIcons: false,
                menuCorner: 'topRight',
                position: 'right',
                order: 1,
                events: {
                    onChange: function(Menu, Item) {
                        const value = Item.getAttribute('value');
                        let text = value;

                        if (value === '') {
                            text = QUILocale.get(lg, 'currency.select.all');
                        }

                        self.$Currency.setAttribute('value', value);
                        self.$Currency.setAttribute('text', text);
                        self.refresh();
                    }
                }
            });

            // Grid
            this.getContent().setStyles({
                padding: 10
            });

            const Container = new Element('div').inject(
                this.getContent()
            );


            const Actions = new QUIButton({
                name: 'actions',
                text: QUILocale.get(lg, 'journal.btn.actions'),
                menuCorner: 'topRight',
                position: 'right',
                order: 2
            });

            Actions.appendChild({
                name: 'post',
                disabled: true,
                text: QUILocale.get(lg, 'journal.btn.post'),
                icon: 'fa fa-file-text-o',
                events: {
                    onClick: this.$clickPostInvoice
                }
            });

            Actions.appendChild({
                name: 'copy',
                disabled: true,
                text: QUILocale.get(lg, 'temporary.btn.copyInvoice'),
                icon: 'fa fa-copy',
                events: {
                    onClick: this.$clickCopyInvoice
                }
            });

            Actions.appendChild({
                name: 'delete',
                disabled: true,
                text: QUILocale.get(lg, 'temporary.btn.deleteInvoice'),
                icon: 'fa fa-trash',
                events: {
                    onClick: this.$clickDeleteInvoice
                }
            });

            Actions.appendChild({
                name: 'pdf',
                disabled: true,
                text: QUILocale.get(lg, 'journal.btn.pdf'),
                icon: 'fa fa-file-pdf-o',
                events: {
                    onClick: this.$clickPDF
                }
            });


            this.$Grid = new Grid(Container, {
                titleSort: true,
                storageKey: 'quiqqer-invoice-grid-temporaryInvoices',
                pagination: true,
                multipleSelection: true,
                serverSort: true,
                sortOn: 'date',
                sortBy: 'DESC',

                accordion: true,
                autoSectionToggle: false,
                openAccordionOnClick: false,
                toggleiconTitle: '',
                accordionLiveRenderer: this.$onClickInvoiceDetails,

                exportData: true,
                exportTypes: {
                    csv: true,
                    json: true,
                    xls: true
                },

                buttons: [
                    Actions,
                    this.$Currency,
                    {
                        name: 'create',
                        text: QUILocale.get(lg, 'temporary.btn.createInvoice'),
                        textimage: 'fa fa-plus',
                        events: {
                            onClick: function(Btn) {
                                Btn.setAttribute('textimage', 'fa fa-spinner fa-spin');

                                self.$clickCreateInvoice(Btn).then(function() {
                                    Btn.setAttribute('textimage', 'fa fa-plus');
                                });
                            }
                        }
                    }
                ],
                columnModel: [
                    {
                        header: '&nbsp;',
                        dataIndex: 'opener',
                        dataType: 'int',
                        width: 30
                    },
                    {
                        header: QUILocale.get(lg, 'journal.grid.type'),
                        dataIndex: 'display_type',
                        dataType: 'node',
                        width: 30
                    },
                    {
                        header: QUILocale.get(lg, 'journal.grid.invoiceNo'),
                        dataIndex: 'id',
                        dataType: 'integer',
                        width: 100
                    },
                    {
                        header: QUILocale.get('quiqqer/quiqqer', 'name'),
                        dataIndex: 'customer_name',
                        dataType: 'string',
                        width: 200,
                        className: 'clickable'
                    },
                    {
                        header: QUILocale.get(lg, 'journal.grid.customerNo'),
                        dataIndex: 'customer_id_display',
                        dataType: 'string',
                        width: 90,
                        className: 'clickable'
                    },
                    {
                        header: QUILocale.get(lg, 'journal.grid.status'),
                        dataIndex: 'paid_status_display',
                        dataType: 'html',
                        width: 120,
                        className: 'grid-align-center'
                    },
                    {
                        header: QUILocale.get('quiqqer/quiqqer', 'date'),
                        dataIndex: 'date',
                        dataType: 'date',
                        width: 90
                    },
                    {
                        header: QUILocale.get('quiqqer/quiqqer', 'project'),
                        dataIndex: 'project_name',
                        dataType: 'string',
                        width: 160
                    },
                    {
                        header: QUILocale.get(lg, 'journal.grid.sum'),
                        dataIndex: 'display_sum',
                        dataType: 'string',
                        width: 100,
                        className: 'payment-status-amountCell'
                    },
                    {
                        header: QUILocale.get(lg, 'journal.grid.netto'),
                        dataIndex: 'display_nettosum',
                        dataType: 'string',
                        width: 100,
                        className: 'payment-status-amountCell'
                    },
                    {
                        header: QUILocale.get(lg, 'journal.grid.vat'),
                        dataIndex: 'display_vatsum',
                        dataType: 'string',
                        width: 100,
                        className: 'payment-status-amountCell'
                    },
                    {
                        header: QUILocale.get(lg, 'journal.grid.paymentMethod'),
                        dataIndex: 'payment_title',
                        dataType: 'string',
                        width: 180
                    },
                    {
                        header: QUILocale.get(lg, 'temporary.grid.timeForPayment'),
                        dataIndex: 'time_for_payment',
                        dataType: 'string',
                        width: 120
                    },
                    {
                        header: QUILocale.get(lg, 'journal.grid.paymentDate'),
                        dataIndex: 'paid_date',
                        dataType: 'date',
                        width: 120
                    },
                    {
                        header: QUILocale.get(lg, 'journal.grid.paid'),
                        dataIndex: 'display_paid',
                        dataType: 'string',
                        width: 100,
                        className: 'payment-status-amountCell'
                    },
                    {
                        header: QUILocale.get(lg, 'journal.grid.open'),
                        dataIndex: 'display_missing',
                        dataType: 'string',
                        width: 100,
                        className: 'payment-status-amountCell'
                    },
                    {
                        header: QUILocale.get(lg, 'journal.grid.brutto'),
                        dataIndex: 'isbrutto',
                        dataType: 'integer',
                        width: 50
                    },
                    {
                        header: QUILocale.get(lg, 'journal.grid.taxId'),
                        dataIndex: 'taxId',
                        dataType: 'string',
                        width: 105
                    },
                    {
                        header: QUILocale.get('quiqqer/quiqqer', 'c_date'),
                        dataIndex: 'c_date',
                        dataType: 'date',
                        width: 140
                    },
                    {
                        header: QUILocale.get('quiqqer/quiqqer', 'c_user'),
                        dataIndex: 'c_username',
                        dataType: 'integer',
                        width: 180
                    },
                    {
                        header: QUILocale.get(lg, 'journal.grid.orderNo'),
                        dataIndex: 'order_id',
                        dataType: 'integer',
                        width: 80
                    },
                    {
                        header: QUILocale.get(lg, 'journal.grid.comments'),
                        dataIndex: 'comments',
                        dataType: 'string',
                        width: 100
                    },
                    {
                        header: QUILocale.get(lg, 'journal.grid.paymentData'),
                        dataIndex: 'payment_data',
                        dataType: 'string',
                        width: 100
                    },
                    {
                        header: QUILocale.get(lg, 'journal.grid.processing'),
                        dataIndex: 'processing_status_display',
                        dataType: 'html',
                        width: 150
                    },
                    {
                        header: QUILocale.get(lg, 'journal.grid.hash'),
                        dataIndex: 'hash',
                        dataType: 'string',
                        width: 280,
                        className: 'monospace'
                    },
                    {
                        header: QUILocale.get(lg, 'journal.grid.globalProcessId'),
                        dataIndex: 'global_process_id',
                        dataType: 'string',
                        width: 280,
                        className: 'monospace'
                    },
                    {
                        dataIndex: 'paidstatus',
                        dataType: 'string',
                        hidden: true
                    },
                    {
                        dataIndex: 'c_user',
                        dataType: 'integer',
                        hidden: true
                    }
                ]
            });

            this.$Grid.addEvents({
                onRefresh: this.refresh,

                onClick: function() {
                    const selected = this.getSelectedIndices();

                    const Actions = this.getButtons().filter(function(Btn) {
                            return Btn.getAttribute('name') === 'actions';
                        })[0],

                        children = Actions.getChildren();

                    const Copy = children.filter(function(Btn) {
                        return Btn.getAttribute('name') === 'copy';
                    })[0];

                    const Delete = children.filter(function(Btn) {
                        return Btn.getAttribute('name') === 'delete';
                    })[0];

                    const PDF = children.filter(function(Btn) {
                        return Btn.getAttribute('name') === 'pdf';
                    })[0];

                    const Post = children.filter(function(Btn) {
                        return Btn.getAttribute('name') === 'post';
                    })[0];

                    if (!selected.length) {
                        Copy.disable();
                        Delete.disable();
                        PDF.disable();
                        Post.disable();
                        return;
                    }

                    if (selected.length === 1) {
                        Copy.enable();
                        Delete.enable();
                        PDF.enable();
                        Post.enable();
                        return;
                    }

                    Copy.disable();
                    Delete.enable();
                    PDF.disable();
                    Post.enable();
                },

                onDblClick: function(data) {
                    if (typeof data !== 'undefined' &&
                        (data.cell.get('data-index') === 'customer_id' ||
                            data.cell.get('data-index') === 'customer_name')) {

                        const Cell = data.cell,
                            position = Cell.getPosition(),
                            rowData = self.$Grid.getDataByRow(data.row);

                        return new Promise(function(resolve) {
                            require([
                                'qui/controls/contextmenu/Menu',
                                'qui/controls/contextmenu/Item'
                            ], function(QUIMenu, QUIMenuItem) {
                                const Menu = new QUIMenu({
                                    events: {
                                        onBlur: function() {
                                            Menu.hide();
                                            Menu.destroy();
                                        }
                                    }
                                });

                                Menu.appendChild(
                                    new QUIMenuItem({
                                        icon: rowData.display_type.className,
                                        text: QUILocale.get(lg, 'journal.contextMenu.open.invoice'),
                                        events: {
                                            onClick: function() {
                                                self.openInvoice(rowData.hash);
                                            }
                                        }
                                    })
                                );

                                Menu.appendChild(
                                    new QUIMenuItem({
                                        icon: 'fa fa-user-o',
                                        text: QUILocale.get(lg, 'journal.contextMenu.open.user'),
                                        events: {
                                            onClick: function() {
                                                require(
                                                    ['package/quiqqer/customer/bin/backend/Handler'],
                                                    function(CustomerHandler) {
                                                        CustomerHandler.openCustomer(rowData.customer_id);
                                                    }
                                                );
                                            }
                                        }
                                    })
                                );

                                Menu.inject(document.body);
                                Menu.setPosition(position.x, position.y + 30);
                                Menu.setTitle(rowData.id);
                                Menu.show();
                                Menu.focus();

                                resolve();
                            });
                        });
                    }

                    if (!self.$Grid.getSelectedData().length) {
                        return;
                    }

                    self.openInvoice(
                        self.$Grid.getSelectedData()[0].hash
                    );
                }
            });
        },

        /**
         * event : on panel resize
         */
        $onResize: function() {
            if (!this.$Grid) {
                return;
            }

            const Body = this.getContent();

            if (!Body) {
                return;
            }

            const size = Body.getSize();

            this.$Grid.setHeight(size.y - 20);
            this.$Grid.setWidth(size.x - 20);
            this.$Grid.resize();
        },

        /**
         * event: on panel inject
         */
        $onInject: function() {
            const self = this;

            QUIAjax.get([
                'package_quiqqer_currency_ajax_getAllowedCurrencies',
                'package_quiqqer_currency_ajax_getDefault'
            ], function(currencies, currency) {
                let i, len, entry, text;

                if (!currencies.length || currencies.length === 1) {
                    self.$Currency.destroy();
                    return;
                }

                for (i = 0, len = currencies.length; i < len; i++) {
                    entry = currencies[i];

                    text = entry.code + ' ' + entry.sign;
                    text = text.trim();

                    self.$Currency.appendChild(
                        new QUIContextMenuItem({
                            name: entry.code,
                            value: entry.code,
                            text: text
                        })
                    );
                }

                self.$Currency.appendChild(
                    new QUIContextMenuItem({
                        name: 'all',
                        value: '',
                        text: QUILocale.get(lg, 'currency.select.all')
                    })
                );

                self.$Currency.enable();
                self.$Currency.setAttribute('value', currency.code);
                self.$Currency.setAttribute('text', currency.code);
            }, {
                'package': 'quiqqer/currency'
            });

            this.$Currency.getContextMenu(function(ContextMenu) {
                ContextMenu.setAttribute('showIcons', false);
            });

            this.refresh();
        },

        /**
         * event: on panel destroy
         */
        $onDestroy: function() {
            Invoices.removeEvents({
                onDeleteInvoice: this.$onInvoicesChange,
                onCreateInvoice: this.$onInvoicesChange,
                onSaveInvoice: this.$onInvoicesChange,
                onCopyInvoice: this.$onInvoicesChange,
                onPostInvoice: this.$onInvoicesChange,
                createCreditNote: this.$onInvoicesChange
            });
        },

        /**
         * event: on panel show
         */
        $onShow: function() {
            this.refresh();
        },

        /**
         * Creates a new invoice
         *
         * @return {Promise}
         */
        $clickCreateInvoice: function() {
            return Invoices.createInvoice().then(function(invoiceId) {
                return this.openInvoice(invoiceId);
            }.bind(this)).catch(function(Exception) {
                QUI.getMessageHandler().then(function(MH) {
                    if (typeof Exception.getMessage !== 'undefined') {
                        MH.addError(Exception.getMessage());
                        return;
                    }

                    console.error(Exception);
                });
            });
        },

        /**
         * Post the selected invoice
         *
         * @param Button
         */
        $clickPostInvoice: function(Button) {
            const selected = this.$Grid.getSelectedData(),
                oldImage = Button.getAttribute('textimage');

            if (!selected.length) {
                return;
            }

            Button.setAttribute('textimage', 'fa fa-spinner fa-spin');

            const proms = [];

            for (let i = 0, len = selected.length; i < len; i++) {
                proms.push(Invoices.getMissingAttributes(selected[i].id));
            }

            Promise.all(proms).then(function(result) {
                Button.setAttribute('textimage', oldImage);

                for (let i = 0, len = result.length; i < len; i++) {
                    if (Object.getLength(result[i])) {
                        return false;
                    }
                }

                return true;
            }).then(function(go) {
                if (go === false) {
                    QUI.getMessageHandler().then(function(MH) {
                        MH.addError(
                            QUILocale.get(lg, 'exception.post.invoices.missing.attributes')
                        );
                    });

                    return;
                }

                let invoices = '', Row;

                for (let i = 0, len = selected.length; i < len; i++) {
                    Row = selected[i];
                    invoices += '<li>' + Row.id;

                    if (Row.customer_name) {
                        invoices += ' - ' + Row.customer_name;
                    }

                    if (Row.project_name) {
                        invoices += ' (' + Row.project_name + ')';
                    }

                    invoices += '</li>';
                }

                new QUIConfirm({
                    title: QUILocale.get(lg, 'dialog.ti.post.title'),
                    text: QUILocale.get(lg, 'dialog.ti.post.text'),
                    information: QUILocale.get(lg, 'dialog.ti.post.information', {
                        invoices: '<ul>' + invoices + '</ul>'
                    }),
                    icon: 'fa fa-check',
                    texticon: 'fa fa-check',
                    maxHeight: 400,
                    maxWidth: 600,
                    autoclose: false,
                    ok_button: {
                        text: QUILocale.get(lg, 'dialog.ti.post.button'),
                        textimage: 'fa fa-check'
                    },
                    events: {
                        onSubmit: function(Win) {
                            Win.Loader.show();

                            const posts = [];

                            for (let i = 0, len = selected.length; i < len; i++) {
                                posts.push(Invoices.postInvoice(selected[i].hash));
                            }

                            Promise.all(posts).then(function() {
                                return Invoices.getSetting('temporaryInvoice', 'openPrintDialogAfterPost');
                            }).then(function(openPrintDialogAfterPost) {
                                if (!openPrintDialogAfterPost || selected.length > 1) {
                                    Win.close();
                                    return;
                                }

                                let entityType;

                                switch (parseInt(selected[0].type)) {
                                    case 3:
                                        entityType = 'CreditNote';
                                        break;

                                    case 4:
                                        entityType = 'Canceled';
                                        break;

                                    default:
                                        entityType = 'Invoice';
                                }

                                // open print dialog
                                Dialogs.openPrintDialog(selected[0].hash, entityType).then(function() {
                                    Win.close();
                                });
                            }).catch(function(Err) {
                                QUI.getMessageHandler().then(function(MH) {
                                    MH.addError(Err.getMessage());
                                });

                                Win.Loader.hide();
                            });
                        }
                    }
                }).open();
            });
        },

        /**
         * opens the delete dialog
         */
        $clickDeleteInvoice: function() {
            const selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            let invoices = '';

            for (let i = 0, len = selected.length; i < len; i++) {
                invoices = invoices + '<li>' + selected[i].id + '</li>';
            }

            new QUIConfirm({
                title: QUILocale.get(lg, 'dialog.ti.delete.title'),
                text: QUILocale.get(lg, 'dialog.ti.delete.text'),
                information: QUILocale.get(lg, 'dialog.ti.delete.information', {
                    invoices: '<ul>' + invoices + '</ul>'
                }),
                icon: 'fa fa-trash',
                texticon: 'fa fa-trash',
                maxHeight: 400,
                maxWidth: 600,
                autoclose: false,
                ok_button: {
                    text: QUILocale.get('quiqqer/quiqqer', 'delete'),
                    textimage: 'fa fa-trash'
                },
                events: {
                    onSubmit: function(Win) {
                        Win.Loader.show();

                        const posts = [];

                        for (let i = 0, len = selected.length; i < len; i++) {
                            posts.push(Invoices.deleteInvoice(selected[i].hash));
                        }

                        Promise.all(posts).then(function() {
                            Win.close();
                        }).then(function() {
                            Win.Loader.show();
                        }).catch(function(Exception) {
                            QUI.getMessageHandler().then(function(MH) {
                                if (typeof Exception.getMessage !== 'undefined') {
                                    MH.addError(Exception.getMessage());
                                    return;
                                }

                                console.error(Exception);
                            });

                            Win.Loader.hide();
                        });
                    }
                }
            }).open();
        },

        /**
         * Copy the temporary invoice and opens the invoice
         */
        $clickCopyInvoice: function() {
            const self = this,
                selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            new QUIConfirm({
                title: QUILocale.get(lg, 'dialog.ti.copy.title'),
                text: QUILocale.get(lg, 'dialog.ti.copy.text'),
                information: QUILocale.get(lg, 'dialog.ti.copy.information', {
                    id: selected[0].id
                }),
                icon: 'fa fa-copy',
                texticon: 'fa fa-copy',
                maxHeight: 400,
                maxWidth: 600,
                autoclose: false,
                ok_button: {
                    text: QUILocale.get('quiqqer/quiqqer', 'copy'),
                    textimage: 'fa fa-copy'
                },
                events: {
                    onSubmit: function(Win) {
                        Win.Loader.show();

                        Invoices.copyTemporaryInvoice(selected[0].hash).then(function(newId) {
                            Win.close();
                            return self.openInvoice(newId);
                        }).then(function() {
                            Win.Loader.show();
                        }).catch(function(Exception) {
                            QUI.getMessageHandler().then(function(MH) {
                                if (typeof Exception.getMessage !== 'undefined') {
                                    MH.addError(Exception.getMessage());
                                    return;
                                }

                                console.error(Exception);
                            });

                            Win.Loader.hide();
                        });
                    }
                }
            }).open();
        },

        /**
         * Export PDF of a temporary invoice
         *
         * @param Button
         */
        $clickPDF: function(Button) {
            let selected = this.$Grid.getSelectedData();

            if (!selected.length) {
                return;
            }

            selected = selected[0];
            Button.setAttribute('textimage', 'fa fa-spinner fa-spin');


            let entityType;

            switch (parseInt(selected.type)) {
                case 3:
                    entityType = 'CreditNote';
                    break;

                case 4:
                    entityType = 'Canceled';
                    break;

                default:
                    entityType = 'Invoice';
            }

            Dialogs.openPrintDialog(selected.hash, entityType);

            Button.setAttribute('textimage', 'fa fa-file-pdf-o');
        },

        /**
         * event: invoices changed something
         * create, delete, save, copy
         */
        $onInvoicesChange: function() {
            this.refresh();
        },

        /**
         * Open the accordion details of the invoice
         *
         * @param {Object} data
         */
        $onClickInvoiceDetails: function(data) {
            const row = data.row,
                ParentNode = data.parent;

            ParentNode.setStyle('padding', 10);
            ParentNode.set('html', '<div class="fa fa-spinner fa-spin"></div>');

            Invoices.getArticleHtmlFromTemporary(this.$Grid.getDataByRow(row).hash).then(function(result) {
                ParentNode.set('html', '');

                if (result.indexOf('<table') === -1) {
                    ParentNode.set('html', QUILocale.get(lg, 'erp.panel.temporary.invoices.no.article'));
                    return;
                }

                new Element('div', {
                    'class': 'invoices-invoice-details',
                    html: result
                }).inject(ParentNode);
            });
        },

        //region category stuff

        $openXmlCategory: function(Category) {
            this.Loader.show();

            QUIAjax.get('package_quiqqer_order_ajax_backend_panel_getCategory', (html) => {
                this.$closeCategory().then((Container) => {
                    Container.set('html', html);

                    return QUI.parse(Container);
                }).then(() => {
                    return this.$openCategory();
                }).then(() => {
                    this.Loader.hide();
                });
            }, {
                'package': 'quiqqer/order',
                category: Category.getAttribute('name')
            });
        }

        //endregion
    });
});
