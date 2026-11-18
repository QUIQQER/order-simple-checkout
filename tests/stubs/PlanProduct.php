<?php

declare(strict_types=1);

namespace QUI\ERP\Plans;

use QUI\ERP\Products\Product\Types\DigitalProduct;

if (!class_exists(PlanProduct::class)) {
    class PlanProduct extends DigitalProduct
    {
    }
}
