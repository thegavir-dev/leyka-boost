<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('LeykaUTMTrackerUTM')) {
    class LeykaUTMTrackerUTM {

        protected static function set_cookie($name, $value) {
            if (headers_sent()) {
                return;
            }

            setcookie(
                $name,
                $value,
                array(
                    'expires'  => time() + (30 * DAY_IN_SECONDS),
                    'path'     => COOKIEPATH ? COOKIEPATH : '/',
                    'domain'   => COOKIE_DOMAIN,
                    'secure'   => is_ssl(),
                    'httponly' => false,
                    'samesite' => 'Lax',
                )
            );

            $_COOKIE[$name] = $value;
        }

        public static function capture_utm() {
            if (is_admin()) {
                return;
            }

            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only UTM parameters, sanitized via sanitize_text_field().
            $source   = !empty($_GET['utm_source']) ? sanitize_text_field(wp_unslash($_GET['utm_source'])) : '';
            $medium   = !empty($_GET['utm_medium']) ? sanitize_text_field(wp_unslash($_GET['utm_medium'])) : '';
            $campaign = !empty($_GET['utm_campaign']) ? sanitize_text_field(wp_unslash($_GET['utm_campaign'])) : '';
            // phpcs:enable WordPress.Security.NonceVerification.Recommended

            // FIRST touch: set all three cookies atomically on the first UTM visit.
            // We use leyka_utm_first_source as the single gate — once it is set,
            // none of the first-touch cookies are ever overwritten, preventing
            // mixed attribution from different visits.
            if ($source && empty($_COOKIE['leyka_utm_first_source'])) {
                self::set_cookie('leyka_utm_first_source', $source);
                self::set_cookie('leyka_utm_first_medium', $medium);
                self::set_cookie('leyka_utm_first_campaign', $campaign);
            }

            // LAST touch: always overwrite all three atomically when a new UTM set is present.
            if ($source) {
                self::set_cookie('leyka_utm_last_source', $source);
                self::set_cookie('leyka_utm_last_medium', $medium);
                self::set_cookie('leyka_utm_last_campaign', $campaign);
            }
        }
    }
}
