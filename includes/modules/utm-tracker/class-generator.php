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
            echo '<h1>' . esc_html(lutm_t('Генератор UTM-ссылок', 'UTM Link Generator')) . '</h1>';

            echo '<div class="lutm-generator">';

            // ── Form ────────────────────────────────────────────────
            echo '<div class="lutm-gen-form">';

            echo '<label>' . esc_html(lutm_t('Базовый URL *', 'Base URL *')) . '</label>';
            echo '<input type="url" id="lutm-gen-url" class="regular-text lutm-gen-input" placeholder="https://site.ru/page/">';

            echo '<label>' . esc_html(lutm_t('utm_source *', 'utm_source *')) . '</label>';
            echo '<input type="text" id="lutm-gen-source" class="regular-text lutm-gen-input" placeholder="google">';

            echo '<label>utm_medium</label>';
            echo '<input type="text" id="lutm-gen-medium" class="regular-text lutm-gen-input" placeholder="cpc">';

            echo '<label>utm_campaign</label>';
            echo '<input type="text" id="lutm-gen-campaign" class="regular-text lutm-gen-input" placeholder="spring2026">';

            echo '<label>utm_content</label>';
            echo '<input type="text" id="lutm-gen-content" class="regular-text lutm-gen-input" placeholder="banner_top">';

            echo '<label>utm_term</label>';
            echo '<input type="text" id="lutm-gen-term" class="regular-text lutm-gen-input" placeholder="donate">';

            echo '<p class="description">' . esc_html(lutm_t(
                'Отслеживаются плагином только source, medium и campaign.',
                'Only source, medium and campaign are tracked by the plugin.'
            )) . '</p>';

            echo '<div class="lutm-gen-buttons">';
            echo '<button type="button" id="lutm-gen-generate" class="button button-primary">' . esc_html(lutm_t('Сгенерировать ссылку', 'Generate link')) . '</button>';
            echo ' <button type="button" id="lutm-gen-clear" class="button">' . esc_html(lutm_t('Очистить', 'Clear')) . '</button>';
            echo '</div>';

            echo '</div>';

            // ── Result ──────────────────────────────────────────────
            echo '<div class="lutm-gen-result" style="display:none">';
            echo '<label>' . esc_html(lutm_t('Результат', 'Result')) . '</label>';
            echo '<input type="text" id="lutm-gen-output" class="large-text" readonly>';
            echo '<button type="button" id="lutm-gen-copy" class="button">' . esc_html(lutm_t('Копировать', 'Copy')) . '</button>';
            echo '<span id="lutm-gen-copied" class="lutm-gen-notice" style="display:none">' . esc_html(lutm_t('Скопировано!', 'Copied!')) . '</span>';
            echo '</div>';

            echo '</div>';

            // ── History ─────────────────────────────────────────────
            echo '<h2>' . esc_html(lutm_t('История ссылок', 'Link history')) . '</h2>';

            echo '<div class="lutm-gen-history-actions">';
            echo '<button type="button" id="lutm-gen-clear-history" class="button">' . esc_html(lutm_t('Очистить историю', 'Clear history')) . '</button>';
            echo '</div>';

            echo '<table class="widefat striped lutm-gen-history-table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html(lutm_t('Ссылка', 'Link')) . '</th>';
            echo '<th>Source</th>';
            echo '<th>Medium</th>';
            echo '<th>Campaign</th>';
            echo '<th>' . esc_html(lutm_t('Автор', 'Author')) . '</th>';
            echo '<th>' . esc_html(lutm_t('Дата', 'Date')) . '</th>';
            echo '<th>' . esc_html(lutm_t('Действия', 'Actions')) . '</th>';
            echo '</tr></thead>';
            echo '<tbody id="lutm-gen-history-body">';
            echo '<tr><td colspan="7">' . esc_html(lutm_t('Загрузка…', 'Loading…')) . '</td></tr>';
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
                'url'        => isset($_POST['url'])      ? esc_url_raw(wp_unslash($_POST['url']))              : '',
                'source'     => isset($_POST['source'])   ? sanitize_text_field(wp_unslash($_POST['source']))   : '',
                'medium'     => isset($_POST['medium'])   ? sanitize_text_field(wp_unslash($_POST['medium']))   : '',
                'campaign'   => isset($_POST['campaign']) ? sanitize_text_field(wp_unslash($_POST['campaign'])) : '',
                'content'    => isset($_POST['content'])  ? sanitize_text_field(wp_unslash($_POST['content']))  : '',
                'term'       => isset($_POST['term'])     ? sanitize_text_field(wp_unslash($_POST['term']))     : '',
                'full_url'   => isset($_POST['full_url']) ? esc_url_raw(wp_unslash($_POST['full_url']))         : '',
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
