<?php

namespace QUITests\ERP\Accounting\Invoice;

use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Invoice\Search\InvoiceSearch;
use QUI\ERP\Accounting\Invoice\Settings;

class InvoiceSearchUnitTest extends TestCase
{
    public function testQueryWithoutFiltersSupportsCountLimitAndOrdering(): void
    {
        $Search = $this->createSearch();

        $QueryBuilder = $Search->query();
        self::assertSame(0, $QueryBuilder->getFirstResult());
        self::assertSame(20, $QueryBuilder->getMaxResults());
        self::assertStringContainsString('ORDER BY', $QueryBuilder->getSQL());

        $CountQueryBuilder = $Search->query(true);
        self::assertNull($CountQueryBuilder->getMaxResults());
        self::assertStringContainsString('COUNT(', $CountQueryBuilder->getSQL());

        $Search->order('display_sum DESC');
        self::assertStringContainsString('sum', $Search->query()->getSQL());

        $Search->order('not_allowed DESC');
        self::assertStringContainsString('sum', $Search->query()->getSQL());

        $Search->limit('10', '5');
        self::assertSame(10, $Search->query()->getFirstResult());
        self::assertSame(5, $Search->query()->getMaxResults());

        $Search->noLimit();
        self::assertNull($Search->query()->getMaxResults());
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

        $QueryBuilder = $Search->query();
        $parameters = $QueryBuilder->getParameters();

        self::assertSame(date('Y-m-d 00:00:00', $timestamp), $parameters['filter0']);
        self::assertSame(date('Y-m-d 23:59:59', $timestamp), $parameters['filter1']);
        self::assertSame(QUI\ERP\Constants::PAYMENT_STATUS_OPEN, $parameters['filter2']);
        self::assertSame(QUI\ERP\Constants::PAYMENT_STATUS_PART, $parameters['filter3']);
        self::assertSame(QUI\ERP\Constants::PAYMENT_STATUS_PAID, $parameters['filter4']);
        self::assertSame(42, $parameters['filter5']);
        self::assertArrayHasKey('currency', $parameters);

        $Search->clearFilter();
        self::assertArrayNotHasKey('filter0', $Search->query()->getParameters());
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

            $parameters = $Search->query()->getParameters();

            self::assertArrayHasKey('searchId', $parameters);
            self::assertArrayHasKey('customerIdSearch', $parameters);
            self::assertArrayHasKey('search', $parameters);
        }
    }

    public function testQuerySupportsExactMatchFilters(): void
    {
        $Search = $this->createSearch();

        $Search->setFilter('customer_id', '123');
        $Search->setFilter('c_user', '12');
        $Search->setFilter('order_id', '34');
        $Search->setFilter('hash', '78');
        $Search->setFilter('isbrutto', '1');

        $parameters = $Search->query()->getParameters();

        self::assertCount(6, $parameters);
        self::assertContains('12', $parameters, true);
        self::assertContains('34', $parameters, true);
        self::assertContains('78', $parameters, true);
        self::assertContains(1, $parameters, true);
    }

    private function createSearch(): InvoiceSearch
    {
        return new class () extends InvoiceSearch {
            public function query(bool $count = false): QueryBuilder
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
