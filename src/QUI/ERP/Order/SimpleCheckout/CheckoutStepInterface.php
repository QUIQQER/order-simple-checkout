<?php

namespace QUI\ERP\Order\SimpleCheckout;


interface CheckoutStepInterface
{
    public function __construct(Checkout $Checkout, $attributes = []);
}