<?php
declare(strict_types=1);

namespace StorvixLabs\CartInsight\Model\Report;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use StorvixLabs\CartInsight\Model\Config;

/**
 * Reads the report numbers straight from the quote and order tables with a few aggregate queries.
 *
 * A cart is abandoned when it is still active (never turned into an order), has items, and was last touched
 * inside the period but at least "abandoned after" hours ago. The furthest checkout step it reached is derived
 * from its data: address filled in, shipping method chosen, payment method chosen.
 */
class DataProvider
{
    /**
     * Orders in these states count as a failed payment: canceled or never confirmed by the gateway.
     */
    private const FAILED_STATES = ['canceled', 'pending_payment', 'payment_review'];

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Config $config
    ) {
    }

    /**
     * @return array{carts: int, value: float, average: float, with_email: int, orders: int, rate: float}
     */
    public function getSummary(Criteria $criteria): array
    {
        [$sql, $bind] = $this->baseQuery($criteria);
        $row = $this->connection()->fetchRow(
            "SELECT COUNT(*) AS carts, COALESCE(SUM(t.value), 0) AS value,
                    SUM(CASE WHEN t.customer_email IS NOT NULL AND t.customer_email <> '' THEN 1 ELSE 0 END) AS with_email
             FROM ($sql) t",
            $bind
        ) ?: [];
        $carts = (int)($row['carts'] ?? 0);
        $value = (float)($row['value'] ?? 0);
        $orders = $this->countOrders($criteria);

        return [
            'carts' => $carts,
            'value' => $value,
            'average' => $carts > 0 ? $value / $carts : 0.0,
            'with_email' => (int)($row['with_email'] ?? 0),
            'orders' => $orders,
            'rate' => ($carts + $orders) > 0 ? $carts / ($carts + $orders) * 100 : 0.0,
        ];
    }

    /**
     * One row per checkout step, from the cart to the placed order: how many carts reached it and were lost there.
     *
     * @return array<int, array{label: string, reached: int, lost: int, share: float}>
     */
    public function getFunnel(Criteria $criteria): array
    {
        [$sql, $bind] = $this->baseQuery($criteria);
        $lostAt = [];
        foreach ($this->connection()->fetchAll("SELECT t.stage, COUNT(*) AS carts FROM ($sql) t GROUP BY t.stage", $bind) as $row) {
            $lostAt[(int)$row['stage']] = (int)$row['carts'];
        }
        $orders = $this->countOrders($criteria);
        $labels = Stage::labels();
        $total = array_sum($lostAt) + $orders;

        $rows = [];
        $reached = $total;
        foreach ($labels as $stage => $label) {
            $lost = $lostAt[$stage] ?? 0;
            $rows[] = [
                'label' => $label,
                'reached' => $reached,
                'lost' => $lost,
                'share' => $reached > 0 ? $lost / $reached * 100 : 0.0,
            ];
            $reached -= $lost;
        }
        $rows[] = ['label' => (string)__('Placed an order'), 'reached' => $orders, 'lost' => 0, 'share' => 0.0];

        return $rows;
    }

    /**
     * @return array<int, array{label: string, carts: int, value: float}>
     */
    public function getValueRanges(Criteria $criteria): array
    {
        $limits = $this->config->getValueBuckets();
        $case = '';
        foreach ($limits as $index => $limit) {
            $case .= sprintf('WHEN t.value < %s THEN %d ', $this->number($limit), $index);
        }
        $case = 'CASE ' . $case . 'ELSE ' . count($limits) . ' END';
        [$sql, $bind] = $this->baseQuery($criteria);
        $found = [];
        foreach ($this->connection()->fetchAll(
            "SELECT $case AS bucket, COUNT(*) AS carts, COALESCE(SUM(t.value), 0) AS value FROM ($sql) t GROUP BY bucket",
            $bind
        ) as $row) {
            $found[(int)$row['bucket']] = $row;
        }

        $rows = [];
        for ($i = 0; $i <= count($limits); $i++) {
            $rows[] = [
                'label' => $this->rangeLabel($limits, $i),
                'carts' => (int)($found[$i]['carts'] ?? 0),
                'value' => (float)($found[$i]['value'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Per payment method: carts lost after choosing it, orders placed with it, and how many of those orders failed.
     *
     * @return array<int, array{code: string, title: string, abandoned: int, abandoned_value: float, orders: int, failed: int, failure_rate: float}>
     */
    public function getPaymentMethods(Criteria $criteria): array
    {
        $methods = [];
        [$sql, $bind] = $this->baseQuery($criteria);
        foreach ($this->connection()->fetchAll(
            "SELECT t.payment_method AS method, COUNT(*) AS carts, COALESCE(SUM(t.value), 0) AS value
             FROM ($sql) t WHERE t.payment_method IS NOT NULL AND t.payment_method <> '' GROUP BY t.payment_method",
            $bind
        ) as $row) {
            $methods[$row['method']]['abandoned'] = (int)$row['carts'];
            $methods[$row['method']]['abandoned_value'] = (float)$row['value'];
        }

        $failed = "'" . implode("','", self::FAILED_STATES) . "'";
        $orders = $this->connection()->fetchAll(
            "SELECT p.method AS method, COUNT(*) AS orders,
                    SUM(CASE WHEN o.state IN ($failed) THEN 1 ELSE 0 END) AS failed
             FROM {$this->table('sales_order')} o
             JOIN {$this->table('sales_order_payment')} p ON p.parent_id = o.entity_id
             WHERE o.created_at BETWEEN :from AND :to AND p.method IS NOT NULL
             GROUP BY p.method",
            ['from' => $criteria->fromTimestamp(), 'to' => $this->orderPeriodEnd($criteria)]
        );
        foreach ($orders as $row) {
            $methods[$row['method']]['orders'] = (int)$row['orders'];
            $methods[$row['method']]['failed'] = (int)$row['failed'];
        }

        $rows = [];
        foreach ($methods as $code => $data) {
            $placed = $data['orders'] ?? 0;
            $rows[] = [
                'code' => (string)$code,
                'title' => $this->config->getPaymentTitle((string)$code),
                'abandoned' => $data['abandoned'] ?? 0,
                'abandoned_value' => $data['abandoned_value'] ?? 0.0,
                'orders' => $placed,
                'failed' => $data['failed'] ?? 0,
                'failure_rate' => $placed > 0 ? ($data['failed'] ?? 0) / $placed * 100 : 0.0,
            ];
        }
        usort($rows, static fn (array $a, array $b): int => $b['abandoned'] + $b['failed'] <=> $a['abandoned'] + $a['failed']);

        return $rows;
    }

    /**
     * Most valuable abandoned carts first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCarts(Criteria $criteria, int $limit = 50): array
    {
        [$sql, $bind] = $this->baseQuery($criteria);
        $rows = $this->connection()->fetchAll(
            "SELECT t.* FROM ($sql) t ORDER BY t.value DESC, t.quote_id DESC LIMIT " . max(1, $limit),
            $bind
        );
        $labels = Stage::labels();
        foreach ($rows as &$row) {
            $row['stage_label'] = $labels[(int)$row['stage']] ?? '';
            $row['payment_title'] = $row['payment_method'] ? $this->config->getPaymentTitle((string)$row['payment_method']) : '';
        }

        return $rows;
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function baseQuery(Criteria $criteria): array
    {
        $sql = "SELECT q.entity_id AS quote_id, q.updated_at, q.customer_id, q.customer_email,
                       q.items_count, q.items_qty,
                       COALESCE(NULLIF(q.base_grand_total, 0), q.base_subtotal, 0) AS value,
                       qp.method AS payment_method,
                       CASE
                           WHEN COALESCE(qp.method, '') <> '' THEN " . Stage::PAYMENT . "
                           WHEN q.is_virtual = 0 AND COALESCE(qa.shipping_method, '') <> '' THEN " . Stage::SHIPPING . "
                           WHEN COALESCE(qa.street, '') <> '' AND COALESCE(qa.firstname, '') <> '' THEN " . Stage::ADDRESS . "
                           ELSE " . Stage::CART . "
                       END AS stage
                FROM {$this->table('quote')} q
                LEFT JOIN {$this->table('quote_address')} qa
                       ON qa.quote_id = q.entity_id AND qa.address_type = IF(q.is_virtual = 1, 'billing', 'shipping')
                LEFT JOIN {$this->table('quote_payment')} qp ON qp.quote_id = q.entity_id
                WHERE q.is_active = 1 AND q.items_count > 0 AND q.updated_at BETWEEN :from AND :to";

        return [$sql, ['from' => $criteria->fromTimestamp(), 'to' => $criteria->toTimestamp()]];
    }

    private function countOrders(Criteria $criteria): int
    {
        return (int)$this->connection()->fetchOne(
            "SELECT COUNT(*) FROM {$this->table('sales_order')} WHERE created_at BETWEEN :from AND :to",
            ['from' => $criteria->fromTimestamp(), 'to' => $this->orderPeriodEnd($criteria)]
        );
    }

    private function orderPeriodEnd(Criteria $criteria): string
    {
        return $criteria->to . ' 23:59:59';
    }

    /**
     * @param float[] $limits
     */
    private function rangeLabel(array $limits, int $index): string
    {
        $currency = $this->config->getBaseCurrency();
        if ($index === 0) {
            return (string)__('Under %1 %2', $this->number($limits[0]), $currency);
        }
        if ($index === count($limits)) {
            return (string)__('%1 %2 or more', $this->number($limits[$index - 1]), $currency);
        }

        return (string)__('%1 – %2 %3', $this->number($limits[$index - 1]), $this->number($limits[$index]), $currency);
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    private function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }

    private function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }
}
