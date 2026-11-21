<?php
declare(strict_types=1);

namespace StorvixLabs\CartInsight\Model\Report;

/**
 * How far a customer got before leaving.
 */
class Stage
{
    public const CART = 1;
    public const ADDRESS = 2;
    public const SHIPPING = 3;
    public const PAYMENT = 4;

    /**
     * @return array<int, string>
     */
    public static function labels(): array
    {
        return [
            self::CART => (string)__('Left at the cart'),
            self::ADDRESS => (string)__('Entered an address'),
            self::SHIPPING => (string)__('Chose a shipping method'),
            self::PAYMENT => (string)__('Chose a payment method'),
        ];
    }
}
