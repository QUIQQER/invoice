<?php

define('SYSTEM_INTERN', true);

require_once 'bootstrap.php';

// Order
$Order = QUI\ERP\Order\Handler::getInstance()->get('115');

echo $Order->getType();
echo PHP_EOL;

echo $Order->getUUID();
echo PHP_EOL;


// Payment
$Payment = QUI\ERP\Accounting\Payments\Payments::getInstance()->getPayment(23);
$Order->addPayment(1, $Payment->getPaymentType());

echo PHP_EOL;
echo PHP_EOL;
echo PHP_EOL;
