<?php
declare(strict_types=1);

namespace StorvixLabs\CartInsight\Model\Report;

/**
 * Report period (database dates, UTC) and the age after which a cart counts as abandoned.
 */
class Criteria
{
    public function __construct(
        public readonly string $from,
        public readonly string $to,
        public readonly int $abandonedAfterHours
    ) {
    }

    /**
     * Invalid or missing dates fall back to the last $defaultDays days.
     */
    public static function fromRequest(
        string $from,
        string $to,
        int $abandonedAfterHours,
        int $defaultDays,
        ?int $now = null
    ): self {
        $now ??= time();
        $toTime = self::parse($to) ?? $now;
        $fromTime = self::parse($from) ?? ($toTime - $defaultDays * 86400);
        if ($fromTime > $toTime) {
            [$fromTime, $toTime] = [$toTime, $fromTime];
        }

        return new self(gmdate('Y-m-d', $fromTime), gmdate('Y-m-d', $toTime), $abandonedAfterHours);
    }

    /**
     * Start of the first day, as a database timestamp.
     */
    public function fromTimestamp(): string
    {
        return $this->from . ' 00:00:00';
    }

    /**
     * End of the last day, but never later than "now minus abandoned-after".
     */
    public function toTimestamp(?int $now = null): string
    {
        $limit = gmdate('Y-m-d H:i:s', ($now ?? time()) - $this->abandonedAfterHours * 3600);
        $end = $this->to . ' 23:59:59';

        return min($end, $limit);
    }

    private static function parse(string $date): ?int
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }
        $time = strtotime($date . ' 00:00:00 UTC');

        return $time === false ? null : $time;
    }
}
