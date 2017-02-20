/**
 * @module package/quiqqer/invoice/bin/backend/controls/InvoiceItems
 *
 * Invoice item list (Produkte Positionen)
 *
 * @require qui/QUI
 * @require qui/controls/Control
 * @require Mustache
 * @require Locale
 * @require package/quiqqer/invoice/bin/backend/controls/InvoiceItemsProduct
 * @require text!package/quiqqer/invoice/bin/backend/controls/InvoiceItems.html
 * @require css!package/quiqqer/invoice/bin/backend/controls/InvoiceItems.css
 */
define('package/quiqqer/invoice/bin/backend/controls/InvoiceItems', [

    'qui/QUI',
    'qui/controls/Control',
    'Mustache',
    'Locale',
    'package/quiqqer/invoice/bin/backend/controls/InvoiceItemsProduct',

    'text!package/quiqqer/invoice/bin/backend/controls/InvoiceItems.html',
    'css!package/quiqqer/invoice/bin/backend/controls/InvoiceItems.css'

], function (QUI, QUIControl, Mustache, QUILocale, InvoiceItemsProduct, template) {
    "use strict";

    var lg = 'quiqqer/invoice';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/invoice/bin/backend/controls/InvoiceItems',

        options: {},

        initialize: function (options) {
            this.parent(options);

            this.$products = [];

            this.$Container = null;
        },

        /**
         * Create the DOMNode element
         *
         * @returns {HTMLDivElement}
         */
        create: function () {
            this.$Elm = this.parent();

            this.$Elm.addClass('quiqqer-invoice-backend-invoiceItems');

            this.$Elm.set({
                html: Mustache.render(template, {
                    titleDescription: QUILocale.get(lg, 'invoice.products.description'),
                    titleQuantity: QUILocale.get(lg, 'invoice.products.quantity'),
                    titleUnitPrice: QUILocale.get(lg, 'invoice.products.unitPrice'),
                    titlePrice: QUILocale.get(lg, 'invoice.products.price'),
                    titleVAT: QUILocale.get(lg, 'invoice.products.vat'),
                    titleSum: QUILocale.get(lg, 'invoice.products.sum')
                })
            });

            this.$Container = this.$Elm.getElement('.quiqqer-invoice-backend-invoiceItems-items');

            return this.$Elm;
        },

        /**
         * Add a product to the list
         * The product must be an instance of InvoiceItemsProduc
         *
         * @param {Object} Product
         */
        addProduct: function (Product) {
            if (typeof Product !== 'object') {
                return;
            }

            if (!(Product instanceof InvoiceItemsProduct)) {
                return;
            }

            this.$products.push(Product);

            Product.setPosition(this.$products.length);
            Product.inject(this.$Container);
        },

        /**
         * Insert a new empty product
         */
        insertNewProduct: function () {
            this.addProduct(new InvoiceItemsProduct());
        }
    });
});