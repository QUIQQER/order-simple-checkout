<?php

/**
 * This file contains a product landing page
 *
 * @var QUI\Projects\Project $Project
 * @var QUI\Projects\Site $Site
 * @var QUI\Interfaces\Template\EngineInterface $Engine
 * @var QUI\Template $Template
 */

use QUI\ERP\Products\Handler\Products;

$productId = $Site->getAttribute('order.simple.productLandingPage.productId');
$ctaUrl = $Site->getAttribute('order.simple.productLandingPage.ctaUrl');

if (empty($productId)) {
    $Engine->assign('Product', null);
} else {
    try {
        $Product = Products::getProduct($productId);
        $ProductControl = new QUI\ERP\Products\Controls\Products\Product([
            'Product' => $Product
        ]);

        $Engine->assign('ProductControl', $ProductControl);
    } catch (\QUI\ERP\Products\Product\Exception) {
        $Engine->assign('Product', null);
    }
}

if ($ctaUrl) {
    $ctaUrl = filter_var($ctaUrl, FILTER_SANITIZE_URL);
    $Template->extendHeader('<script>QUIQQER_LANDING_PAGE_CTA_URL = "' . $ctaUrl . '";</script>');
}
