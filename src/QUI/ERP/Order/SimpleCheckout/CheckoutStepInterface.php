<?php

namespace QUI\ERP\Order\SimpleCheckout;

/**
 * Interface CheckoutStepInterface
 *
 * This interface represents a checkout step in a checkout process.
 * It defines the method that any checkout step must implement.
 */
interface CheckoutStepInterface
{
    /**
     * Constructor for the class.
     *
     * @param Checkout $Checkout An instance of the Checkout class.
     * @param array $attributes [optional] An array of attributes (default: empty array).
     */
    public function __construct(Checkout $Checkout, $attributes = []);
}
