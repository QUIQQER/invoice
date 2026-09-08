![QUIQQER Invoice](bin/images/Readme_EN.png)

QUIQQER Invoice (ERP module)
========

The QUIQQER Invoice Module integrates complete invoice management in QUIQQER.

Package name:

    quiqqer/invoice


Features
------

- Invoice creation
- Temporary Invoice creation
- Order Connection - Automatic invoicing via the order module
- Fits perfectly with the order, payment system
- Invoice Template API
- Invoice printing API

ERP Stack
----

We recommend to install additional packages:

- quiqqer/erp
- quiqqer/areas
- quiqqer/currency
- quiqqer/discount
- quiqqer/products
- quiqqer/tax

Installation
------------

The package name is: quiqqer/invoice

Server:

- git@dev.quiqqer.com:quiqqer/erp.git
- git@dev.quiqqer.com:quiqqer/invoice.git

MCP invoice downloads
---------------------

The `invoice_download` tool requires `quiqqer.invoice.mcp`. Pass `invoiceId` as a numeric ID,
prefixed invoice number or UUID/hash. Set `draft: true` for a temporary invoice.

The optional `format` parameter accepts:

- `pdf`: PDF using the configured ERP output template, without embedded invoice XML.
- `xrechnung`: standalone XRechnung 3.0 XML (CII).
- `zugferd`: PDF with embedded invoice XML, using `invoice.zugferdInvoiceAttachmentType`
  (EN16931 when the profile setting is missing).

Without `format`, `invoice.zugferdInvoiceAttachment` determines the output: enabled means ZUGFeRD,
disabled means PDF. A missing setting defaults to ZUGFeRD. An explicit format overrides this setting.
Electronic formats use the existing invoice exporter and require the corresponding invoice and customer data.

Example for an explicit XRechnung download:

```json
{"invoiceId": "INV-123", "format": "xrechnung"}
```

The response contains `format`, `downloadId` and a `download` object with filename, MIME type,
total size and `contentBase64`. Decode each chunk separately and concatenate the decoded bytes.
For further chunks, pass `downloadId`, `offset: download.nextOffset`, and the same invoice, `draft`
and returned `format`. Stop when `download.complete` is true. `maxBytes` limits each chunk to at
most 5 MiB. Downloads are bound to the requesting user and expire after 15 minutes; restart
without `downloadId` if necessary.

Contribution
----------

- Issue Tracker: https://dev.quiqqer.com/quiqqer/invoice/issues
- Source Code: https://dev.quiqqer.com/quiqqer/invoice/tree/master


Support
-------

If you have found an error or want improvements, please send an e-mail to support@pcsg.de.


Licence
-------

- PCSG QEL-1.0
