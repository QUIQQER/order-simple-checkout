<?php

/**
 * This file contains the simple checkout site type
 *
 * @var QUI\Projects\Project $Project
 * @var QUI\Projects\Site $Site
 * @var QUI\Interfaces\Template\EngineInterface $Engine
 * @var QUI\Template $Template
 */

$Site->setAttribute('nocache', true);

try {
    $Checkout = new QUI\ERP\Order\SimpleCheckout\Checkout([
        'data-qui-load-hash-from-url' => 1
    ]);

    $Engine->assign([
        'Checkout' => $Checkout
    ]);
} catch (QUI\Database\Exception $Exception) {
    $ExceptionReplacement = new QUI\Exception(['quiqqer/quiqqer', 'exception.error']);

    QUI\System\Log::writeException($Exception);

    $Engine->assign([
        'Exception' => $ExceptionReplacement
    ]);
} catch (Exception $Exception) {
    $Engine->assign([
        'Exception' => $Exception
    ]);
}
