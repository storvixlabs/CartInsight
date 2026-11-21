<?php
declare(strict_types=1);

namespace StorvixLabs\CartInsight\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use StorvixLabs\CartInsight\Model\Config;
use StorvixLabs\CartInsight\Model\Report\Criteria;
use StorvixLabs\CartInsight\Model\Report\DataProvider;

class Report implements ArgumentInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $cache = [];

    public function __construct(
        private readonly RequestInterface $request,
        private readonly Config $config,
        private readonly DataProvider $dataProvider,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    public function getCriteria(): Criteria
    {
        return $this->cache['criteria'] ??= Criteria::fromRequest(
            (string)$this->request->getParam('from', ''),
            (string)$this->request->getParam('to', ''),
            (int)$this->request->getParam('hours', 0) ?: $this->config->getAbandonedAfterHours(),
            $this->config->getDefaultPeriodDays()
        );
    }

    /**
     * @return array{carts: int, value: float, average: float, with_email: int, orders: int, rate: float}
     */
    public function getSummary(): array
    {
        return $this->cache['summary'] ??= $this->dataProvider->getSummary($this->getCriteria());
    }

    /**
     * @return array<int, array{label: string, reached: int, lost: int, share: float}>
     */
    public function getFunnel(): array
    {
        return $this->cache['funnel'] ??= $this->dataProvider->getFunnel($this->getCriteria());
    }

    /**
     * @return array<int, array{label: string, carts: int, value: float}>
     */
    public function getValueRanges(): array
    {
        return $this->cache['ranges'] ??= $this->dataProvider->getValueRanges($this->getCriteria());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPaymentMethods(): array
    {
        return $this->cache['methods'] ??= $this->dataProvider->getPaymentMethods($this->getCriteria());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCarts(int $limit = 50): array
    {
        return $this->cache['carts' . $limit] ??= $this->dataProvider->getCarts($this->getCriteria(), $limit);
    }

    public function formatMoney(float $baseAmount): string
    {
        return $this->priceCurrency->format($baseAmount, false, 2, null, $this->config->getBaseCurrency());
    }

    public function formatPercent(float $value): string
    {
        return number_format($value, 1) . '%';
    }
}
