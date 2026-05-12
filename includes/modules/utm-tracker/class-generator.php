<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('LeykaUTMTrackerGenerator')) {
    class LeykaUTMTrackerGenerator {

        const OPTION_KEY  = 'leyka_utm_generator_history';
        const HISTORY_MAX = 200;

        // ── Page render ─────────────────────────────────────────────

        public static function page() {
            if (!current_user_can('manage_options')) {
                return;
            }

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('UTM Link Generator', 'leyka-boost') . '</h1>';

            echo '<div class="lutm-generator">';

            // ── Form ────────────────────────────────────────────────
            echo '<div class="lutm-gen-form">';

            echo '<label>' . esc_html__('Base URL *', 'leyka-boost') . '</label>';
            echo '<input type="url" id="lutm-gen-url" class="regular-text lutm-gen-input" placeholder="https://site.ru/page/">';

            echo '<label>' . esc_html__('utm_source *', 'leyka-boost') . '</label>';
            echo '<input type="text" id="lutm-gen-source" class="regular-text lutm-gen-input" placeholder="google">';

            echo '<label>utm_medium</label>';
            echo '<input type="text" id="lutm-gen-medium" class="regular-text lutm-gen-input" placeholder="cpc">';

            echo '<label>utm_campaign</label>';
            echo '<input type="text" id="lutm-gen-campaign" class="regular-text lutm-gen-input" placeholder="spring2026">';

            echo '<label>utm_content</label>';
            echo '<input type="text" id="lutm-gen-content" class="regular-text lutm-gen-input" placeholder="banner_top">';

            echo '<label>utm_term</label>';
            echo '<input type="text" id="lutm-gen-term" class="regular-text lutm-gen-input" placeholder="donate">';

            echo '<p class="description">' . esc_html__('Only source, medium and campaign are tracked by the plugin.', 'leyka-boost') . '</p>';

            echo '<div class="lutm-gen-buttons">';
            echo '<button type="button" id="lutm-gen-generate" class="button button-primary">' . esc_html__('Generate link', 'leyka-boost') . '</button>';
            echo ' <button type="button" id="lutm-gen-clear" class="button">' . esc_html__('Clear', 'leyka-boost') . '</button>';
            echo '</div>';

            echo '</div>';

            // ── Result ──────────────────────────────────────────────
            echo '<div class="lutm-gen-result" style="display:none">';
            echo '<label>' . esc_html__('Result', 'leyka-boost') . '</label>';
            echo '<input type="text" id="lutm-gen-output" class="large-text" readonly>';
            echo '<button type="button" id="lutm-gen-copy" class="button">' . esc_html__('Copy', 'leyka-boost') . '</button>';
            echo '<span id="lutm-gen-copied" class="lutm-gen-notice" style="display:none">' . esc_html__('Copied!', 'leyka-boost') . '</span>';
            echo '</div>';

            echo '</div>';

            // ── History ─────────────────────────────────────────────
            echo '<h2>' . esc_html__('Link history', 'leyka-boost') . '</h2>';

            echo '<div class="lutm-gen-history-actions">';
            echo '<button type="button" id="lutm-gen-clear-history" class="button">' . esc_html__('Clear history', 'leyka-boost') . '</button>';
            echo '</div>';

            echo '<table class="widefat striped lutm-gen-history-table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Link', 'leyka-boost') . '</th>';
            echo '<th>Source</th>';
            echo '<th>Medium</th>';
            echo '<th>Campaign</th>';
            echo '<th>' . esc_html__('Author', 'leyka-boost') . '</th>';
            echo '<th>' . esc_html__('Date', 'leyka-boost') . '</th>';
            echo '<th>' . esc_html__('Actions', 'leyka-boost') . '</th>';
            echo '</tr></thead>';
            echo '<tbody id="lutm-gen-history-body">';
            echo '<tr><td colspan="7">' . esc_html__('Loading...', 'leyka-boost') . '</td></tr>';
            echo '</tbody></table>';

            echo '</div>';
        }

        // ── AJAX: save ──────────────────────────────────────────────

        public static function ajax_save() {
            check_ajax_referer('lutm_generator_nonce', '_nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error('forbidden');
            }

            $entry = array(
                'url'        => isset($_POST['url'])      ? esc_url_raw(wp_unslash($_POST['url']))                    : '',
                'source'     => isset($_POST['source'])   ? sanitize_text_field(wp_unslash($_POST['source']))         : '',
                'medium'     => isset($_POST['medium'])   ? sanitize_text_field(wp_unslash($_POST['medium']))         : '',
                'campaign'   => isset($_POST['campaign']) ? sanitize_text_field(wp_unslash($_POST['campaign']))       : '',
                'content'    => isset($_POST['content'])  ? sanitize_text_field(wp_unslash($_POST['content']))        : '',
                'term'       => isset($_POST['term'])     ? sanitize_text_field(wp_unslash($_POST['term']))           : '',
                'full_url'   => isset($_POST['full_url']) ? esc_url_raw(wp_unslash($_POST['full_url']))               : '',
                'user'       => wp_get_current_user()->user_login,
                'created_at' => current_time('Y-m-d H:i'),
            );

            $history = get_option(self::OPTION_KEY, array());
            if (!is_array($history)) {
                $history = array();
            }

            array_unshift($history, $entry);

            if (count($history) > self::HISTORY_MAX) {
                $history = array_slice($history, 0, self::HISTORY_MAX);
            }

            update_option(self::OPTION_KEY, $history, false);

            wp_send_json_success($history);
        }

        // ── AJAX: load ──────────────────────────────────────────────

        public static function ajax_load() {
            check_ajax_referer('lutm_generator_nonce', '_nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error('forbidden');
            }

            $history = get_option(self::OPTION_KEY, array());
            if (!is_array($history)) {
                $history = array();
            }

            wp_send_json_success($history);
        }

        // ── AJAX: clear ─────────────────────────────────────────────

        public static function ajax_clear() {
            check_ajax_referer('lutm_generator_nonce', '_nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error('forbidden');
            }

            delete_option(self::OPTION_KEY);
            wp_send_json_success(array());
        }
    }
}
