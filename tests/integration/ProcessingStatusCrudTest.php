<?php

namespace QUITests\ERP\Accounting\Invoice\Integration;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Invoice\ProcessingStatus\Exception;
use QUI\ERP\Accounting\Invoice\ProcessingStatus\Factory;
use QUI\ERP\Accounting\Invoice\ProcessingStatus\Handler;
use Throwable;

class ProcessingStatusCrudTest extends TestCase
{
    public function testChangesAreImmediatelyVisibleThroughHandlerCache(): void
    {
        $Factory = Factory::getInstance();
        $Handler = Handler::getInstance();
        $statusId = $Factory->getNextId() + 1000;
        $titles = [];

        foreach (QUI::availableLanguages() as $language) {
            $titles[$language] = 'PHPUnit processing status';
        }

        try {
            $Factory->createProcessingStatus(
                $statusId,
                '#123456',
                $titles,
                [Handler::STATUS_OPTION_PREVENT_INVOICE_POSTING => false]
            );

            $CreatedStatus = $Handler->getProcessingStatus($statusId);
            self::assertSame('#123456', $CreatedStatus->getColor());

            try {
                $Factory->createProcessingStatus($statusId, '#000000', $titles);
                self::fail('Creating the same processing status twice must fail.');
            } catch (Exception) {
                self::assertTrue(true);
            }

            $Handler->updateProcessingStatus(
                $statusId,
                '#654321',
                $titles,
                [Handler::STATUS_OPTION_PREVENT_INVOICE_POSTING => true]
            );

            $UpdatedStatus = $Handler->getProcessingStatus($statusId);
            self::assertSame('#654321', $UpdatedStatus->getColor());
            self::assertTrue($UpdatedStatus->getOption(Handler::STATUS_OPTION_PREVENT_INVOICE_POSTING));

            $Handler->deleteProcessingStatus($statusId);

            $this->expectException(Exception::class);
            $Handler->getProcessingStatus($statusId);
        } finally {
            try {
                $Handler->deleteProcessingStatus($statusId);
            } catch (Throwable) {
            }
        }
    }
}
