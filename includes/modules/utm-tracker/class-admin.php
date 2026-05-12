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
                __('Settings', 'leyka-boost'),
                __('Settings', 'leyka-boost'),
                'manage_options',
                'leyka-utm-settings',
                array(__CLASS__, 'settings_page')
            );

            add_submenu_page(
                'leyka-boost',
                __('Generator', 'leyka-boost'),
                __('Generator', 'leyka-boost'),
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
                        'errorUrlEmpty'   => __('Enter a URL', 'leyka-boost'),
                        'errorUrlInvalid' => __('Invalid URL', 'leyka-boost'),
                        'errorSourceEmpty' => __('Enter utm_source', 'leyka-boost'),
                        'confirmClear'    => __('Are you sure you want to clear the generator history? This action cannot be undone.', 'leyka-boost'),
                        'noHistory'       => __('History is empty.', 'leyka-boost'),
                        'copy'            => __('Copy', 'leyka-boost'),
                        'load'            => __('Load', 'leyka-boost'),
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
                'success' => array('success', __('Success', 'leyka-boost')),
                'pending' => array('pending', __('Pending', 'leyka-boost')),
                'fail'    => array('fail',    __('Fail', 'leyka-boost')),
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
                . '<strong>' . esc_html__('Source:', 'leyka-boost') . '</strong> ' . esc_html($source) . '<br>'
                . '<strong>' . esc_html__('Medium:', 'leyka-boost') . '</strong> ' . esc_html($medium) . '<br>'
                . '<strong>' . esc_html__('Campaign:', 'leyka-boost') . '</strong> ' . esc_html($campaign)
                . '</div>';
        }

        // ── Filter helpers ────────────────────────────────────────────

        protected static function get_current_filters() {
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filter parameters, sanitized via sanitize_text_field().
            $filters = array(
                'utm_source'   => isset($_GET['utm_source'])   ? sanitize_text_field(wp_unslash($_GET['utm_source']))   : '',
                'utm_medium'   => isset($_GET['utm_medium'])    ? sanitize_text_field(wp_unslash($_GET['utm_medium']))   : '',
                'utm_campaign' => isset($_GET['utm_campaign'])  ? sanitize_text_field(wp_unslash($_GET['utm_campaign'])) : '',
                'status'       => isset($_GET['status'])        ? sanitize_text_field(wp_unslash($_GET['status']))       : '',
                'date_from'    => isset($_GET['date_from'])     ? sanitize_text_field(wp_unslash($_GET['date_from']))    : '',
                'date_to'      => isset($_GET['date_to'])       ? sanitize_text_field(wp_unslash($_GET['date_to']))      : '',
            );
            // phpcs:enable WordPress.Security.NonceVerification.Recommended

            return $filters;
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
            echo '<option value="">' . esc_html__('All sources', 'leyka-boost') . '</option>';
            foreach ($options['sources'] as $src) {
                echo '<option value="' . esc_attr($src) . '"' . selected($filters['utm_source'], $src, false) . '>' . esc_html($src) . '</option>';
            }
            echo '</select>';

            echo '<select name="utm_medium">';
            echo '<option value="">' . esc_html__('All mediums', 'leyka-boost') . '</option>';
            foreach ($options['mediums'] as $med) {
                echo '<option value="' . esc_attr($med) . '"' . selected($filters['utm_medium'], $med, false) . '>' . esc_html($med) . '</option>';
            }
            echo '</select>';

            echo '<select name="utm_campaign">';
            echo '<option value="">' . esc_html__('All campaigns', 'leyka-boost') . '</option>';
            foreach ($options['campaigns'] as $cmp) {
                echo '<option value="' . esc_attr($cmp) . '"' . selected($filters['utm_campaign'], $cmp, false) . '>' . esc_html($cmp) . '</option>';
            }
            echo '</select>';

            echo '<select name="status">';
            echo '<option value="">' . esc_html__('All statuses', 'leyka-boost') . '</option>';
            echo '<option value="success"' . selected($filters['status'], 'success', false) . '>' . esc_html__('Success', 'leyka-boost') . '</option>';
            echo '<option value="pending"' . selected($filters['status'], 'pending', false) . '>' . esc_html__('Pending', 'leyka-boost') . '</option>';
            echo '<option value="fail"' . selected($filters['status'], 'fail', false) . '>' . esc_html__('Fail', 'leyka-boost') . '</option>';
            echo '</select>';

            echo '<input type="date" name="date_from" value="' . esc_attr($filters['date_from']) . '" placeholder="' . esc_attr__('Date from', 'leyka-boost') . '">';
            echo '<input type="date" name="date_to" value="' . esc_attr($filters['date_to']) . '" placeholder="' . esc_attr__('Date to', 'leyka-boost') . '">';

            submit_button(__('Apply', 'leyka-boost'), 'primary', '', false);

            if (self::has_active_filters($filters)) {
                echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=leyka-utm-tracker')) . '">' . esc_html__('Reset', 'leyka-boost') . '</a>';
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
            echo '<a class="button" href="' . esc_url($url) . '">' . esc_html__('Export CSV', 'leyka-boost') . '</a>';
        }

        protected static function render_summaries($top_sources, $top_campaigns, $paths) {
            $touch_label = LeykaUTMTrackerAnalytics::get_touch_preference() === 'last'
                ? __('last touch', 'leyka-boost')
                : __('first touch', 'leyka-boost');

            echo '<div class="lutm-summaries">';

            echo '<div class="lutm-summary-block">';
            echo '<h3>' . esc_html__('Traffic sources', 'leyka-boost') . ' <small>(' . esc_html($touch_label) . ')</small></h3>';
            self::render_summary_table(
                array(__('Source', 'leyka-boost'), __('Donations', 'leyka-boost'), __('Amount', 'leyka-boost')),
                $top_sources,
                'source_name'
            );
            echo '</div>';

            echo '<div class="lutm-summary-block">';
            echo '<h3>' . esc_html__('Campaigns', 'leyka-boost') . ' <small>(' . esc_html($touch_label) . ')</small></h3>';
            self::render_summary_table(
                array(__('Campaign', 'leyka-boost'), __('Donations', 'leyka-boost'), __('Amount', 'leyka-boost')),
                $top_campaigns,
                'campaign_name'
            );
            echo '</div>';

            echo '</div>';

            if (!empty($paths)) {
                echo '<div class="lutm-summary-block lutm-summary-block--full">';
                echo '<h3>' . esc_html__('Path: First -> Last touch', 'leyka-boost') . '</h3>';
                echo '<table class="widefat striped">';
                echo '<thead><tr>';
                echo '<th>' . esc_html__('First source', 'leyka-boost') . '</th>';
                echo '<th>' . esc_html__('Last source', 'leyka-boost') . '</th>';
                echo '<th>' . esc_html__('Donations', 'leyka-boost') . '</th>';
                echo '<th>' . esc_html__('Amount', 'leyka-boost') . '</th>';
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
                echo '<tr><td colspan="' . count($headers) . '">' . esc_html__('No data', 'leyka-boost') . '</td></tr>';
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
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from wpdb prefix, safe.
                $deleted = $wpdb->query("DELETE FROM {$table} WHERE status = 'pending'");
                /* translators: %d: number of deleted pending donation attempts */
                self::notice(sprintf(__('Deleted pending attempts: %d', 'leyka-boost'), (int) $deleted));
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
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination parameter.
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
            self::stat_card(__('Total donations', 'leyka-boost'), $counts['total']);
            self::stat_card(__('Successful', 'leyka-boost'), $counts['success_count']);
            self::stat_card(__('Attempts', 'leyka-boost'), $counts['pending_count']);
            self::stat_card(__('Successful amount', 'leyka-boost'), number_format_i18n($counts['sum_success'], 2));
            self::stat_card(__('Conversion', 'leyka-boost'), $conversion . '%');
            echo '</div>';

            // ── Filters ─────────────────────────────────────────────
            self::render_filters($filters, $options);

            // ── Actions ─────────────────────────────────────────────
            echo '<div class="lutm-actions">';
            echo '<form class="lutm-clear-form" method="post" style="display:inline-block" onsubmit="return confirm(\'' . esc_js(__('Are you sure you want to clear pending donation attempts? This action cannot be undone.', 'leyka-boost')) . '\');">';
            wp_nonce_field('lutm_clear_pending_action', 'lutm_clear_pending_nonce');
            submit_button(__('Clear pending', 'leyka-boost'), 'secondary', 'lutm_clear_pending', false);
            echo '</form> ';
            self::render_csv_button($filters);
            echo '</div>';

            // ── Summary tables ──────────────────────────────────────
            self::render_summaries($top_sources, $top_campaigns, $paths);

            // ── Main table ──────────────────────────────────────────
            echo '<h2>' . esc_html__('All donations', 'leyka-boost') . '</h2>';
            echo '<table class="widefat striped leyka-utm-table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html('#') . '</th>';
            echo '<th>' . esc_html__('ID', 'leyka-boost') . '</th>';
            echo '<th>' . esc_html__('First touch', 'leyka-boost') . '</th>';
            echo '<th>' . esc_html__('Last touch', 'leyka-boost') . '</th>';
            echo '<th>' . esc_html__('Amount', 'leyka-boost') . '</th>';
            echo '<th>' . esc_html__('Status', 'leyka-boost') . '</th>';
            echo '<th>' . esc_html__('Date', 'leyka-boost') . '</th>';
            echo '</tr></thead><tbody>';

            if (empty($rows)) {
                echo '<tr><td colspan="7">' . esc_html__('UTM data is empty. Data will appear after the first donation with UTM marks.', 'leyka-boost') . '</td></tr>';
            } else {
                foreach ($rows as $index => $row) {
                    $link = admin_url('admin.php?page=leyka_donation_info&donation=' . (int) $row->donation_id);
                    $row_number = (($current_page - 1) * $per_page) + $index + 1;

                    echo '<tr>';
                    echo '<td>' . (int) $row_number . '</td>';
                    echo '<td><a href="' . esc_url($link) . '"><strong>' . (int) $row->donation_id . '</strong></a></td>';
                    echo '<td>' . wp_kses_post( self::utm_block(
                        __('First touch', 'leyka-boost'),
                        $row->utm_first_source,
                        $row->utm_first_medium,
                        $row->utm_first_campaign
                    ) ) . '</td>';
                    echo '<td>' . wp_kses_post( self::utm_block(
                        __('Last touch', 'leyka-boost'),
                        $row->utm_last_source,
                        $row->utm_last_medium,
                        $row->utm_last_campaign
                    ) ) . '</td>';
                    echo '<td>' . esc_html(number_format_i18n((float) $row->amount, 2)) . '</td>';
                    echo '<td>' . wp_kses_post( self::badge($row->status) ) . '</td>';
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
                    'prev_text' => '&laquo; ' . __('Prev', 'leyka-boost'),
                    'next_text' => __('Next', 'leyka-boost') . ' &raquo;',
                    'type'      => 'array',
                ));

                if (!empty($links)) {
                    echo '<div class="tablenav bottom lutm-pagination">';
                    echo '<div class="tablenav-pages">';
                    /* translators: %d: total number of UTM entries */
                    echo '<span class="displaying-num">' . esc_html(sprintf(__('%d entries', 'leyka-boost'), (int) $result['total'])) . '</span>';
                    foreach ($links as $link) {
                        echo wp_kses_post($link);
                    }
                    echo '<span class="lutm-pagination__info">';
                    printf(
                        /* translators: 1: current page number, 2: total pages */
                        esc_html__('Page %1$d of %2$d', 'leyka-boost'),
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
                $touch = isset($_POST['leyka_utm_touch_preference']) && 'last' === sanitize_text_field(wp_unslash($_POST['leyka_utm_touch_preference'])) ? 'last' : 'first';
                update_option('leyka_utm_touch_preference', $touch);
                self::notice(__('Settings saved.', 'leyka-boost'));
            }

            $enabled = (bool) get_option('leyka_utm_logging_enabled', false);
            $touch   = get_option('leyka_utm_touch_preference', 'first');

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Settings', 'leyka-boost') . '</h1>';
            echo '<form method="post">';
            wp_nonce_field('lutm_save_settings_action', 'lutm_settings_nonce');

            echo '<table class="form-table" role="presentation"><tbody>';

            echo '<tr>';
            echo '<th scope="row">' . esc_html__('Analytics by', 'leyka-boost') . '</th>';
            echo '<td>';
            echo '<label><input type="radio" name="leyka_utm_touch_preference" value="first" ' . checked($touch, 'first', false) . '> ';
            echo esc_html__('First touch (recommended)', 'leyka-boost');
            echo '</label><br>';
            echo '<label><input type="radio" name="leyka_utm_touch_preference" value="last" ' . checked($touch, 'last', false) . '> ';
            echo esc_html__('Last touch', 'leyka-boost');
            echo '</label>';
            echo '<p class="description">' . esc_html__('Determines which touch is used for summaries and filters.', 'leyka-boost') . '</p>';
            echo '</td>';
            echo '</tr>';

            echo '<tr>';
            echo '<th scope="row">' . esc_html__('Event log', 'leyka-boost') . '</th>';
            echo '<td>';
            echo '<label><input type="checkbox" name="leyka_utm_logging_enabled" value="1" ' . checked($enabled, true, false) . '> ';
            echo esc_html__('Active', 'leyka-boost');
            echo '</label>';
            echo '<p class="description">' . esc_html__('Inactive by default. Use only for diagnostics.', 'leyka-boost') . '</p>';
            echo '</td>';
            echo '</tr>';

            echo '</tbody></table>';

            submit_button(__('Save settings', 'leyka-boost'), 'primary', 'lutm_save_settings');
            echo '</form>';
            echo '</div>';
        }

        // ── CSV export ──────────────────────────────────────────────

        public static function handle_csv_export() {
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('Insufficient permissions', 'leyka-boost'));
            }

            check_admin_referer('lutm_export_csv');

            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only export filter parameters, sanitized via sanitize_text_field().
            $filters = array(
                'utm_source'   => isset($_GET['utm_source'])   ? sanitize_text_field(wp_unslash($_GET['utm_source']))   : '',
                'utm_medium'   => isset($_GET['utm_medium'])    ? sanitize_text_field(wp_unslash($_GET['utm_medium']))   : '',
                'utm_campaign' => isset($_GET['utm_campaign'])  ? sanitize_text_field(wp_unslash($_GET['utm_campaign'])) : '',
                'status'       => isset($_GET['status'])        ? sanitize_text_field(wp_unslash($_GET['status']))       : '',
                'date_from'    => isset($_GET['date_from'])     ? sanitize_text_field(wp_unslash($_GET['date_from']))    : '',
                'date_to'      => isset($_GET['date_to'])       ? sanitize_text_field(wp_unslash($_GET['date_to']))      : '',
            );
            // phpcs:enable WordPress.Security.NonceVerification.Recommended

            $rows = LeykaUTMTrackerAnalytics::get_export_rows($filters);

            $filename = 'leyka-utm-export-' . wp_date('Y-m-d') . '.csv';

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');

            // BOM for Excel UTF-8
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Streaming CSV to browser output, not a disk file operation.
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

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing browser output stream, not a disk file.
            fclose($output);
            exit;
        }
    }
}
