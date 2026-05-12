<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('LeykaUTMTrackerAdmin')) {
    class LeykaUTMTrackerAdmin {

        public static function menu() {
            add_submenu_page(
                'leyka-boost',
                __('UTM Tracker', 'leyka-boost'),
                __('UTM Tracker', 'leyka-boost'),
                'manage_options',
                'leyka-utm-tracker',
                array(__CLASS__, 'page')
            );

            add_submenu_page(
                'leyka-boost',
                lutm_t('Настройки', 'Settings'),
                lutm_t('Настройки', 'Settings'),
                'manage_options',
                'leyka-utm-settings',
                array(__CLASS__, 'settings_page')
            );

            add_submenu_page(
                'leyka-boost',
                lutm_t('Генератор ссылок', 'Generator'),
                lutm_t('Генератор ссылок', 'Generator'),
                'manage_options',
                'leyka-utm-generator',
                array('LeykaUTMTrackerGenerator', 'page')
            );

            // Enqueue admin stylesheet only on our own pages.
            add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        }

        public static function enqueue_assets($hook) {
            $our_pages = array(
                'leyka-boost_page_leyka-utm-tracker',
                'leyka-boost_page_leyka-utm-settings',
                'leyka-boost_page_leyka-utm-generator',
            );

            if (!in_array($hook, $our_pages, true)) {
                return;
            }

            wp_enqueue_style(
                'leyka-boost-admin',
                LEYKA_UTM_TRACKER_URL . 'assets/css/admin.css',
                array(),
                LEYKA_UTM_TRACKER_VERSION
            );

            if ($hook === 'leyka-boost_page_leyka-utm-generator') {
                wp_enqueue_script(
                    'leyka-utm-generator',
                    LEYKA_UTM_TRACKER_URL . 'assets/js/utm-generator.js',
                    array('jquery'),
                    LEYKA_UTM_TRACKER_VERSION,
                    true
                );
                wp_localize_script('leyka-utm-generator', 'lutmGen', array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce'   => wp_create_nonce('lutm_generator_nonce'),
                    'i18n'    => array(
                        'errorUrlEmpty'   => lutm_t('Введите URL', 'Enter a URL'),
                        'errorUrlInvalid' => lutm_t('Некорректный URL', 'Invalid URL'),
                        'errorSourceEmpty' => lutm_t('Введите utm_source', 'Enter utm_source'),
                        'confirmClear'    => lutm_t('Вы уверены, что хотите очистить историю генератора? Это действие невозможно отменить.', 'Are you sure you want to clear the generator history? This action cannot be undone.'),
                        'noHistory'       => lutm_t('История пуста.', 'History is empty.'),
                        'copy'            => lutm_t('Копировать', 'Copy'),
                        'load'            => lutm_t('В форму', 'Load'),
                    ),
                ));
            }
        }

        // ── Helpers ─────────────────────────────────────────────────

        protected static function notice($message, $type = 'success') {
            printf(
                '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        }

        protected static function badge($status) {
            $map = array(
                'success' => array('success', lutm_t('Успешно', 'Success')),
                'pending' => array('pending', lutm_t('В процессе', 'Pending')),
                'fail'    => array('fail',    lutm_t('Ошибка', 'Fail')),
            );

            $item  = isset($map[$status]) ? $map[$status] : array('unknown', esc_html($status));
            $class = 'lutm-badge lutm-badge--' . $item[0];

            return sprintf(
                '<span class="%1$s">%2$s</span>',
                esc_attr($class),
                esc_html($item[1])
            );
        }

        protected static function stat_card($label, $value) {
            echo '<div class="lutm-card">';
            echo '<div class="lutm-card__label">' . esc_html($label) . '</div>';
            echo '<div class="lutm-card__value">' . esc_html((string) $value) . '</div>';
            echo '</div>';
        }

        protected static function utm_block($title, $source, $medium, $campaign) {
            $source   = $source   !== '' ? $source   : '—';
            $medium   = $medium   !== '' ? $medium   : '—';
            $campaign = $campaign !== '' ? $campaign : '—';

            return '<div class="leyka-utm-meta">'
                . '<strong>' . esc_html(lutm_t('Источник:', 'Source:')) . '</strong> ' . esc_html($source) . '<br>'
                . '<strong>' . esc_html(lutm_t('Канал:', 'Medium:')) . '</strong> ' . esc_html($medium) . '<br>'
                . '<strong>' . esc_html(lutm_t('Кампания:', 'Campaign:')) . '</strong> ' . esc_html($campaign)
                . '</div>';
        }

        // ── Filter helpers ────────────────────────────────────────────

        protected static function get_current_filters() {
            return array(
                'utm_source'   => isset($_GET['utm_source'])   ? sanitize_text_field(wp_unslash($_GET['utm_source']))   : '',
                'utm_medium'   => isset($_GET['utm_medium'])   ? sanitize_text_field(wp_unslash($_GET['utm_medium']))   : '',
                'utm_campaign' => isset($_GET['utm_campaign']) ? sanitize_text_field(wp_unslash($_GET['utm_campaign'])) : '',
                'status'       => isset($_GET['status'])       ? sanitize_text_field(wp_unslash($_GET['status']))       : '',
                'date_from'    => isset($_GET['date_from'])    ? sanitize_text_field(wp_unslash($_GET['date_from']))    : '',
                'date_to'      => isset($_GET['date_to'])      ? sanitize_text_field(wp_unslash($_GET['date_to']))      : '',
            );
        }

        protected static function has_active_filters($filters) {
            foreach ($filters as $v) {
                if ($v !== '') {
                    return true;
                }
            }
            return false;
        }

        protected static function render_filters($filters, $options) {
            $base_url = admin_url('admin.php');
            echo '<form class="lutm-filters" method="get" action="' . esc_url($base_url) . '">';
            echo '<input type="hidden" name="page" value="leyka-utm-tracker">';

            echo '<select name="utm_source">';
            echo '<option value="">' . esc_html(lutm_t('Все источники', 'All sources')) . '</option>';
            foreach ($options['sources'] as $src) {
                echo '<option value="' . esc_attr($src) . '"' . selected($filters['utm_source'], $src, false) . '>' . esc_html($src) . '</option>';
            }
            echo '</select>';

            echo '<select name="utm_medium">';
            echo '<option value="">' . esc_html(lutm_t('Все каналы', 'All mediums')) . '</option>';
            foreach ($options['mediums'] as $med) {
                echo '<option value="' . esc_attr($med) . '"' . selected($filters['utm_medium'], $med, false) . '>' . esc_html($med) . '</option>';
            }
            echo '</select>';

            echo '<select name="utm_campaign">';
            echo '<option value="">' . esc_html(lutm_t('Все кампании', 'All campaigns')) . '</option>';
            foreach ($options['campaigns'] as $cmp) {
                echo '<option value="' . esc_attr($cmp) . '"' . selected($filters['utm_campaign'], $cmp, false) . '>' . esc_html($cmp) . '</option>';
            }
            echo '</select>';

            echo '<select name="status">';
            echo '<option value="">' . esc_html(lutm_t('Все статусы', 'All statuses')) . '</option>';
            echo '<option value="success"' . selected($filters['status'], 'success', false) . '>' . esc_html(lutm_t('Успешно', 'Success')) . '</option>';
            echo '<option value="pending"' . selected($filters['status'], 'pending', false) . '>' . esc_html(lutm_t('В процессе', 'Pending')) . '</option>';
            echo '<option value="fail"' . selected($filters['status'], 'fail', false) . '>' . esc_html(lutm_t('Ошибка', 'Fail')) . '</option>';
            echo '</select>';

            echo '<input type="date" name="date_from" value="' . esc_attr($filters['date_from']) . '" placeholder="' . esc_attr(lutm_t('Дата от', 'Date from')) . '">';
            echo '<input type="date" name="date_to" value="' . esc_attr($filters['date_to']) . '" placeholder="' . esc_attr(lutm_t('Дата до', 'Date to')) . '">';

            submit_button(lutm_t('Применить', 'Apply'), 'primary', '', false);

            if (self::has_active_filters($filters)) {
                echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=leyka-utm-tracker')) . '">' . esc_html(lutm_t('Сбросить', 'Reset')) . '</a>';
            }

            echo '</form>';
        }

        protected static function render_csv_button($filters) {
            $url = wp_nonce_url(admin_url('admin-post.php?action=lutm_export_csv'), 'lutm_export_csv');
            foreach ($filters as $key => $value) {
                if ($value !== '') {
                    $url = add_query_arg($key, $value, $url);
                }
            }
            echo '<a class="button" href="' . esc_url($url) . '">' . esc_html(lutm_t('Экспорт CSV', 'Export CSV')) . '</a>';
        }

        protected static function render_summaries($top_sources, $top_campaigns, $paths) {
            $touch_label = LeykaUTMTrackerAnalytics::get_touch_preference() === 'last'
                ? lutm_t('последнее касание', 'last touch')
                : lutm_t('первое касание', 'first touch');

            echo '<div class="lutm-summaries">';

            echo '<div class="lutm-summary-block">';
            echo '<h3>' . esc_html(lutm_t('Источники трафика', 'Traffic sources')) . ' <small>(' . esc_html($touch_label) . ')</small></h3>';
            self::render_summary_table(
                array(lutm_t('Источник', 'Source'), lutm_t('Донатов', 'Donations'), lutm_t('Сумма', 'Amount')),
                $top_sources,
                'source_name'
            );
            echo '</div>';

            echo '<div class="lutm-summary-block">';
            echo '<h3>' . esc_html(lutm_t('Кампании', 'Campaigns')) . ' <small>(' . esc_html($touch_label) . ')</small></h3>';
            self::render_summary_table(
                array(lutm_t('Кампания', 'Campaign'), lutm_t('Донатов', 'Donations'), lutm_t('Сумма', 'Amount')),
                $top_campaigns,
                'campaign_name'
            );
            echo '</div>';

            echo '</div>';

            if (!empty($paths)) {
                echo '<div class="lutm-summary-block lutm-summary-block--full">';
                echo '<h3>' . esc_html(lutm_t('Путь: Первое → Последнее касание', 'Path: First → Last touch')) . '</h3>';
                echo '<table class="widefat striped">';
                echo '<thead><tr>';
                echo '<th>' . esc_html(lutm_t('Первый источник', 'First source')) . '</th>';
                echo '<th>' . esc_html(lutm_t('Последний источник', 'Last source')) . '</th>';
                echo '<th>' . esc_html(lutm_t('Донатов', 'Donations')) . '</th>';
                echo '<th>' . esc_html(lutm_t('Сумма', 'Amount')) . '</th>';
                echo '</tr></thead><tbody>';
                foreach ($paths as $path) {
                    echo '<tr>';
                    echo '<td>' . esc_html($path->first_source !== '' ? $path->first_source : '(direct)') . '</td>';
                    echo '<td>' . esc_html($path->last_source !== '' ? $path->last_source : '(direct)') . '</td>';
                    echo '<td>' . (int) $path->donations . '</td>';
                    echo '<td>' . esc_html(number_format_i18n((float) $path->total_amount, 2)) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '</div>';
            }
        }

        protected static function render_summary_table($headers, $rows, $name_key) {
            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            foreach ($headers as $h) {
                echo '<th>' . esc_html($h) . '</th>';
            }
            echo '</tr></thead><tbody>';

            if (empty($rows)) {
                echo '<tr><td colspan="' . count($headers) . '">' . esc_html(lutm_t('Нет данных', 'No data')) . '</td></tr>';
            } else {
                foreach ($rows as $row) {
                    $name = $row->$name_key;
                    echo '<tr>';
                    echo '<td>' . esc_html($name !== '' ? $name : '(direct)') . '</td>';
                    echo '<td>' . (int) $row->donations . '</td>';
                    echo '<td>' . esc_html(number_format_i18n((float) $row->total_amount, 2)) . '</td>';
                    echo '</tr>';
                }
            }

            echo '</tbody></table>';
        }

        protected static function build_pagination_base_url($filters) {
            $base = admin_url('admin.php?page=leyka-utm-tracker');
            foreach ($filters as $key => $value) {
                if ($value !== '') {
                    $base = add_query_arg($key, $value, $base);
                }
            }
            return $base;
        }

        // ── Main analytics page ──────────────────────────────────────

        public static function page() {
            if (!current_user_can('manage_options')) {
                return;
            }

            global $wpdb;
            $table = LeykaUTMTrackerDB::get_table_name();

            // Handle "clear pending" action.
            if (
                isset($_POST['lutm_clear_pending']) &&
                check_admin_referer('lutm_clear_pending_action', 'lutm_clear_pending_nonce')
            ) {
                $deleted = $wpdb->query("DELETE FROM {$table} WHERE status = 'pending'");
                self::notice(sprintf(lutm_t('Удалено попыток: %d', 'Deleted pending attempts: %d'), (int) $deleted));
            }

            // ── Collect filters ─────────────────────────────────────
            $filters = self::get_current_filters();

            // ── Analytics data ──────────────────────────────────────
            $counts     = LeykaUTMTrackerAnalytics::get_counts($filters);
            $conversion = LeykaUTMTrackerAnalytics::get_conversion($counts);
            $options    = LeykaUTMTrackerAnalytics::get_filter_options();

            // ── Pagination ──────────────────────────────────────────
            $per_page     = 25;
            $total_pages  = max(1, (int) ceil($counts['total'] / $per_page));
            $current_page = min($total_pages, max(1, isset($_GET['paged']) ? absint(wp_unslash($_GET['paged'])) : 1));

            $result = LeykaUTMTrackerAnalytics::get_rows($filters, $per_page, $current_page);
            $rows   = $result['rows'];

            // ── Summary data ────────────────────────────────────────
            $top_sources   = LeykaUTMTrackerAnalytics::get_top_sources($filters);
            $top_campaigns = LeykaUTMTrackerAnalytics::get_top_campaigns($filters);
            $paths         = LeykaUTMTrackerAnalytics::get_first_last_paths($filters);

            // ── Output ──────────────────────────────────────────────
            echo '<div class="wrap">';
            echo '<h1>Leyka UTM Tracker</h1>';

            // ── Cards ───────────────────────────────────────────────
            echo '<div class="lutm-cards">';
            self::stat_card(lutm_t('Всего пожертвований', 'Total donations'), $counts['total']);
            self::stat_card(lutm_t('Успешных', 'Successful'), $counts['success_count']);
            self::stat_card(lutm_t('Попытка', 'Attempts'), $counts['pending_count']);
            self::stat_card(lutm_t('Сумма успешных', 'Successful amount'), number_format_i18n($counts['sum_success'], 2));
            self::stat_card(lutm_t('Конверсия', 'Conversion'), $conversion . '%');
            echo '</div>';

            // ── Filters ─────────────────────────────────────────────
            self::render_filters($filters, $options);

            // ── Actions ─────────────────────────────────────────────
            echo '<div class="lutm-actions">';
            echo '<form class="lutm-clear-form" method="post" style="display:inline-block" onsubmit="return confirm(\'' . esc_js(lutm_t('Вы уверены, что хотите очистить попытки пожертвований? Это действие невозможно отменить.', 'Are you sure you want to clear pending donation attempts? This action cannot be undone.')) . '\');">';
            wp_nonce_field('lutm_clear_pending_action', 'lutm_clear_pending_nonce');
            submit_button(lutm_t('Очистить попытки', 'Clear pending'), 'secondary', 'lutm_clear_pending', false);
            echo '</form> ';
            self::render_csv_button($filters);
            echo '</div>';

            // ── Summary tables ──────────────────────────────────────
            self::render_summaries($top_sources, $top_campaigns, $paths);

            // ── Main table ──────────────────────────────────────────
            echo '<h2>' . esc_html(lutm_t('Все пожертвования', 'All donations')) . '</h2>';
            echo '<table class="widefat striped leyka-utm-table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html('#') . '</th>';
            echo '<th>' . esc_html(lutm_t('ID', 'ID')) . '</th>';
            echo '<th>' . esc_html(lutm_t('Первое касание', 'First touch')) . '</th>';
            echo '<th>' . esc_html(lutm_t('Последнее касание', 'Last touch')) . '</th>';
            echo '<th>' . esc_html(lutm_t('Сумма', 'Amount')) . '</th>';
            echo '<th>' . esc_html(lutm_t('Статус', 'Status')) . '</th>';
            echo '<th>' . esc_html(lutm_t('Дата', 'Date')) . '</th>';
            echo '</tr></thead><tbody>';

            if (empty($rows)) {
                echo '<tr><td colspan="7">' . esc_html(lutm_t('UTM-данные отсутствуют. Данные появятся после первого пожертвования с UTM-метками.', 'UTM data is empty. Data will appear after the first donation with UTM marks.')) . '</td></tr>';
            } else {
                foreach ($rows as $index => $row) {
                    $link = admin_url('admin.php?page=leyka_donation_info&donation=' . (int) $row->donation_id);
                    $row_number = (($current_page - 1) * $per_page) + $index + 1;

                    echo '<tr>';
                    echo '<td>' . (int) $row_number . '</td>';
                    echo '<td><a href="' . esc_url($link) . '"><strong>' . (int) $row->donation_id . '</strong></a></td>';
                    echo '<td>' . self::utm_block(
                        lutm_t('Первое касание', 'First touch'),
                        $row->utm_first_source,
                        $row->utm_first_medium,
                        $row->utm_first_campaign
                    ) . '</td>';
                    echo '<td>' . self::utm_block(
                        lutm_t('Последнее касание', 'Last touch'),
                        $row->utm_last_source,
                        $row->utm_last_medium,
                        $row->utm_last_campaign
                    ) . '</td>';
                    echo '<td>' . esc_html(number_format_i18n((float) $row->amount, 2)) . '</td>';
                    echo '<td>' . self::badge($row->status) . '</td>';
                    echo '<td>' . esc_html($row->updated_at) . '</td>';
                    echo '</tr>';
                }
            }

            echo '</tbody></table>';

            // ── Pagination links ────────────────────────────────────
            if ($total_pages > 1) {
                $base_url = self::build_pagination_base_url($filters);
                $big      = 999999999;
                $links = paginate_links(array(
                    'base'      => str_replace($big, '%#%', esc_url(add_query_arg('paged', $big, $base_url))),
                    'format'    => '',
                    'current'   => $current_page,
                    'total'     => $total_pages,
                    'prev_text' => '&laquo; ' . lutm_t('Назад', 'Prev'),
                    'next_text' => lutm_t('Вперёд', 'Next') . ' &raquo;',
                    'type'      => 'array',
                ));

                if (!empty($links)) {
                    echo '<div class="tablenav bottom lutm-pagination">';
                    echo '<div class="tablenav-pages">';
                    echo '<span class="displaying-num">' . esc_html(sprintf(lutm_t('%d записей', '%d entries'), (int) $result['total'])) . '</span>';
                    foreach ($links as $link) {
                        echo wp_kses_post($link);
                    }
                    echo '<span class="lutm-pagination__info">';
                    printf(
                        esc_html(lutm_t('Страница %1$d из %2$d', 'Page %1$d of %2$d')),
                        (int) $current_page,
                        (int) $total_pages
                    );
                    echo '</span>';
                    echo '</div>';
                    echo '</div>';
                }
            }

            echo '</div>';
        }

        // ── Settings page ────────────────────────────────────────────

        public static function settings_page() {
            if (!current_user_can('manage_options')) {
                return;
            }

            if (
                isset($_POST['lutm_save_settings']) &&
                check_admin_referer('lutm_save_settings_action', 'lutm_settings_nonce')
            ) {
                update_option('leyka_utm_logging_enabled', !empty($_POST['leyka_utm_logging_enabled']) ? 1 : 0);
                $touch = isset($_POST['leyka_utm_touch_preference']) && wp_unslash($_POST['leyka_utm_touch_preference']) === 'last' ? 'last' : 'first';
                update_option('leyka_utm_touch_preference', $touch);
                self::notice(lutm_t('Настройки сохранены.', 'Settings saved.'));
            }

            $enabled = (bool) get_option('leyka_utm_logging_enabled', false);
            $touch   = get_option('leyka_utm_touch_preference', 'first');

            echo '<div class="wrap">';
            echo '<h1>' . esc_html(lutm_t('Настройки', 'Settings')) . '</h1>';
            echo '<form method="post">';
            wp_nonce_field('lutm_save_settings_action', 'lutm_settings_nonce');

            echo '<table class="form-table" role="presentation"><tbody>';

            echo '<tr>';
            echo '<th scope="row">' . esc_html(lutm_t('Аналитика по', 'Analytics by')) . '</th>';
            echo '<td>';
            echo '<label><input type="radio" name="leyka_utm_touch_preference" value="first" ' . checked($touch, 'first', false) . '> ';
            echo esc_html(lutm_t('Первое касание (рекомендуется)', 'First touch (recommended)'));
            echo '</label><br>';
            echo '<label><input type="radio" name="leyka_utm_touch_preference" value="last" ' . checked($touch, 'last', false) . '> ';
            echo esc_html(lutm_t('Последнее касание', 'Last touch'));
            echo '</label>';
            echo '<p class="description">' . esc_html(lutm_t('Определяет, по какому касанию строится сводка и работают фильтры.', 'Determines which touch is used for summaries and filters.')) . '</p>';
            echo '</td>';
            echo '</tr>';

            echo '<tr>';
            echo '<th scope="row">' . esc_html(lutm_t('Журнал событий', 'Event log')) . '</th>';
            echo '<td>';
            echo '<label><input type="checkbox" name="leyka_utm_logging_enabled" value="1" ' . checked($enabled, true, false) . '> ';
            echo esc_html(lutm_t('Активен', 'Active'));
            echo '</label>';
            echo '<p class="description">' . esc_html(lutm_t('По умолчанию неактивно. Используйте только для диагностики.', 'Inactive by default. Use only for diagnostics.')) . '</p>';
            echo '</td>';
            echo '</tr>';

            echo '</tbody></table>';

            submit_button(lutm_t('Сохранить настройки', 'Save settings'), 'primary', 'lutm_save_settings');
            echo '</form>';
            echo '</div>';
        }

        // ── CSV export ──────────────────────────────────────────────

        public static function handle_csv_export() {
            if (!current_user_can('manage_options')) {
                wp_die(lutm_t('Недостаточно прав', 'Insufficient permissions'));
            }

            check_admin_referer('lutm_export_csv');

            $filters = array(
                'utm_source'   => isset($_GET['utm_source'])   ? sanitize_text_field(wp_unslash($_GET['utm_source']))   : '',
                'utm_medium'   => isset($_GET['utm_medium'])   ? sanitize_text_field(wp_unslash($_GET['utm_medium']))   : '',
                'utm_campaign' => isset($_GET['utm_campaign']) ? sanitize_text_field(wp_unslash($_GET['utm_campaign'])) : '',
                'status'       => isset($_GET['status'])       ? sanitize_text_field(wp_unslash($_GET['status']))       : '',
                'date_from'    => isset($_GET['date_from'])    ? sanitize_text_field(wp_unslash($_GET['date_from']))    : '',
                'date_to'      => isset($_GET['date_to'])      ? sanitize_text_field(wp_unslash($_GET['date_to']))      : '',
            );

            $rows = LeykaUTMTrackerAnalytics::get_export_rows($filters);

            $filename = 'leyka-utm-export-' . wp_date('Y-m-d') . '.csv';

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');

            // BOM for Excel UTF-8
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, array(
                'ID',
                'First Source', 'First Medium', 'First Campaign',
                'Last Source', 'Last Medium', 'Last Campaign',
                'Amount', 'Status', 'Date',
            ));

            foreach ($rows as $row) {
                fputcsv($output, array(
                    $row['donation_id'],
                    $row['utm_first_source'],
                    $row['utm_first_medium'],
                    $row['utm_first_campaign'],
                    $row['utm_last_source'],
                    $row['utm_last_medium'],
                    $row['utm_last_campaign'],
                    $row['amount'],
                    $row['status'],
                    $row['updated_at'],
                ));
            }

            fclose($output);
            exit;
        }
    }
}
