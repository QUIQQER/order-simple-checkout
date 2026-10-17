<?php

namespace QUI\ERP\Order\SimpleCheckout;

use QUI;
use QUI\ERP\Order\AbstractOrder;
use QUI\ERP\Order\OrderInProcess;

/**
 * Order-specific configuration for the checkout's optional completion actions.
 *
 * @phpstan-type CompletionSettings array{
 *     successContent: string|false,
 *     successUrl: string|false,
 *     successRedirectDelay: int|false,
 *     successContentSource: 'php'|'frontend'
 * }
 */
final class SuccessSettings
{
    public const DATA_KEY = 'simpleCheckoutSuccess';
    public const DEFAULTS = [
        'successContent' => false,
        'successUrl' => false,
        'successRedirectDelay' => false,
        'successContentSource' => 'frontend'
    ];

    /**
     * @param array<string, mixed> $settings
     * @return CompletionSettings
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
            'successRedirectDelay' => $delay,
            'successContentSource' => ($settings['successContentSource'] ?? null) === 'php' ? 'php' : 'frontend'
        ];
    }

    /** @return CompletionSettings */
    public static function read(AbstractOrder $Order): array
    {
        $settings = $Order->getDataEntry(self::DATA_KEY);

        if (!is_array($settings)) {
            return self::DEFAULTS;
        }

        // Settings saved before frontend HTML support could only contain trusted PHP content.
        $settings['successContentSource'] ??= 'php';

        return self::normalize($settings);
    }

    /**
     * Frontend content always requires sanitization, regardless of any submitted source marker.
     *
     * @param array<string, mixed> $settings
     * @return CompletionSettings
     */
    public static function frontendSettings(array $settings): array
    {
        return self::normalize([
            'successContent' => $settings['successContent'] ?? false,
            'successUrl' => $settings['successUrl'] ?? false,
            'successRedirectDelay' => $settings['successRedirectDelay'] ?? false
        ]);
    }

    /**
     * Configure trusted PHP content. Request handlers must use configureFrontend() instead.
     * Defaults on a returning page never overwrite the settings saved before payment.
     *
     * @param array<string, mixed> $settings
     * @return CompletionSettings
     */
    public static function configure(AbstractOrder $Order, array $settings): array
    {
        $settings['successContentSource'] = 'php';

        return self::save($Order, $settings);
    }

    /**
     * @param array<string, mixed> $settings
     * @return CompletionSettings
     */
    public static function configureFrontend(AbstractOrder $Order, array $settings): array
    {
        return self::save($Order, self::frontendSettings($settings));
    }

    /**
     * @param array<string, mixed> $settings
     * @return CompletionSettings
     */
    private static function save(AbstractOrder $Order, array $settings): array
    {
        $normalized = self::normalize($settings);
        $stored = self::read($Order);

        if ($normalized['successContent'] === false) {
            $normalized['successContent'] = $stored['successContent'];
            $normalized['successContentSource'] = $stored['successContentSource'];
        }

        foreach (['successUrl', 'successRedirectDelay'] as $name) {
            if ($normalized[$name] === false) {
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
