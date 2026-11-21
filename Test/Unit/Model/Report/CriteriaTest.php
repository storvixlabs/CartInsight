<?php
declare(strict_types=1);

namespace StorvixLabs\CartInsight\Test\Unit\Model\Report;

use PHPUnit\Framework\TestCase;
use StorvixLabs\CartInsight\Model\Report\Criteria;

class CriteriaTest extends TestCase
{
    private const NOW = 1_800_000_000; // 2027-01-15 08:00:00 UTC

    public function testMissingDatesDefaultToThePastPeriod(): void
    {
        $criteria = Criteria::fromRequest('', '', 1, 30, self::NOW);

        $this->assertSame('2027-01-15', $criteria->to);
        $this->assertSame('2026-12-16', $criteria->from);
    }

    public function testInvalidDatesAreIgnoredAndSwappedDatesCorrected(): void
    {
        $this->assertSame('2027-01-15', Criteria::fromRequest('x', "' OR 1=1", 1, 7, self::NOW)->to);

        $criteria = Criteria::fromRequest('2026-03-10', '2026-03-01', 1, 7, self::NOW);
        $this->assertSame(['2026-03-01', '2026-03-10'], [$criteria->from, $criteria->to]);
    }

    public function testPeriodEndNeverReachesCartsThatAreTooFreshToBeAbandoned(): void
    {
        $criteria = new Criteria('2027-01-01', '2027-01-15', 2);

        $this->assertSame('2027-01-15 06:00:00', $criteria->toTimestamp(self::NOW));
        $this->assertSame('2027-01-10 23:59:59', (new Criteria('2027-01-01', '2027-01-10', 2))->toTimestamp(self::NOW));
    }
}
