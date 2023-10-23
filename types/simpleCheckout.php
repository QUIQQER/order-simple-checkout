<?php

$Site->setAttribute('nocache', true);

try {
    $Checkout = new QUI\ERP\Order\SimpleCheckout\Checkout([
    ]);

    $Engine->assign([
        'Checkout' => $Checkout
    ]);
} catch (QUI\DataBase\Exception $Exception) {
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
