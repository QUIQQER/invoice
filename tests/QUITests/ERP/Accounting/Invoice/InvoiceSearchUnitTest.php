<?php

namespace QUITests\ERP\Accounting\Invoice;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Invoice\Search\InvoiceSearch;
use QUI\ERP\Accounting\Invoice\Settings;

class InvoiceSearchUnitTest extends TestCase
{
    public function testQueryWithoutFiltersSupportsCountLimitAndOrdering(): void
    {
        $Search = $this->createSearch();

        self::assertStringContainsString('SELECT id', $Search->query()['query']);
        self::assertStringContainsString('LIMIT 0,20', $Search->query()['query']);
        self::assertStringContainsString('COUNT(*)', $Search->query(true)['query']);

        $Search->order('display_sum DESC');
        self::assertStringContainsString('ORDER BY sum DESC', $Search->query()['query']);

        $Search->order('not_allowed DESC');
        self::assertStringContainsString('ORDER BY sum DESC', $Search->query()['query']);

        $Search->limit('10', '5');
        self::assertStringContainsString('LIMIT 10,5', $Search->query()['query']);

        $Search->noLimit();
        self::assertStringNotContainsString('LIMIT', $Search->query()['query']);
        self::assertContains('paid_status', $Search->allowedFields());
    }

    public function testQueryBuildsSupportedFiltersAndIgnoresInvalidValues(): void
    {
        $Search = $this->createSearch();
        $timestamp = strtotime('2026-07-17 12:00:00');

        $Search->setFilter('unknown', 'ignored');
        $Search->setFilter('from', '');
        $Search->setFilter('from', (string)$timestamp);
        $Search->setFilter('to', (string)$timestamp);
        $Search->setFilter('paid_status', '');
        $Search->setFilter('paid_status', '999');
        $Search->setFilter('paid_status', (string)QUI\ERP\Constants::PAYMENT_STATUS_OPEN);
        $Search->setFilter('paid_status', (string)QUI\ERP\Constants::PAYMENT_STATUS_PAID);
        $Search->setFilter('id', '42');
        $Search->setFilter('currency', '---');

        $query = $Search->query();

        self::assertStringContainsString('date >= :filter0', $query['query']);
        self::assertStringContainsString('date <= :filter1', $query['query']);
        self::assertStringContainsString('paid_status = :filter2', $query['query']);
        self::assertStringContainsString('paid_status = :filter3', $query['query']);
        self::assertStringContainsString('id = :filter5', $query['query']);
        self::assertArrayHasKey(':currency', $query['binds']);

        $Search->clearFilter();
        self::assertStringNotContainsString('date >=', $Search->query()['query']);
    }

    public function testSearchTextSupportsInvoiceTemporaryAndGenericPrefixes(): void
    {
        $searchValues = [
            Settings::getInstance()->getInvoicePrefix() . '123',
            Settings::getInstance()->getTemporaryInvoicePrefix() . '456',
            'customer or project'
        ];

        foreach ($searchValues as $searchValue) {
            $Search = $this->createSearch();
            $Search->setFilter('search', $searchValue);
            $Search->setFilter('currency', QUI\ERP\Defaults::getCurrency()->getCode());

            $query = $Search->query();

            self::assertStringContainsString('global_process_id LIKE :search', $query['query']);
            self::assertArrayHasKey('searchId', $query['binds']);
            self::assertArrayHasKey('customerIdSearch', $query['binds']);
            self::assertArrayHasKey('search', $query['binds']);
        }
    }

    private function createSearch(): InvoiceSearch
    {
        return new class () extends InvoiceSearch {
            /**
             * @return array{query: string, binds: array<mixed>}
             */
            public function query(bool $count = false): array
            {
                return $this->getQuery($count);
            }

            /**
             * @return array<int, string>
             */
            public function allowedFields(): array
            {
                return $this->getAllowedFields();
            }
        };
    }
}
