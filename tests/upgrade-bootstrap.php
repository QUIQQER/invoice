<?php

declare(strict_types=1);

require_once __DIR__ . '/QUITests/ERP/Accounting/Invoice/DatabaseEnvironment.php';

if (!QUITests\ERP\Accounting\Invoice\DatabaseEnvironment::usesCiDatabase()) {
    throw new RuntimeException('Invoice installation and migration tests may only run in GitLab CI.');
}

require_once __DIR__ . '/upgrade/OrderDouble.php';
require_once __DIR__ . '/upgrade/OrderHandlerDouble.php';

if (class_exists(QUI\ERP\Order\Handler::class, false)) {
    throw new RuntimeException('The invoice upgrade suite requires an isolated Order handler boundary.');
}

class_alias(
    QUITests\ERP\Accounting\Invoice\Upgrade\OrderHandlerDouble::class,
    QUI\ERP\Order\Handler::class
);

require_once __DIR__ . '/phpunit-bootstrap.php';
