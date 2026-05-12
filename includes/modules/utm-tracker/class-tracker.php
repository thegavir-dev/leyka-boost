<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('LeykaUTMTrackerTracker')) {
    class LeykaUTMTrackerTracker {

        protected static function get_cookie_value($name, $fallback = '') {
            return isset($_COOKIE[$name]) ? sanitize_text_field(wp_unslash($_COOKIE[$name])) : $fallback;
        }

        public static function get_amount_from_meta($post_id) {
            $meta = get_post_meta($post_id);
            if (!is_array($meta) || empty($meta)) {
                leyka_boost_utm_log('No meta found for donation ID=' . $post_id, 'WARN');
                return 0;
            }

            $preferred_keys = array(
                'leyka_donation_amount',
                'leyka_amount',
                '_leyka_amount',
                '_amount',
                'amount',
                'donation_amount',
                'payment_amount',
                '_amount_total',
                'sum',
                'total',
                'price',
            );

            foreach ($preferred_keys as $key) {
                if (!empty($meta[$key][0]) && is_numeric($meta[$key][0])) {
                    $amount = (float) $meta[$key][0];
                    if ($amount > 0) {
                        leyka_boost_utm_log('Amount key detected: ' . $key . ' = ' . $amount);
                        return $amount;
                    }
                }
            }

            foreach ($meta as $key => $value) {
                $key_l = strtolower((string) $key);

                if (
                    strpos($key_l, 'amount') === false &&
                    strpos($key_l, 'sum') === false &&
                    strpos($key_l, 'total') === false &&
                    strpos($key_l, 'price') === false
                ) {
                    continue;
                }

                $candidate = is_array($value) ? reset($value) : $value;
                if (is_numeric($candidate)) {
                    $candidate = (float) $candidate;
                    if ($candidate > 0) {
                        leyka_boost_utm_log('Amount key detected: ' . $key . ' = ' . $candidate);
                        return $candidate;
                    }
                }
            }

            leyka_boost_utm_log('Amount not found for donation ID=' . $post_id, 'WARN');
            return 0;
        }

        protected static function get_existing_row($donation_id) {
            global $wpdb;
            $table = LeykaUTMTrackerDB::get_table_name();

            return $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table} WHERE donation_id = %d", (int) $donation_id),
                ARRAY_A
            );
        }

        protected static function build_utm_payload($existing_row = array()) {
            $first_source = self::get_cookie_value(
                'leyka_utm_first_source',
                !empty($existing_row['utm_first_source']) ? $existing_row['utm_first_source'] : (!empty($existing_row['utm_source']) ? $existing_row['utm_source'] : '')
            );
            $first_medium = self::get_cookie_value(
                'leyka_utm_first_medium',
                !empty($existing_row['utm_first_medium']) ? $existing_row['utm_first_medium'] : (!empty($existing_row['utm_medium']) ? $existing_row['utm_medium'] : '')
            );
            $first_campaign = self::get_cookie_value(
                'leyka_utm_first_campaign',
                !empty($existing_row['utm_first_campaign']) ? $existing_row['utm_first_campaign'] : (!empty($existing_row['utm_campaign']) ? $existing_row['utm_campaign'] : '')
            );

            $last_source = self::get_cookie_value(
                'leyka_utm_last_source',
                !empty($existing_row['utm_last_source']) ? $existing_row['utm_last_source'] : $first_source
            );
            $last_medium = self::get_cookie_value(
                'leyka_utm_last_medium',
                !empty($existing_row['utm_last_medium']) ? $existing_row['utm_last_medium'] : $first_medium
            );
            $last_campaign = self::get_cookie_value(
                'leyka_utm_last_campaign',
                !empty($existing_row['utm_last_campaign']) ? $existing_row['utm_last_campaign'] : $first_campaign
            );

            $legacy_source = $first_source ? $first_source : '(direct)';
            $legacy_medium = $first_medium;
            $legacy_campaign = $first_campaign;

            if (!$first_source && !$last_source) {
                $legacy_source = '(direct)';
            }

            return array(
                'utm_source' => $legacy_source,
                'utm_medium' => $legacy_medium,
                'utm_campaign' => $legacy_campaign,

                'utm_first_source' => $first_source,
                'utm_first_medium' => $first_medium,
                'utm_first_campaign' => $first_campaign,

                'utm_last_source' => $last_source ? $last_source : ($first_source ? $first_source : '(direct)'),
                'utm_last_medium' => $last_medium,
                'utm_last_campaign' => $last_campaign,
            );
        }

        protected static function upsert_donation($donation_id, $status, $amount = null) {
            global $wpdb;
            $table = LeykaUTMTrackerDB::get_table_name();

            $existing = self::get_existing_row($donation_id);
            $amount = $amount === null ? self::get_amount_from_meta($donation_id) : (float) $amount;
            $utm = self::build_utm_payload(is_array($existing) ? $existing : array());

            $sql = $wpdb->prepare(
                "INSERT INTO {$table}
                    (donation_id, utm_source, utm_medium, utm_campaign,
                     utm_first_source, utm_first_medium, utm_first_campaign,
                     utm_last_source, utm_last_medium, utm_last_campaign,
                     status, amount, created_at, updated_at)
                 VALUES
                    (%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %f, %s, %s)
                 ON DUPLICATE KEY UPDATE
                    utm_source = VALUES(utm_source),
                    utm_medium = VALUES(utm_medium),
                    utm_campaign = VALUES(utm_campaign),
                    utm_first_source = VALUES(utm_first_source),
                    utm_first_medium = VALUES(utm_first_medium),
                    utm_first_campaign = VALUES(utm_first_campaign),
                    utm_last_source = VALUES(utm_last_source),
                    utm_last_medium = VALUES(utm_last_medium),
                    utm_last_campaign = VALUES(utm_last_campaign),
                    status = VALUES(status),
                    amount = VALUES(amount),
                    updated_at = VALUES(updated_at)",
                (int) $donation_id,
                $utm['utm_source'],
                $utm['utm_medium'],
                $utm['utm_campaign'],
                $utm['utm_first_source'],
                $utm['utm_first_medium'],
                $utm['utm_first_campaign'],
                $utm['utm_last_source'],
                $utm['utm_last_medium'],
                $utm['utm_last_campaign'],
                (string) $status,
                (float) $amount,
                current_time('mysql'),
                current_time('mysql')
            );

            $result = $wpdb->query($sql);

            if ($result === false) {
                leyka_boost_utm_log('DB ERROR: ' . $wpdb->last_error, 'ERROR');
            } else {
                leyka_boost_utm_log(
                    sprintf(
                        'UPSERT OK ID=%d status=%s amount=%s first=%s/%s/%s last=%s/%s/%s',
                        (int) $donation_id,
                        (string) $status,
                        (string) $amount,
                        $utm['utm_first_source'],
                        $utm['utm_first_medium'],
                        $utm['utm_first_campaign'],
                        $utm['utm_last_source'],
                        $utm['utm_last_medium'],
                        $utm['utm_last_campaign']
                    )
                );
            }
        }

        public static function handle_donation_created($post_id, $post, $update) {
            if (!is_object($post) || empty($post->post_type) || $post->post_type !== 'leyka_donation') {
                return;
            }

            // Skip updates — status transitions are handled by handle_status_change().
            if ($update) {
                return;
            }

            // If the post already carries a "real" status, handle_status_change() will
            // fire in the same request and do the upsert — avoid a redundant double write.
            $skip_statuses = array('submitted', 'funded', 'failed', 'cancelled');
            if (in_array($post->post_status, $skip_statuses, true)) {
                leyka_boost_utm_log('INSERT_POST skipped (transition will follow): ID=' . $post_id . ' status=' . $post->post_status);
                return;
            }

            leyka_boost_utm_log('INSERT_POST: ID=' . $post_id . ' type=' . $post->post_type . ' status=' . $post->post_status);
            self::upsert_donation($post_id, 'pending');
        }

        public static function handle_status_change($new_status, $old_status, $post) {
            if (!is_object($post) || empty($post->post_type) || $post->post_type !== 'leyka_donation') {
                return;
            }

            leyka_boost_utm_log(
                sprintf(
                    'STATUS: ID=%d type=%s %s → %s',
                    (int) $post->ID,
                    $post->post_type,
                    (string) $old_status,
                    (string) $new_status
                )
            );

            if ($new_status === 'submitted') {
                self::upsert_donation($post->ID, 'pending');
                return;
            }

            if ($new_status === 'funded') {
                $amount = self::get_amount_from_meta($post->ID);
                self::upsert_donation($post->ID, 'success', $amount);
                leyka_boost_utm_log('SUCCESS ID=' . $post->ID . ' amount=' . $amount);
                return;
            }

            if (in_array($new_status, array('failed', 'cancelled'), true)) {
                self::upsert_donation($post->ID, 'fail');
                leyka_boost_utm_log('FAIL ID=' . $post->ID);
            }
        }
    }
}
