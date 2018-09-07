<?php

require 'bootstrap.php';


$Invoice = QUI\ERP\Accounting\Invoice\Handler::getInstance()->getInvoice(2);

QUI\ERP\Accounting\Calc::calculatePayments($Invoice);
