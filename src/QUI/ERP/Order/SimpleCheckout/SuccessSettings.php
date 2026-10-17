<?php

namespace QUI\ERP\Order\SimpleCheckout;

/**
 * Normalize the checkout control's optional completion attributes.
 *
 * @phpstan-type CompletionSettings array{
 *     successContent: string|false,
 *     successUrl: string|false,
 *     successRedirectDelay: int|false
 * }
 */
final class SuccessSettings
{
    public const DEFAULTS = [
        'successContent' => false,
        'successUrl' => false,
        'successRedirectDelay' => false
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
            'successRedirectDelay' => $delay
        ];
    }
}
