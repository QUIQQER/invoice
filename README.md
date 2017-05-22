Rechnungsverwaltung
========

Erstellen Sie Ihre Rechnungen.

Paketname:

    quiqqer/invoice


ERP Stack
----

Wir empfehlen weitere Pakete zu installieren:

- quiqqer/erp
- quiqqer/areas
- quiqqer/currency
- quiqqer/discount
- quiqqer/products
- quiqqer/tax

Installation
------------

Der Paketname ist: quiqqer/invoice

Benötigte Server:

- git@dev.quiqqer.com:quiqqer/erp.git
- git@dev.quiqqer.com:quiqqer/invoice.git

Mitwirken
----------

- Issue Tracker: https://dev.quiqqer.com/quiqqer/invoice/issues
- Source Code: https://dev.quiqqer.com/quiqqer/invoice/tree/master


Support
-------

Falls Sie ein Fehler gefunden haben, oder Verbesserungen wünschen,
dann können Sie gerne an support@pcsg.de eine E-Mail schreiben.


Lizenz
-------


Entwickler
-------


#### Invoice Events

- onQuiqqerInvoiceCreateCreditNote [Invoice]
- onQuiqqerInvoiceCancel [Invoice]
- onQuiqqerInvoiceStorno [Invoice] (Same as cancel, alias)

- onQuiqqerInvoicePaymentStatusChanged [Invoice, (int) $newStatus, (int) $oldStatus]

- onQuiqqerInvoiceAddComment [Invoice, message]
- onQuiqqerInvoiceAddHistory [Invoice, message]

- onQuiqqerInvoiceCopyBegin [Invoice]
- onQuiqqerInvoiceCopy [Invoice]
- onQuiqqerInvoiceCopyEnd [Invoice, TemporaryInvoice]

- onQuiqqerInvoiceAddPaymentBegin [
    Invoice, 
    $amount, 
    QUI\ERP\Accounting\Payments\Api\PaymentsInterface, 
    $date
]

- onQuiqqerInvoiceAddPayment [
    Invoice, 
    $amount, 
    QUI\ERP\Accounting\Payments\Api\PaymentsInterface, 
    $date
]

- onQuiqqerInvoiceAddPaymentEnd [
    Invoice, 
    $amount, 
    QUI\ERP\Accounting\Payments\Api\PaymentsInterface, 
    $date
]

#### Temporary Invoice Events

- onQuiqqerInvoiceTemporaryInvoicePostBegin [TemporaryInvoice]
- onQuiqqerInvoiceTemporaryInvoicePost [TemporaryInvoice]
- onQuiqqerInvoiceTemporaryInvoicePostEnd [TemporaryInvoice, Invoice]

- onQuiqqerInvoiceTemporaryInvoiceSaveBegin [TemporaryInvoice]
- onQuiqqerInvoiceTemporaryInvoiceSave [TemporaryInvoice]
- onQuiqqerInvoiceTemporaryInvoiceSaveEnd [TemporaryInvoice]

- onQuiqqerInvoiceTemporaryInvoiceCopy [TemporaryInvoice]
- onQuiqqerInvoiceTemporaryInvoiceCopyEnd [TemporaryInvoice, TemporaryInvoice $Copy]

- onQuiqqerInvoiceTemporaryInvoiceDelete [TemporaryInvoice]

- onQuiqqerInvoiceTemporaryInvoiceAddHistory [TemporaryInvoice, message]
- onQuiqqerInvoiceTemporaryInvoiceAddComment [TemporaryInvoice, message]
