<?php
declare(strict_types=1);

namespace StorvixLabs\CartInsight\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Config
{
    private const PATH = 'storvixlabs_cartinsight/general/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getAbandonedAfterHours(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::PATH . 'abandoned_after_hours'));
    }

    public function getDefaultPeriodDays(): int
    {
        return max(1, (int)$this->scopeConfig->getValue(self::PATH . 'default_period_days'));
    }

    /**
     * Sorted, unique, positive upper limits of the cart value ranges.
     *
     * @return float[]
     */
    public function getValueBuckets(): array
    {
        $parts = preg_split('/[\s,;]+/', (string)$this->scopeConfig->getValue(self::PATH . 'value_buckets')) ?: [];
        $limits = [];
        foreach ($parts as $part) {
            if (is_numeric($part) && (float)$part > 0) {
                $limits[] = (float)$part;
            }
        }
        $limits = array_values(array_unique($limits));
        sort($limits);

        return array_slice($limits, 0, 10) ?: [50.0, 100.0, 250.0, 500.0, 1000.0];
    }

    public function getBaseCurrency(): string
    {
        return (string)($this->scopeConfig->getValue('currency/options/base') ?: 'USD');
    }

    public function getPaymentTitle(string $code): string
    {
        $title = trim((string)$this->scopeConfig->getValue('payment/' . $code . '/title'));

        return $title !== '' ? $title : $code;
    }
}
