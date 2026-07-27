<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Invoice\DemoData;

use DateTimeImmutable;
use QUI;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\Invoice\Factory;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\DemoData\Contract\DemoDataCreatorInterface;
use QUI\ERP\DemoData\DTO\CreatedDemoData;
use QUI\ERP\DemoData\DTO\CreatedDemoDataCollection;
use QUI\ERP\DemoData\DTO\DemoDataCreationContext;
use QUI\ERP\DemoData\DTO\DemoDataDateRange;
use QUI\ERP\DemoData\DTO\DemoDataReference;
use QUI\ERP\DemoData\DTO\DemoDataReferenceCollection;
use QUI\ERP\DemoData\Exception\DemoDataException;

final class InvoiceDemoDataCreator implements DemoDataCreatorInterface
{
    private const PROVIDER_IDENTIFIER = 'quiqqer.invoice';
    private const ENTITY_TYPE = 'invoice_temporary';

    public function getDependencies(): array
    {
        return ['quiqqer.customer'];
    }

    public function createDemoData(DemoDataCreationContext $context): CreatedDemoDataCollection
    {
        $privateCustomer = $this->getCustomerReference($context, 'private_customer');
        $businessCustomer = $this->getCustomerReference($context, 'business_customer');
        $dateRanges = $context->getDateRanges();

        if ($dateRanges === []) {
            $now = new DateTimeImmutable();
            $dateRanges = [new DemoDataDateRange($now, $now)];
        }

        $createdDemoData = [];

        foreach ($dateRanges as $index => $dateRange) {
            $rangeNumber = $index + 1;
            $createdDemoData[] = $this->createInvoice(
                $privateCustomer,
                $dateRange->startDate,
                'Demo consulting service',
                "private_invoice_$rangeNumber"
            );
            $createdDemoData[] = $this->createInvoice(
                $businessCustomer,
                $dateRange->endDate,
                'Demo software subscription',
                "business_invoice_$rangeNumber"
            );
        }

        return new CreatedDemoDataCollection($createdDemoData);
    }

    public function deleteDemoData(DemoDataReferenceCollection $demoData): void
    {
        $systemUser = QUI::getUsers()->getSystemUser();

        foreach ($demoData->forProvider(self::PROVIDER_IDENTIFIER) as $reference) {
            if ($reference->entityType !== self::ENTITY_TYPE) {
                throw new DemoDataException('Invoice demo data reference has an invalid entity type.');
            }

            Handler::getInstance()->delete($reference->entityUuid, $systemUser);
        }
    }

    private function createInvoice(
        DemoDataReference $customerReference,
        DateTimeImmutable $date,
        string $articleTitle,
        string $referenceKey
    ): CreatedDemoData {
        $systemUser = QUI::getUsers()->getSystemUser();
        $invoice = Factory::getInstance()->createInvoice($systemUser);
        $customer = QUI::getUsers()->get($customerReference->entityUuid);
        $address = $customer->getStandardAddress();

        if ($address === null) {
            throw new DemoDataException('Invoice demo data customer has no standard address.');
        }

        $invoice->setCustomer($customer);
        $invoice->setAttribute('invoice_address_id', $address->getUUID());
        $invoice->setAttribute('invoice_address', $address->toJSON());
        $invoice->setAttribute('payment_method', -1);
        $invoice->setAttribute(InvoiceTemporary::SPECIAL_ATTRIBUTE_DO_NOT_SEND_CREATION_MAIL, 1);
        $invoice->setAttribute('date', $date->format('Y-m-d H:i:s'));
        $invoice->addArticle(new Article([
            'id' => 1,
            'articleNo' => 'DEMO-' . strtoupper($referenceKey),
            'title' => $articleTitle,
            'unitPrice' => 99,
            'quantity' => 1,
            'vat' => 19
        ]));
        $invoice->save($systemUser);

        return new CreatedDemoData(self::ENTITY_TYPE, $invoice->getUUID(), $referenceKey);
    }

    private function getCustomerReference(DemoDataCreationContext $context, string $referenceKey): DemoDataReference
    {
        foreach ($context->getDependencyData('quiqqer.customer') as $reference) {
            if ($reference->referenceKey === $referenceKey && $reference->entityType === 'customer') {
                return $reference;
            }
        }

        throw new DemoDataException("Customer demo data reference '$referenceKey' is missing.");
    }
}
