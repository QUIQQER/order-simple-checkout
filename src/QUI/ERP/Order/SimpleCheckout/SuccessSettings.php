<?php

namespace QUI\ERP\Order\SimpleCheckout;

use QUI;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\OrderInProcess;

/** Safe, order-specific configuration for the checkout's optional completion actions. */
final class SuccessSettings
{
    public const DATA_KEY = 'simpleCheckoutSuccess';
    public const DEFAULTS = [
        'successContent' => false,
        'successUrl' => false,
        'successRedirectDelay' => false
    ];

    /**
     * @param array<string, mixed> $settings
     * @return array{successContent: string|false, successUrl: string|false, successRedirectDelay: int|false}
     */
    public static function normalize(array $settings): array
    {
        $content = $settings['successContent'] ?? false;

        $url = $settings['successUrl'] ?? false;

        if (
            !is_string($url)
            || $url === ''
            || preg_match('/[\x00-\x20\x7f\\\\]/', $url)
            || str_starts_with($url, '//')
        ) {
            $url = false;
        } elseif (!str_starts_with($url, '/')) {
            $scheme = parse_url($url, PHP_URL_SCHEME);

            if (
                !in_array($scheme, ['https', 'http'], true)
                || filter_var($url, FILTER_VALIDATE_URL) === false
            ) {
                $url = false;
            }
        }

        $delay = $settings['successRedirectDelay'] ?? false;

        if (is_int($delay) || is_string($delay)) {
            $delay = filter_var($delay, FILTER_VALIDATE_INT, [
                'options' => [
                    'min_range' => 0,
                    'max_range' => 86400
                ]
            ]);
        } else {
            $delay = false;
        }

        return [
            'successContent' => is_string($content) && trim($content) !== '' ? $content : false,
            'successUrl' => $url,
            'successRedirectDelay' => $delay
        ];
    }

    /** @return array{successContent: string|false, successUrl: string|false, successRedirectDelay: int|false} */
    public static function read(AbstractOrder $Order): array
    {
        $settings = $Order->getDataEntry(self::DATA_KEY);

        return is_array($settings) ? self::normalize($settings) : self::DEFAULTS;
    }

    /**
     * HTML is configured exclusively by trusted PHP code, never through frontend requests.
     *
     * @param array<string, mixed> $settings
     * @return array{successUrl: string|false, successRedirectDelay: int|false}
     */
    public static function frontendSettings(array $settings): array
    {
        $normalized = self::normalize([
            'successUrl' => $settings['successUrl'] ?? false,
            'successRedirectDelay' => $settings['successRedirectDelay'] ?? false
        ]);

        return [
            'successUrl' => $normalized['successUrl'],
            'successRedirectDelay' => $normalized['successRedirectDelay']
        ];
    }

    /**
     * Configure from trusted PHP. Request handlers must use frontendSettings() first.
     * Defaults on a returning page never overwrite the settings saved before payment.
     *
     * @param array<string, mixed> $settings
     * @return array{successContent: string|false, successUrl: string|false, successRedirectDelay: int|false}
     */
    public static function configure(AbstractOrder $Order, array $settings): array
    {
        $normalized = self::normalize($settings);
        $stored = self::read($Order);

        foreach ($normalized as $name => $value) {
            if ($value === false) {
                $normalized[$name] = $stored[$name];
            }
        }

        if (
            $normalized === $stored
            || !$Order instanceof OrderInProcess
            || $Order->getOrderId()
            || $Order->getDataEntry('orderedWithCosts')
        ) {
            return $stored;
        }

        $User = QUI::getUserBySession();

        if ($Order->getCustomer()->getUUID() !== $User->getUUID()) {
            throw new QUI\Exception(['quiqqer/order', 'exception.no.permission.for.this.order'], 403);
        }

        $Order->setData(self::DATA_KEY, $normalized);
        $Order->save($User);

        return $normalized;
    }
}
